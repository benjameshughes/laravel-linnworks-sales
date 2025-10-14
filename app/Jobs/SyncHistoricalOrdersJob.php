<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Sync\Orders\ImportInBulk;
use App\Actions\Sync\TrackSyncProgress;
use App\DataTransferObjects\ProcessedOrderFilters;
use App\Events\OrdersSynced;
use App\Events\SyncCompleted;
use App\Events\SyncProgressUpdated;
use App\Events\SyncStarted;
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
 * Only syncs PROCESSED orders (no open orders - historical data is all processed).
 * Uses 'processed' date field (when order was fulfilled).
 * Shows progress, persists state for UI.
 *
 * Flow:
 * 1. Get ALL processed order IDs in date range (no limit)
 * 2. Fetch full details in batches of 200
 * 3. Bulk write to DB
 * 4. Persist progress every 10 batches
 * 5. Conditional cache warming (only if affects dashboard periods)
 *
 * Performance: ~300-500 orders/sec with retry logic
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
        $this->timeout = 3600; // 1 hour for large historical imports
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
        ImportInBulk $importer,
        OrderSyncOrchestrator $sync
    ): void {
        // Start sync log with proper type
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
            $progressTracker = TrackSyncProgress::start($syncLog);
            $totalCreated = 0;
            $totalUpdated = 0;
            $totalProcessed = 0;
            $totalFailed = 0;

            // Skip open orders entirely - historical data is all processed
            Log::info('Historical import - processed orders only', [
                'from' => $this->fromDate->toDateString(),
                'to' => $this->toDate->toDateString(),
            ]);

            // Stream ALL processed order IDs in date range (no limit)
            $syncLog->updateProgress('fetching_processed_ids', 0, 3, ['message' => 'Streaming historical orders...']);
            event(new SyncProgressUpdated('fetching-processed-ids', 'Streaming historical orders...'));

            // Use 'processed' date field for historical imports
            $processedOrderIdsStream = $api->streamProcessedOrderIds(
                from: $this->fromDate,
                to: $this->toDate,
                filters: ProcessedOrderFilters::forHistoricalImport()->toArray(),
                userId: null,
                progressCallback: function ($page, $totalPages, $fetchedCount, $totalResults) use ($syncLog) {
                    $message = "Streaming historical orders: page {$page}/".($totalPages ?: '?')." ({$fetchedCount} fetched)";
                    event(new SyncProgressUpdated('fetching-processed-ids', $message, $fetchedCount));

                    if ($page % 10 === 0 || $page === $totalPages) {
                        $syncLog->updateProgress('fetching_processed_ids', $page, max($totalPages, $page), [
                            'message' => $message,
                            'current_page' => $page,
                            'total_pages' => $totalPages,
                            'fetched_count' => $fetchedCount,
                            'total_results' => $totalResults,
                        ]);
                    }
                }
            );

            // Broadcast sync started
            $days = (int) $this->fromDate->diffInDays($this->toDate);
            event(new SyncStarted(0, $days));

            // Skip open/closed status updates (not relevant for historical)

            // STREAMING MICRO-BATCH PROCESSING
            $syncLog->updateProgress('importing', 1, 3, [
                'message' => 'Starting historical import...',
            ]);

            $currentBatch = 0;
            $totalOrdersFetched = 0;

            // Stream and process historical orders page by page
            foreach ($processedOrderIdsStream as $pageOrderIds) {
                $currentBatch++;
                $this->processBatch($api, $importer, $progressTracker, $pageOrderIds, $currentBatch, $totalCreated, $totalUpdated, $totalProcessed, $totalFailed);
                $totalOrdersFetched += $pageOrderIds->count();

                // Persist progress every 10 batches (for UI display)
                if ($currentBatch % 10 === 0) {
                    $this->persistProgress($syncLog, $currentBatch, $totalProcessed, $totalCreated, $totalUpdated, $totalFailed);
                }

                unset($pageOrderIds);

                // Explicit garbage collection hint every 10 batches
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

            // Complete sync log
            $syncLog->complete(
                fetched: $totalOrdersFetched,
                created: $totalCreated,
                updated: $totalUpdated,
                skipped: $totalProcessed - $totalCreated - $totalUpdated,
                failed: $totalFailed
            );

            // Broadcast completion
            $success = $totalFailed === 0 && $totalProcessed > 0;
            event(new SyncCompleted(
                processed: $totalProcessed,
                created: $totalCreated,
                updated: $totalUpdated,
                failed: $totalFailed,
                success: $success,
            ));

            // Conditional cache warming - only if affects dashboard periods
            if ($success && $totalProcessed > 0 && $this->affectsDashboardPeriods()) {
                Log::info('Triggering cache warming after successful historical import', [
                    'orders_processed' => $totalProcessed,
                    'date_range' => [
                        'from' => $this->fromDate->toDateString(),
                        'to' => $this->toDate->toDateString(),
                    ],
                ]);

                event(new OrdersSynced(
                    ordersProcessed: $totalProcessed,
                    syncType: 'historical_import'
                ));
            } else {
                $reason = ! $success ? 'sync had failures' :
                    ($totalProcessed === 0 ? 'no orders processed' :
                    'historical data outside dashboard periods (last 730 days)');

                Log::info('Skipping cache warming', [
                    'reason' => $reason,
                    'success' => $success,
                    'total_processed' => $totalProcessed,
                    'affects_dashboard' => $this->affectsDashboardPeriods(),
                ]);
            }

            Log::info('Historical import finished', [
                'total_orders_fetched' => $totalOrdersFetched,
                'processed' => $totalProcessed,
                'created' => $totalCreated,
                'updated' => $totalUpdated,
                'failed' => $totalFailed,
                'success' => $success,
            ]);

        } catch (\Throwable $e) {
            Log::error('Historical import failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $syncLog->fail($e->getMessage());

            // Broadcast failure to UI
            event(new SyncCompleted(
                processed: $totalProcessed ?? 0,
                created: $totalCreated ?? 0,
                updated: $totalUpdated ?? 0,
                failed: $totalFailed ?? 0,
                success: false,
            ));

            throw $e;
        }
    }

    /**
     * Check if this historical import affects current dashboard periods
     *
     * Dashboard shows: 1d, 7d, 30d, 90d, 180d, 365d, 730d
     * Only warm cache if importing data within the last 730 days
     */
    protected function affectsDashboardPeriods(): bool
    {
        $maxDashboardPeriod = 730; // days
        $oldestDashboardDate = now()->subDays($maxDashboardPeriod)->startOfDay();

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
        int $totalFailed
    ): void {
        $syncLog->updateProgress('importing', $currentBatch, 0, [
            'total_processed' => $totalProcessed,
            'created' => $totalCreated,
            'updated' => $totalUpdated,
            'failed' => $totalFailed,
            'current_batch' => $currentBatch,
            'message' => "Processed {$totalProcessed} orders in {$currentBatch} batches",
        ]);
    }

    /**
     * Process a single batch of order IDs (fetch full details + import)
     *
     * Includes retry logic with exponential backoff for resilience.
     */
    protected function processBatch(
        LinnworksApiService $api,
        ImportInBulk $importer,
        TrackSyncProgress $progressTracker,
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
        $lastException = null;

        while ($attempt < $maxRetries) {
            try {
                $attempt++;

                event(new SyncProgressUpdated(
                    'fetching-batch',
                    "Fetching batch {$currentBatch}...".($attempt > 1 ? " (attempt {$attempt}/{$maxRetries})" : ''),
                    $totalProcessed
                ));

                // Fetch full order details for this batch
                $orders = $api->getOrdersByIds($orderIds->toArray());

                Log::info('Fetched order batch', [
                    'batch' => $currentBatch,
                    'orders_in_batch' => $orders->count(),
                    'attempt' => $attempt,
                ]);

                event(new SyncProgressUpdated(
                    'importing-batch',
                    "Importing batch {$currentBatch}...",
                    $totalProcessed
                ));

                // Import this batch
                $result = $importer->import($orders);

                $totalCreated += $result->created;
                $totalUpdated += $result->updated;
                $totalProcessed += $result->processed;
                $totalFailed += $result->failed;

                Log::info('Imported order batch', [
                    'batch' => $currentBatch,
                    'processed' => $result->processed,
                    'created' => $result->created,
                    'updated' => $result->updated,
                    'failed' => $result->failed,
                    'attempt' => $attempt,
                ]);

                // Broadcast progress every 5 batches
                if ($currentBatch % 5 === 0) {
                    $progressTracker->broadcastPerformanceUpdate(
                        totalProcessed: $totalProcessed,
                        created: $totalCreated,
                        updated: $totalUpdated,
                        failed: $totalFailed,
                        currentBatch: $currentBatch,
                        totalBatches: 0 // Unknown with streaming
                    );
                }

                // Success! Free memory and return
                unset($orders, $result);

                return;

            } catch (\App\Exceptions\Linnworks\LinnworksApiException $e) {
                $lastException = $e;

                // Check if this is a retryable error
                if (! $e->isRetryable()) {
                    Log::error('Non-retryable Linnworks API error on batch', [
                        'batch' => $currentBatch,
                        'attempt' => $attempt,
                        'error' => $e->getUserMessage(),
                        'status_code' => $e->getCode(),
                    ]);

                    throw $e;
                }

                // Log retryable error
                Log::warning('Linnworks API error on batch, will retry', [
                    'batch' => $currentBatch,
                    'attempt' => $attempt,
                    'max_retries' => $maxRetries,
                    'error' => $e->getUserMessage(),
                    'status_code' => $e->getCode(),
                    'is_timeout' => $e->isTimeout(),
                    'is_rate_limited' => $e->isRateLimited(),
                ]);

                // Calculate backoff with exponential increase
                $backoffSeconds = $baseBackoffSeconds * (2 ** ($attempt - 1)); // 5s, 10s, 20s

                // Special handling for rate limits
                if ($e->isRateLimited() && $e->getRetryAfter()) {
                    $backoffSeconds = $e->getRetryAfter();
                    Log::info('Rate limited, using Retry-After header', [
                        'batch' => $currentBatch,
                        'retry_after_seconds' => $backoffSeconds,
                    ]);
                }

                // If this was our last attempt, throw
                if ($attempt >= $maxRetries) {
                    Log::error('Batch failed after all retry attempts', [
                        'batch' => $currentBatch,
                        'total_attempts' => $attempt,
                        'order_ids_count' => $orderIds->count(),
                        'final_error' => $e->getUserMessage(),
                    ]);

                    throw $e;
                }

                // Wait before retrying
                Log::info('Waiting before retry', [
                    'batch' => $currentBatch,
                    'attempt' => $attempt,
                    'backoff_seconds' => $backoffSeconds,
                    'next_attempt' => $attempt + 1,
                ]);

                sleep($backoffSeconds);

            } catch (\Throwable $e) {
                $lastException = $e;

                Log::error('Unexpected error processing batch', [
                    'batch' => $currentBatch,
                    'attempt' => $attempt,
                    'error' => $e->getMessage(),
                    'exception_class' => get_class($e),
                ]);

                // Don't retry unexpected errors
                throw $e;
            }
        }

        // Should never reach here, but if we do, throw the last exception
        if ($lastException) {
            throw $lastException;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('SyncHistoricalOrdersJob failed', [
            'started_by' => $this->startedBy,
            'from' => $this->fromDate->toDateString(),
            'to' => $this->toDate->toDateString(),
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}
