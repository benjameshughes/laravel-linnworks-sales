<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Sync\Orders\BulkImportOrders;
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
 * - Syncs ALL open orders (no limit, no date filter)
 * - Syncs processed orders from last checkpoint (or last 7 days if first run)
 * - Two separate endpoints: open and processed are mutually exclusive
 *
 * Flow:
 * 1. Get ALL open order IDs → Orders/GetAllOpenOrders (single call, no pagination)
 * 2. Stream processed order IDs since checkpoint → ProcessedOrders/SearchProcessedOrders
 * 3. Fetch & update missing orders (orders no longer in open list)
 *    - Fetches full details to capture processed_date, status, shipping, etc.
 *    - Handles orders processed OUTSIDE checkpoint window (architectural gap fix)
 * 4. Deduplicate IDs (orders processed between open/processed API calls)
 * 5. Fetch full details in batches of 200 → Orders/GetOrdersById (works for both)
 * 6. OrderImportDTO checks processedDate to set is_open (null = open, date = processed)
 * 7. Bulk write to DB (upserts handle any remaining duplicates)
 * 8. Update checkpoint timestamp
 * 9. Warm cache
 *
 * Accuracy improvements:
 * - GetAllOpenOrders (250/min rate limit) vs old GetOpenOrderIds (150/min)
 * - No pagination gaps - captures all orders in single atomic snapshot
 * - Testing showed 2 orders missed by old paginated approach
 * - Deduplication prevents fetching same order twice if processed during sync
 * - Missing orders get full processed data, not just is_open flag
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
        BulkImportOrders $importer,
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

            // Step 1: Get ALL open order IDs (no date filter - always current)
            $syncLog->updateProgress('fetching_open_ids', 0, 4, ['message' => 'Fetching all open orders...']);
            event(new SyncProgressUpdated('fetching-open-ids', 'Fetching all open orders...'));
            Log::info('Fetching open order IDs from Linnworks...');

            $openOrderIds = $api->getAllOpenOrderIds();
            Log::info("Found {$openOrderIds->count()} open order IDs");
            $syncLog->updateProgress('fetching_open_ids', 1, 4, [
                'message' => "Found {$openOrderIds->count()} open orders",
                'open_count' => $openOrderIds->count(),
            ]);

            // Mark orders that are no longer open as closed (fetch full details)
            if ($openOrderIds->isNotEmpty()) {
                $this->markMissingOrdersAsClosed($openOrderIds, $api, $importer);
            }

            // Step 2: Stream processed order IDs since last checkpoint (incremental)
            $syncLog->updateProgress('fetching_processed_ids', 1, 4, ['message' => 'Streaming processed orders (incremental)...']);
            event(new SyncProgressUpdated('fetching-processed-ids', 'Streaming processed orders (incremental)...'));

            Log::info('Streaming processed order IDs (incremental)', [
                'from' => $processedFrom->toDateString(),
                'to' => $processedTo->toDateString(),
                'days_covered' => $processedFrom->diffInDays($processedTo),
            ]);

            // Stream processed orders - gets IDs only, then we fetch details
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

            // Step 3: STREAMING MICRO-BATCH PROCESSING (Memory-efficient!)
            $syncLog->updateProgress('importing', 2, 4, [
                'message' => 'Starting streaming import...',
            ]);

            $currentBatch = 0;
            $totalOrdersFetched = 0;
            $alreadyProcessedIds = collect(); // Track IDs to avoid duplicates

            // Process open orders first
            if ($openOrderIds->isNotEmpty()) {
                $openChunks = $openOrderIds->chunk(200);
                foreach ($openChunks as $chunk) {
                    $currentBatch++;
                    $this->processBatch($api, $importer, $progressTracker, $chunk, $currentBatch, $totalCreated, $totalUpdated, $totalProcessed, $totalFailed);
                    $totalOrdersFetched += $chunk->count();
                    $alreadyProcessedIds = $alreadyProcessedIds->merge($chunk); // Track these IDs
                    unset($chunk);
                }
            }

            // Then stream and process processed orders page by page
            // getOrdersByIds returns full details with processedDate field
            // OrderImportDTO checks processedDate to determine is_open
            foreach ($processedOrderIdsStream as $pageOrderIds) {
                // Deduplicate: remove any IDs we already processed from open orders
                $uniqueIds = $pageOrderIds->diff($alreadyProcessedIds);

                if ($uniqueIds->isEmpty()) {
                    Log::info('Skipped batch - all orders already processed', [
                        'batch_size' => $pageOrderIds->count(),
                        'duplicates_skipped' => $pageOrderIds->count(),
                    ]);

                    continue; // Skip this batch entirely
                }

                if ($uniqueIds->count() < $pageOrderIds->count()) {
                    Log::info('Deduplication removed already-processed orders', [
                        'original_count' => $pageOrderIds->count(),
                        'unique_count' => $uniqueIds->count(),
                        'duplicates_removed' => $pageOrderIds->count() - $uniqueIds->count(),
                    ]);
                }

                $currentBatch++;
                $this->processBatch($api, $importer, $progressTracker, $uniqueIds, $currentBatch, $totalCreated, $totalUpdated, $totalProcessed, $totalFailed);
                $totalOrdersFetched += $uniqueIds->count();
                $alreadyProcessedIds = $alreadyProcessedIds->merge($uniqueIds); // Track these too
                unset($pageOrderIds, $uniqueIds);

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

            // Step 4: Update checkpoint for next incremental sync
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

            // Step 5: Broadcast completion
            $success = $totalFailed === 0 && $totalProcessed > 0;
            event(new SyncCompleted(
                processed: $totalProcessed,
                created: $totalCreated,
                updated: $totalUpdated,
                failed: $totalFailed,
                success: $success,
            ));

            // Step 6: Always warm cache on success
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
        BulkImportOrders $importer,
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
     *
     * Fetches full order details before updating to capture processed data
     * (processed_date, status, shipping info, etc.) for orders processed
     * outside the checkpoint window.
     */
    protected function markMissingOrdersAsClosed(
        \Illuminate\Support\Collection $currentOpenOrderIds,
        LinnworksApiService $api,
        BulkImportOrders $importer
    ): void {
        // Find orders in DB that are no longer in the open list
        $missingOrderIds = Order::where('status', 0)
            ->whereNotIn('order_id', $currentOpenOrderIds->toArray())
            ->pluck('order_id');

        if ($missingOrderIds->isEmpty()) {
            return;
        }

        Log::info('Found orders no longer in open list', [
            'count' => $missingOrderIds->count(),
            'strategy' => 'fetch_full_details',
        ]);

        try {
            // Fetch full order details from Linnworks
            // This gives us the complete processed data (processed_date, status, shipping, etc.)
            $orders = $api->getOrdersByIds($missingOrderIds->toArray());

            if ($orders->isEmpty()) {
                Log::warning('No order details returned for missing orders', [
                    'missing_count' => $missingOrderIds->count(),
                ]);

                // Fall back to simple is_open update
                $this->markOrdersAsClosedSimple($missingOrderIds);

                return;
            }

            // Import with full details (will update processed_date, status, etc.)
            $result = $importer->import($orders);

            Log::info('Updated missing orders with full processed data', [
                'fetched' => $orders->count(),
                'updated' => $result->updated,
                'failed' => $result->failed,
            ]);

        } catch (\Throwable $e) {
            Log::error('Failed to fetch details for missing orders', [
                'error' => $e->getMessage(),
                'missing_count' => $missingOrderIds->count(),
            ]);

            // Fall back to simple is_open update
            $this->markOrdersAsClosedSimple($missingOrderIds);
        }
    }

    /**
     * Fallback: Mark orders as closed without fetching details
     *
     * Only used if fetching full details fails.
     */
    protected function markOrdersAsClosedSimple(\Illuminate\Support\Collection $orderIds): void
    {
        $closedCount = Order::whereIn('order_id', $orderIds->toArray())
            ->update([
                'status' => 1,
                'processed_at' => now(),
            ]);

        Log::info("Marked {$closedCount} orders as processed (fallback - no full details)");
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
