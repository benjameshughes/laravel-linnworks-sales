<?php

declare(strict_types=1);

namespace App\Jobs;

use Carbon\Carbon;
use App\Models\SyncLog;
use App\Events\OrdersSynced;
use App\Events\SyncCompleted;
use Illuminate\Bus\Queueable;
use App\Events\SyncProgressUpdated;
use App\Models\LinnworksConnection;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\SerializesModels;
use BenHughes\Linnworks\LinnworksClient;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Actions\Linnworks\FetchOrderDetails;
use App\Actions\Sync\Orders\BulkImportOrders;
use Illuminate\Contracts\Queue\ShouldBeUnique;

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
final class SyncHistoricalOrdersJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Historical imports write to the same tables and can run for hours, so
     * only one is ever allowed in flight. The UI guard alone is racy - the
     * SyncLog it checks is not created until a worker picks the job up.
     */
    private const UNIQUE_LOCK_SECONDS = 86400;

    private const GC_EVERY_BATCHES = 10;

    public readonly int $timeout;

    public readonly int $uniqueFor;

    public function __construct(
        public readonly Carbon $fromDate,
        public readonly Carbon $toDate,
        public readonly ?string $startedBy = null,
    ) {
        $this->timeout = 0;
        $this->uniqueFor = self::UNIQUE_LOCK_SECONDS;
        $this->onQueue('low');
    }

    public function retryUntil(): \DateTimeInterface
    {
        return now()->addHours(24);
    }

    /**
     * Deliberately not keyed on the date range - overlapping imports would
     * fight over the same orders regardless of which dates they cover.
     */
    public function uniqueId(): string
    {
        return 'sync-historical-orders';
    }

    public function handle(
        LinnworksClient $linnworks,
        FetchOrderDetails $fetchOrderDetails,
        BulkImportOrders $importer
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

        if (! LinnworksConnection::query()->active()->exists()) {
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
            $processedOrderIdsStream = $linnworks->orders()->streamProcessed(
                dateType: 'PROCESSED',
                from: $this->fromDate->toIso8601String(),
                to: $this->toDate->toIso8601String(),
                onPage: function (int $page, int $totalPages, int $totalResults) use ($syncLog, &$totalOrdersExpected): void {
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

            $currentBatch = 0;
            $totalOrdersFetched = 0;
            $startTime = microtime(true);

            foreach ($processedOrderIdsStream as $pageOrderIds) {
                $currentBatch++;

                // Stage 2 is announced here rather than before the loop because
                // the stream is a generator - nothing runs, and the progress
                // callback never fires, until the first page is pulled.
                if ($currentBatch === 1) {
                    $this->announceImportStart($syncLog, $totalOrdersExpected);
                }

                $this->processBatch(
                    $fetchOrderDetails,
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
                    $totalOrdersExpected
                        ? "Batch {$currentBatch} completed: {$totalProcessed}/{$totalOrdersExpected} orders"
                        : "Batch {$currentBatch} completed: {$totalProcessed} orders",
                    $totalProcessed
                ));

                unset($pageOrderIds);

                if ($currentBatch % self::GC_EVERY_BATCHES === 0) {
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

            // A range that genuinely holds no orders is a successful import of
            // nothing, not a failure. Cache warming is gated separately below.
            $success = $totalFailed === 0;

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
    private function affectsDashboardPeriods(): bool
    {
        $oldestDashboardDate = now()->subDays(730)->startOfDay();

        return $this->toDate->greaterThanOrEqualTo($oldestDashboardDate);
    }

    /**
     * Tell the UI stage 2 has begun, now that the expected total is known.
     */
    private function announceImportStart(SyncLog $syncLog, ?int $totalOrdersExpected): void
    {
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
    private function processBatch(
        FetchOrderDetails $fetchOrderDetails,
        BulkImportOrders $importer,
        \Illuminate\Support\Collection $pageRows,
        int $currentBatch,
        int &$totalCreated,
        int &$totalUpdated,
        int &$totalProcessed,
        int &$totalFailed
    ): void {
        // The search endpoint returns summaries; full detail needs a second
        // call keyed on the ids it handed back.
        $orderIds = $pageRows
            ->map(fn (array $row): ?string => $row['pkOrderID'] ?? $row['pkOrderId'] ?? null)
            ->filter()
            ->values();

        // Retries and backoff live in the API client now, so a failure that
        // reaches here has already exhausted them and should fail the job.
        $orders = $fetchOrderDetails($orderIds->all());

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
