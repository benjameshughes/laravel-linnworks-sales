<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Sync\Orders\BulkImportOrders;
use App\DataTransferObjects\ProcessedOrderFilters;
use App\Events\OrdersSynced;
use App\Events\SyncCompleted;
use App\Events\SyncProgressUpdated;
use App\Models\SyncLog;
use App\Services\Linnworks\Sync\Orders\OrderSyncOrchestrator;
use App\Services\LinnworksApiService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Sync historical orders - one-time backfill for specific date range
 *
 * Simple flow:
 * 1. Stream order IDs from Linnworks (Stage 1 - hidden from UI)
 * 2. Fetch + import in batches (Stage 2 - shown in UI)
 * 3. Update SyncLog in database after each batch
 * 4. Broadcast SyncProgressUpdated('batch-completed') for UI refresh
 * 5. Broadcast SyncCompleted when done
 * 6. Trigger cache warming via OrdersSynced event
 *
 * Single source of truth: SyncLog model (database)
 */
final class SyncHistoricalOrdersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public readonly int $tries;

    public readonly int $timeout;

    public function __construct(
        public readonly Carbon $fromDate,
        public readonly Carbon $toDate,
        public readonly ?string $startedBy = null,
    ) {
        $this->tries = 1;
        $this->timeout = 0; // No timeout - historical imports can take hours
        $this->onQueue('low'); // Don't block recent syncs
    }

    public function uniqueId(): string
    {
        return sprintf(
            'sync-historical-orders-%s-%s',
            $this->fromDate->format('Ymd'),
            $this->toDate->format('Ymd')
        );
    }

    public function handle(
        LinnworksApiService $api,
        BulkImportOrders $importer,
        OrderSyncOrchestrator $sync
    ): void {
        $syncLog = SyncLog::startSync(SyncLog::TYPE_HISTORICAL_ORDERS, [
            'started_by' => $this->startedBy ?? 'system',
            'job_type' => 'historical_import',
            'date_range' => [
                'from' => $this->fromDate->toDateString(),
                'to' => $this->toDate->toDateString(),
            ],
        ]);

        Log::info('Historical import started', [
            'started_by' => $this->startedBy,
            'from' => $this->fromDate->toDateString(),
            'to' => $this->toDate->toDateString(),
        ]);

        if (! $api->isConfigured()) {
            Log::error('Linnworks API is not configured');
            $syncLog->fail('Linnworks API not configured');

            throw new \Exception('Linnworks API is not configured. Please check your credentials.');
        }

        try {
            $totalCreated = 0;
            $totalUpdated = 0;
            $totalProcessed = 0;
            $totalFailed = 0;

            // Stage 1: Stream order IDs (hidden from UI)
            $syncLog->updateProgress('historical_import', 0, 100, [
                'message' => 'Streaming historical orders...',
                'stage' => 1,
            ]);

            $totalOrdersExpected = null;
            $processedOrderIdsStream = $api->streamProcessedOrderIds(
                from: $this->fromDate,
                to: $this->toDate,
                filters: ProcessedOrderFilters::forHistoricalImport()->toArray(),
                userId: null,
                progressCallback: function ($page, $totalPages, $fetchedCount, $totalResults) use ($syncLog, &$totalOrdersExpected) {
                    if ($page === 1 && $totalResults > 0) {
                        $totalOrdersExpected = $totalResults;
                    }

                    // Update database only every 10 pages (Stage 1 is hidden anyway)
                    if ($page % 10 === 0 || $page === $totalPages) {
                        $stageProgress = $totalPages > 0 ? (int) (($page / $totalPages) * 10) : 0;
                        $syncLog->updateProgress('historical_import', $stageProgress, 100, [
                            'message' => "Streaming order IDs: page {$page}/{$totalPages}",
                            'stage' => 1,
                        ]);
                    }
                }
            );

            // Stage 2: Import batches (shown in UI)
            $syncLog->updateProgress('historical_import', 10, 100, [
                'message' => $totalOrdersExpected
                    ? "Starting import of {$totalOrdersExpected} orders..."
                    : 'Starting historical import...',
                'stage' => 2,
                'total_processed' => 0,
                'total_expected' => $totalOrdersExpected ?? 0,
                'created' => 0,
                'updated' => 0,
                'failed' => 0,
                'current_batch' => 0,
            ]);

            $currentBatch = 0;
            $totalOrdersFetched = 0;
            $startTime = microtime(true);

            foreach ($processedOrderIdsStream as $pageOrderIds) {
                $currentBatch++;

                $this->processBatch(
                    $api,
                    $importer,
                    $pageOrderIds,
                    $currentBatch,
                    $totalCreated,
                    $totalUpdated,
                    $totalProcessed,
                    $totalFailed
                );

                $totalOrdersFetched += $pageOrderIds->count();

                // Update database with progress
                $this->persistProgress(
                    $syncLog,
                    $currentBatch,
                    $totalProcessed,
                    $totalCreated,
                    $totalUpdated,
                    $totalFailed,
                    $totalOrdersExpected,
                    $startTime
                );

                // Broadcast to UI - this is the only event ImportProgress listens to
                event(new SyncProgressUpdated(
                    'batch-completed',
                    "Batch {$currentBatch} completed: {$totalProcessed}/{$totalOrdersExpected} orders",
                    $totalProcessed
                ));

                unset($pageOrderIds);

                if ($currentBatch % 10 === 0 && function_exists('gc_collect_cycles')) {
                    gc_collect_cycles();
                }
            }

            Log::info('Historical import completed', [
                'total_batches' => $currentBatch,
                'total_orders_fetched' => $totalOrdersFetched,
                'total_processed' => $totalProcessed,
                'created' => $totalCreated,
                'updated' => $totalUpdated,
                'failed' => $totalFailed,
            ]);

            $syncLog->complete(
                fetched: $totalOrdersFetched,
                created: $totalCreated,
                updated: $totalUpdated,
                skipped: $totalProcessed - $totalCreated - $totalUpdated,
                failed: $totalFailed
            );

            $success = $totalFailed === 0 && $totalProcessed > 0;

            // Broadcast completion - ImportProgress listens to this
            event(new SyncCompleted(
                processed: $totalProcessed,
                created: $totalCreated,
                updated: $totalUpdated,
                failed: $totalFailed,
                success: $success,
            ));

            // Trigger cache warming if needed
            if ($success && $totalProcessed > 0 && $this->affectsDashboardPeriods()) {
                Log::info('Triggering cache warming after successful historical import');

                event(new OrdersSynced(
                    ordersProcessed: $totalProcessed,
                    syncType: 'historical_import'
                ));
            }

        } catch (\Throwable $e) {
            Log::error('Historical import failed', [
                'error' => $e->getMessage(),
            ]);

            $syncLog->fail($e->getMessage());

            throw $e;
        }
    }

    /**
     * Check if this historical import affects current dashboard periods (last 730 days)
     */
    protected function affectsDashboardPeriods(): bool
    {
        $oldestDashboardDate = now()->subDays(730)->startOfDay();

        return $this->toDate->greaterThanOrEqualTo($oldestDashboardDate);
    }

    /**
     * Persist progress to database for UI display
     */
    private function persistProgress(
        SyncLog $syncLog,
        int $currentBatch,
        int $totalProcessed,
        int $totalCreated,
        int $totalUpdated,
        int $totalFailed,
        ?int $totalOrdersExpected,
        float $startTime
    ): void {
        $stageProgress = 10;

        if ($totalOrdersExpected > 0 && $totalProcessed > 0) {
            $stageProgress = 10 + (int) (($totalProcessed / $totalOrdersExpected) * 90);
            $stageProgress = min($stageProgress, 100);
        }

        $timeElapsed = microtime(true) - $startTime;
        $ordersPerSecond = $totalProcessed > 0 ? $totalProcessed / max(0.001, $timeElapsed) : 0;
        $estimatedRemaining = null;

        if ($totalOrdersExpected > 0 && $totalProcessed > 0 && $totalProcessed < $totalOrdersExpected) {
            $remaining = $totalOrdersExpected - $totalProcessed;
            $estimatedRemaining = $ordersPerSecond > 0 ? $remaining / $ordersPerSecond : null;
        }

        $syncLog->updateProgress('historical_import', $stageProgress, 100, [
            'message' => $totalOrdersExpected
                ? "Importing orders: {$totalProcessed}/{$totalOrdersExpected}"
                : "Importing orders: {$totalProcessed} processed",
            'stage' => 2,
            'total_processed' => $totalProcessed,
            'total_expected' => $totalOrdersExpected ?? 0,
            'created' => $totalCreated,
            'updated' => $totalUpdated,
            'failed' => $totalFailed,
            'current_batch' => $currentBatch,
            'orders_per_second' => round($ordersPerSecond, 2),
            'memory_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
            'time_elapsed' => round($timeElapsed, 2),
            'estimated_remaining' => $estimatedRemaining ? round($estimatedRemaining, 2) : null,
        ]);
    }

    /**
     * Process a single batch with retry logic
     */
    protected function processBatch(
        LinnworksApiService $api,
        BulkImportOrders $importer,
        \Illuminate\Support\Collection $orderIds,
        int $currentBatch,
        int &$totalCreated,
        int &$totalUpdated,
        int &$totalProcessed,
        int &$totalFailed
    ): void {
        $maxRetries = 3;
        $baseBackoffSeconds = 5;
        $attempt = 0;

        while ($attempt < $maxRetries) {
            try {
                $attempt++;

                $orders = $api->getOrdersByIds($orderIds->toArray());

                Log::debug('Fetched order batch', [
                    'batch' => $currentBatch,
                    'orders_in_batch' => $orders->count(),
                ]);

                $result = $importer->import($orders);

                $totalCreated += $result->created;
                $totalUpdated += $result->updated;
                $totalProcessed += $result->processed;
                $totalFailed += $result->failed;

                Log::debug('Imported order batch', [
                    'batch' => $currentBatch,
                    'processed' => $result->processed,
                    'created' => $result->created,
                    'updated' => $result->updated,
                ]);

                unset($orders, $result);

                return;

            } catch (\App\Exceptions\Linnworks\LinnworksApiException $e) {
                if (! $e->isRetryable() || $attempt >= $maxRetries) {
                    Log::error('Batch failed', [
                        'batch' => $currentBatch,
                        'attempt' => $attempt,
                        'error' => $e->getUserMessage(),
                    ]);

                    throw $e;
                }

                $backoffSeconds = $e->isRateLimited() && $e->getRetryAfter()
                    ? $e->getRetryAfter()
                    : $baseBackoffSeconds * (2 ** ($attempt - 1));

                Log::warning('Retrying batch after error', [
                    'batch' => $currentBatch,
                    'attempt' => $attempt,
                    'backoff_seconds' => $backoffSeconds,
                ]);

                sleep($backoffSeconds);

            } catch (\Throwable $e) {
                Log::error('Unexpected error processing batch', [
                    'batch' => $currentBatch,
                    'error' => $e->getMessage(),
                ]);

                throw $e;
            }
        }
    }

    /**
     * Handle job failure - broadcast to UI
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('SyncHistoricalOrdersJob failed', [
            'error' => $exception->getMessage(),
        ]);

        try {
            $syncLog = SyncLog::where('sync_type', SyncLog::TYPE_HISTORICAL_ORDERS)
                ->whereIn('status', [SyncLog::STATUS_STARTED, SyncLog::STATUS_FAILED])
                ->latest('started_at')
                ->first();

            if ($syncLog && $syncLog->status === SyncLog::STATUS_STARTED) {
                $syncLog->fail($exception->getMessage());
            }

            $progressData = $syncLog?->progress_data ?? [];

            event(new SyncCompleted(
                processed: $progressData['total_processed'] ?? 0,
                created: $progressData['created'] ?? 0,
                updated: $progressData['updated'] ?? 0,
                failed: $progressData['failed'] ?? 0,
                success: false,
            ));
        } catch (\Throwable $e) {
            Log::error('Failed to broadcast job failure event', ['error' => $e->getMessage()]);
        }
    }
}
