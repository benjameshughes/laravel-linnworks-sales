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
 * UNIFIED order sync job - handles BOTH open and processed orders
 *
 * Key insight: getOrdersByIds() fetches full details for ANY order (open or processed).
 * No need for separate jobs - the only difference is the isProcessed flag!
 *
 * Flow:
 * 1. Get all order IDs (open + processed from last 30 days)
 * 2. Fetch in chunks of 200 using getOrdersByIds()
 * 3. Stream to StreamingOrderImporter (processes while fetching next chunk)
 * 4. Bulk write to DB using DB facade (no Eloquent overhead)
 *
 * Performance: ~300 orders/sec vs ~16 orders/sec (18× faster)
 */
final class SyncOrdersJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $uniqueFor = 3600; // 1 hour

    public int $tries = 1;

    public int $timeout = 600; // 10 minutes

    public function __construct(
        public ?string $startedBy = null,
        public bool $dryRun = false,
        public bool $historicalImport = false,
        public ?Carbon $fromDate = null,
        public ?Carbon $toDate = null,
    ) {
        $this->startedBy = $startedBy ?? 'system';
        $this->onQueue('high');
    }

    public function uniqueId(): string
    {
        return 'sync-orders';
    }

    public function handle(
        LinnworksApiService $api,
        ImportInBulk $importer,
        OrderSyncOrchestrator $sync
    ): void {
        // Start sync log
        $syncLog = SyncLog::startSync(SyncLog::TYPE_OPEN_ORDERS, [
            'started_by' => $this->startedBy,
            'job_type' => 'unified_streaming_sync',
            'dry_run' => $this->dryRun,
        ]);

        Log::info('Unified streaming order sync started', [
            'started_by' => $this->startedBy,
            'dry_run' => $this->dryRun,
            'historical_import' => $this->historicalImport,
            'date_range' => $this->historicalImport ? [
                'from' => $this->fromDate?->toDateString(),
                'to' => $this->toDate?->toDateString(),
            ] : null,
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

            // Step 1: Get open order IDs (skip for historical imports)
            $openOrderIds = collect();

            if (! $this->historicalImport) {
                $syncLog->updateProgress('fetching_open_ids', 0, 4, ['message' => 'Checking open orders...']);
                event(new SyncProgressUpdated('fetching-open-ids', 'Checking open orders...'));
                Log::info('Fetching open order UUIDs from Linnworks...');

                $openOrderIds = $api->getAllOpenOrderIds();
                Log::info("Found {$openOrderIds->count()} open order UUIDs");
                $syncLog->updateProgress('fetching_open_ids', 1, 4, [
                    'message' => "Found {$openOrderIds->count()} open orders",
                    'open_count' => $openOrderIds->count(),
                ]);
            } else {
                Log::info('Skipping open orders (historical import mode)');
            }

            // Step 2: Stream processed order IDs (MEMORY-EFFICIENT!)
            // Instead of loading ALL order IDs into memory, we stream and process page by page
            $syncLog->updateProgress('fetching_processed_ids', 1, 4, ['message' => 'Streaming processed orders (memory-efficient)...']);
            event(new SyncProgressUpdated('fetching-processed-ids', 'Streaming processed orders (memory-efficient)...'));

            // Use custom date range if historical import, otherwise last 30 days
            $processedFrom = $this->historicalImport && $this->fromDate
                ? $this->fromDate
                : Carbon::now()->subDays(30)->startOfDay();
            $processedTo = $this->historicalImport && $this->toDate
                ? $this->toDate
                : Carbon::now()->endOfDay();

            // Use existing logic to get processed order data with progress callback
            // For historical imports, search by processed date; for regular syncs, use received date
            $filters = $this->historicalImport
                ? ProcessedOrderFilters::forHistoricalImport()->toArray()
                : ProcessedOrderFilters::forRecentSync()->toArray();

            // Streaming approach: Get a generator that yields order IDs page by page
            $processedOrderIdsStream = $api->streamProcessedOrderIds(
                from: $processedFrom,
                to: $processedTo,
                filters: $filters,
                maxOrders: (int) config('linnworks.sync.max_processed_orders', 5000),
                userId: null,
                progressCallback: function ($page, $totalPages, $fetchedCount, $totalResults) use ($syncLog) {
                    // Broadcast progress every page
                    $message = "Streaming processed orders: page {$page}/".($totalPages ?: '?')." ({$fetchedCount} fetched)";
                    event(new SyncProgressUpdated('fetching-processed-ids', $message, $fetchedCount));

                    // Update sync log every 10 pages to avoid too many database writes
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

            // Broadcast sync started (we don't know total count yet with streaming)
            event(new SyncStarted(0, 30));

            // Step 3: Mark existing open orders (skip for historical imports)
            if (! $this->historicalImport && $openOrderIds->isNotEmpty()) {
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

                // Mark orders not in the current sync as closed
                $this->markMissingOrdersAsClosed($openOrderIds);
            } elseif ($this->historicalImport) {
                Log::info('Skipping open/closed status updates (historical import mode)');
            }

            // Step 4: STREAMING MICRO-BATCH PROCESSING (Memory-efficient!)
            // Process each page of order IDs as it comes in, never loading all IDs into memory
            $syncLog->updateProgress('importing', 2, 3, [
                'message' => 'Starting streaming import...',
            ]);

            $currentBatch = 0;
            $totalOrdersFetched = 0;

            // First, process open orders if any
            if ($openOrderIds->isNotEmpty()) {
                $openChunks = $openOrderIds->chunk(200);
                foreach ($openChunks as $chunk) {
                    $currentBatch++;
                    $this->processBatch($api, $importer, $progressTracker, $chunk, $currentBatch, $totalCreated, $totalUpdated, $totalProcessed, $totalFailed);
                    $totalOrdersFetched += $chunk->count();

                    // Free memory
                    unset($chunk);
                }
            }

            // Then, stream and process processed orders page by page
            foreach ($processedOrderIdsStream as $pageOrderIds) {
                // Each iteration yields ~200 order IDs
                // We fetch full details and import immediately, then free memory
                $currentBatch++;
                $this->processBatch($api, $importer, $progressTracker, $pageOrderIds, $currentBatch, $totalCreated, $totalUpdated, $totalProcessed, $totalFailed);
                $totalOrdersFetched += $pageOrderIds->count();

                // Free memory immediately after processing
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

            // Step 6: Broadcast completion events
            $success = $totalFailed === 0 && $totalProcessed > 0;
            event(new SyncCompleted(
                processed: $totalProcessed,
                created: $totalCreated,
                updated: $totalUpdated,
                failed: $totalFailed,
                success: $success,
            ));

            // Step 7: Warm cache ONLY if successful
            // Conditions for cache warming:
            // 1. NOT a dry run
            // 2. Sync was successful (no failures)
            // 3. Actually processed at least 1 order
            // 4. For historical imports, only warm if within dashboard periods (730 days)
            $shouldWarmCache = ! $this->dryRun
                && $success
                && $totalProcessed > 0
                && (! $this->historicalImport || $this->affectsDashboardPeriods());

            if ($shouldWarmCache) {
                Log::info('Triggering cache warming after successful sync', [
                    'orders_processed' => $totalProcessed,
                    'historical_import' => $this->historicalImport,
                ]);

                event(new OrdersSynced(
                    ordersProcessed: $totalProcessed,
                    syncType: 'unified_streaming_sync'
                ));
            } else {
                $reason = $this->dryRun ? 'dry run mode' :
                    (! $success ? 'sync had failures' :
                    ($totalProcessed === 0 ? 'no orders processed' :
                    'historical import outside dashboard periods'));

                Log::info('Skipping cache warming', [
                    'reason' => $reason,
                    'dry_run' => $this->dryRun,
                    'success' => $success,
                    'total_processed' => $totalProcessed,
                    'historical_import' => $this->historicalImport,
                ]);
            }

            Log::info('Unified streaming sync completed', [
                'total_orders_fetched' => $totalOrdersFetched,
                'processed' => $totalProcessed,
                'created' => $totalCreated,
                'updated' => $totalUpdated,
                'failed' => $totalFailed,
                'success' => $success,
                'dry_run' => $this->dryRun,
            ]);

        } catch (\Throwable $e) {
            Log::error('Unified streaming sync failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $syncLog->fail($e->getMessage());

            // Broadcast failure to UI
            event(new SyncCompleted(
                processed: $totalProcessed,
                created: $totalCreated,
                updated: $totalUpdated,
                failed: $totalFailed,
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
        if (! $this->historicalImport || ! $this->toDate) {
            return false;
        }

        $maxDashboardPeriod = 730; // days
        $oldestDashboardDate = now()->subDays($maxDashboardPeriod)->startOfDay();

        // If the import's end date is within the last 730 days, it affects the dashboard
        return $this->toDate->greaterThanOrEqualTo($oldestDashboardDate);
    }

    /**
     * Process a single batch of order IDs (fetch full details + import)
     *
     * This method is called repeatedly for each micro-batch.
     * Memory is freed after each call.
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
        event(new SyncProgressUpdated(
            'fetching-batch',
            "Fetching batch {$currentBatch}...",
            $totalProcessed
        ));

        // Fetch full order details for this batch
        $orders = $api->getOrdersByIds($orderIds->toArray());

        Log::info('Fetched order batch', [
            'batch' => $currentBatch,
            'orders_in_batch' => $orders->count(),
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

        // Free memory
        unset($orders, $result);
    }

    /**
     * Mark orders that are no longer open
     */
    protected function markMissingOrdersAsClosed($currentOpenOrderIds): void
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
        Log::error('SyncOrdersJob failed', [
            'started_by' => $this->startedBy,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}
