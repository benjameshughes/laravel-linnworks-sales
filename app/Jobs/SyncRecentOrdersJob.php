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
use App\Models\Order;
use App\Models\SyncCheckpoint;
use App\Models\SyncLog;
use App\Services\Linnworks\Sync\Orders\OrderSyncOrchestrator;
use App\Services\LinnworksApiService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Sync recent orders - fast and incremental
 *
 * Uses checkpoints for efficient incremental syncing:
 * - Syncs ALL open orders (no limit)
 * - Syncs processed orders from last checkpoint (or last 7 days if first run)
 * - No artificial limits - gets everything since last sync
 *
 * Flow:
 * 1. Get ALL open order IDs (no date filter, no limit)
 * 2. Get processed order IDs since last checkpoint (no limit)
 * 3. Fetch full details in batches of 200
 * 4. Bulk write to DB
 * 5. Update open/closed status
 * 6. Update checkpoint timestamp
 * 7. Warm cache
 *
 * Performance: ~300-500 orders/sec with retry logic
 */
final class SyncRecentOrdersJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public readonly int $uniqueFor;

    public readonly int $tries;

    public readonly int $timeout;

    public function __construct(
        public readonly ?string $startedBy = null,
    ) {
        $this->uniqueFor = 3600; // 1 hour
        $this->tries = 1;
        $this->timeout = 1800; // 30 minutes
        $this->onQueue('high');
    }

    public function uniqueId(): string
    {
        return 'sync-recent-orders';
    }

    public function handle(
        LinnworksApiService $api,
        ImportInBulk $importer,
        OrderSyncOrchestrator $sync
    ): void {
        // Get or create checkpoint for incremental sync
        $checkpoint = SyncCheckpoint::getOrCreateCheckpoint('recent_orders', 'linnworks');
        $checkpoint->startSync();

        // Use checkpoint for efficient incremental sync
        $processedFrom = $checkpoint->getIncrementalStartDate();
        $processedTo = Carbon::now()->endOfDay();

        // Start sync log
        $syncLog = SyncLog::startSync(SyncLog::TYPE_OPEN_ORDERS, [
            'started_by' => $this->startedBy ?? 'system',
            'job_type' => 'recent_orders_sync',
            'incremental_from' => $processedFrom->toDateTimeString(),
            'incremental_to' => $processedTo->toDateTimeString(),
        ]);

        Log::info('Recent orders sync started (incremental)', [
            'started_by' => $this->startedBy,
            'from' => $processedFrom->toDateString(),
            'to' => $processedTo->toDateString(),
            'days_covered' => $processedFrom->diffInDays($processedTo),
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

            // Step 1: Get ALL open order IDs (no limit)
            $syncLog->updateProgress('fetching_open_ids', 0, 4, ['message' => 'Checking open orders...']);
            event(new SyncProgressUpdated('fetching-open-ids', 'Checking open orders...'));
            Log::info('Fetching open order UUIDs from Linnworks...');

            $openOrderIds = $api->getAllOpenOrderIds();
            Log::info("Found {$openOrderIds->count()} open order UUIDs");
            $syncLog->updateProgress('fetching_open_ids', 1, 4, [
                'message' => "Found {$openOrderIds->count()} open orders",
                'open_count' => $openOrderIds->count(),
            ]);

            // Step 2: Stream processed order IDs since last checkpoint (no limit)
            $syncLog->updateProgress('fetching_processed_ids', 1, 4, ['message' => 'Streaming processed orders (incremental)...']);
            event(new SyncProgressUpdated('fetching-processed-ids', 'Streaming processed orders (incremental)...'));

            Log::info('Streaming processed order IDs (incremental)', [
                'from' => $processedFrom->toDateString(),
                'to' => $processedTo->toDateString(),
                'days_covered' => $processedFrom->diffInDays($processedTo),
            ]);

            // Stream processed orders - NO maxOrders parameter
            $processedOrderIdsStream = $api->streamProcessedOrderIds(
                from: $processedFrom,
                to: $processedTo,
                filters: ProcessedOrderFilters::forRecentSync()->toArray(),
                userId: null,
                progressCallback: function ($page, $totalPages, $fetchedCount, $totalResults) use ($syncLog) {
                    $message = "Streaming processed orders: page {$page}/".($totalPages ?: '?')." ({$fetchedCount} fetched)";
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
            $daysCovered = (int) $processedFrom->diffInDays($processedTo);
            event(new SyncStarted(0, $daysCovered));

            // Step 3: Mark existing open orders
            if ($openOrderIds->isNotEmpty()) {
                $existingOrderIds = Order::whereIn('linnworks_order_id', $openOrderIds->toArray())
                    ->pluck('linnworks_order_id')
                    ->toArray();

                if (! empty($existingOrderIds)) {
                    Order::whereIn('linnworks_order_id', $existingOrderIds)
                        ->update([
                            'is_open' => true,
                            'last_synced_at' => now(),
                        ]);
                    Log::info('Marked '.count($existingOrderIds).' existing orders as open');
                }

                // Mark orders not in current sync as closed
                $this->markMissingOrdersAsClosed($openOrderIds);
            }

            // Step 4: STREAMING MICRO-BATCH PROCESSING (Memory-efficient!)
            $syncLog->updateProgress('importing', 2, 3, [
                'message' => 'Starting streaming import...',
            ]);

            $currentBatch = 0;
            $totalOrdersFetched = 0;

            // Process open orders first
            if ($openOrderIds->isNotEmpty()) {
                $openChunks = $openOrderIds->chunk(200);
                foreach ($openChunks as $chunk) {
                    $currentBatch++;
                    $this->processBatch($api, $importer, $progressTracker, $chunk, $currentBatch, $totalCreated, $totalUpdated, $totalProcessed, $totalFailed);
                    $totalOrdersFetched += $chunk->count();
                    unset($chunk);
                }
            }

            // Then stream and process processed orders page by page
            foreach ($processedOrderIdsStream as $pageOrderIds) {
                $currentBatch++;
                $this->processBatch($api, $importer, $progressTracker, $pageOrderIds, $currentBatch, $totalCreated, $totalUpdated, $totalProcessed, $totalFailed);
                $totalOrdersFetched += $pageOrderIds->count();
                unset($pageOrderIds);

                // Explicit garbage collection hint every 10 batches
                if ($currentBatch % 10 === 0 && function_exists('gc_collect_cycles')) {
                    gc_collect_cycles();
                }
            }

            Log::info('Streaming import completed', [
                'total_batches' => $currentBatch,
                'total_orders_fetched' => $totalOrdersFetched,
                'total_processed' => $totalProcessed,
                'created' => $totalCreated,
                'updated' => $totalUpdated,
                'failed' => $totalFailed,
            ]);

            // Step 5: Complete sync log
            $syncLog->complete(
                fetched: $totalOrdersFetched,
                created: $totalCreated,
                updated: $totalUpdated,
                skipped: $totalProcessed - $totalCreated - $totalUpdated,
                failed: $totalFailed
            );

            // Update checkpoint for next incremental sync
            $checkpoint->completeSync(
                synced: $totalProcessed,
                created: $totalCreated,
                updated: $totalUpdated,
                failed: $totalFailed,
                metadata: [
                    'from' => $processedFrom->toDateTimeString(),
                    'to' => $processedTo->toDateTimeString(),
                    'open_orders_count' => $openOrderIds->count(),
                ]
            );

            // Step 6: Broadcast completion
            $success = $totalFailed === 0 && $totalProcessed > 0;
            event(new SyncCompleted(
                processed: $totalProcessed,
                created: $totalCreated,
                updated: $totalUpdated,
                failed: $totalFailed,
                success: $success,
            ));

            // Step 7: Always warm cache on success
            if ($success && $totalProcessed > 0) {
                Log::info('Triggering cache warming after successful sync', [
                    'orders_processed' => $totalProcessed,
                ]);

                event(new OrdersSynced(
                    ordersProcessed: $totalProcessed,
                    syncType: 'recent_orders_sync'
                ));
            }

            Log::info('Recent orders sync completed', [
                'total_orders_fetched' => $totalOrdersFetched,
                'processed' => $totalProcessed,
                'created' => $totalCreated,
                'updated' => $totalUpdated,
                'failed' => $totalFailed,
                'success' => $success,
            ]);

        } catch (\Throwable $e) {
            Log::error('Recent orders sync failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $syncLog->fail($e->getMessage());
            $checkpoint->failSync($e->getMessage());

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

    /**
     * Mark orders that are no longer open as closed
     */
    protected function markMissingOrdersAsClosed(\Illuminate\Support\Collection $currentOpenOrderIds): void
    {
        $closedCount = Order::where('is_open', true)
            ->whereNotIn('linnworks_order_id', $currentOpenOrderIds->toArray())
            ->where('last_synced_at', '<', now()->subMinutes(30))
            ->update([
                'is_open' => false,
                'sync_metadata' => \DB::raw("JSON_SET(COALESCE(sync_metadata, '{}'), '$.marked_closed_at', '".now()->toDateTimeString()."')"),
            ]);

        if ($closedCount > 0) {
            Log::info("Marked {$closedCount} missing orders as closed");
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('SyncRecentOrdersJob failed', [
            'started_by' => $this->startedBy,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}
