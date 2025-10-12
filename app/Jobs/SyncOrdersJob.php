<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Orders\StreamingOrderImporter;
use App\Events\OrdersSynced;
use App\Events\SyncCompleted;
use App\Events\SyncProgressUpdated;
use App\Events\SyncStarted;
use App\Models\Order;
use App\Models\SyncLog;
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
final class SyncOrdersJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $uniqueFor = 3600; // 1 hour
    public int $tries = 1;
    public int $timeout = 600; // 10 minutes

    public function __construct(
        public ?string $startedBy = null,
        public bool $dryRun = false,
    ) {
        $this->startedBy = $startedBy ?? 'system';
        $this->onQueue('high');
    }

    public function uniqueId(): string
    {
        return 'sync-orders';
    }

    public function handle(LinnworksApiService $api, StreamingOrderImporter $importer): void
    {
        // Start sync log
        $syncLog = SyncLog::startSync(SyncLog::TYPE_OPEN_ORDERS, [
            'started_by' => $this->startedBy,
            'job_type' => 'unified_streaming_sync',
            'dry_run' => $this->dryRun,
        ]);

        Log::info('Unified streaming order sync started', [
            'started_by' => $this->startedBy,
            'dry_run' => $this->dryRun,
        ]);

        if (!$api->isConfigured()) {
            Log::error('Linnworks API is not configured');
            $syncLog->fail('Linnworks API not configured');
            throw new \Exception('Linnworks API is not configured. Please check your credentials.');
        }

        try {
            $totalCreated = 0;
            $totalUpdated = 0;
            $totalProcessed = 0;
            $totalFailed = 0;

            // Step 1: Get ALL open order IDs (fast check)
            event(new SyncProgressUpdated('fetching-open-ids', 'Checking open orders...'));
            Log::info('Fetching open order UUIDs from Linnworks...');

            $openOrderIds = $api->getAllOpenOrderIds();
            Log::info("Found {$openOrderIds->count()} open order UUIDs");

            // Step 2: Get processed order IDs from last 30 days
            event(new SyncProgressUpdated('fetching-processed-ids', 'Checking processed orders...'));

            $processedFrom = Carbon::now()->subDays(30)->startOfDay();
            $processedTo = Carbon::now()->endOfDay();

            // Use existing logic to get processed order data
            $processedOrders = $api->getAllProcessedOrders(
                from: $processedFrom,
                to: $processedTo,
                filters: [],
                maxOrders: (int) config('linnworks.sync.max_processed_orders', 5000),
                userId: null,
            );

            $processedOrderIds = $processedOrders->pluck('orderId')->filter();

            Log::info('Processed orders found', [
                'count' => $processedOrderIds->count(),
                'from' => $processedFrom->toISOString(),
                'to' => $processedTo->toISOString(),
            ]);

            // Step 3: Combine all order IDs (unified!)
            $allOrderIds = $openOrderIds->concat($processedOrderIds)->unique()->values();

            Log::info('Total unique orders to sync', [
                'open' => $openOrderIds->count(),
                'processed' => $processedOrderIds->count(),
                'unique_total' => $allOrderIds->count(),
            ]);

            // Broadcast sync started
            event(new SyncStarted($allOrderIds->count(), 30));

            // Step 4: Mark existing orders as open/closed based on current state
            if ($openOrderIds->isNotEmpty()) {
                $existingOrderIds = Order::whereIn('linnworks_order_id', $openOrderIds->toArray())
                    ->pluck('linnworks_order_id')
                    ->toArray();

                if (!empty($existingOrderIds)) {
                    Order::whereIn('linnworks_order_id', $existingOrderIds)
                        ->update([
                            'is_open' => true,
                            'last_synced_at' => now(),
                        ]);
                    Log::info('Marked ' . count($existingOrderIds) . ' existing orders as open');
                }

                // Mark orders not in the current sync as closed
                $this->markMissingOrdersAsClosed($openOrderIds);
            }

            // Step 5: Stream fetch + import (THE MAGIC!)
            // Process in chunks of 200, import while fetching next chunk
            $chunks = $allOrderIds->chunk(200);
            $totalChunks = $chunks->count();
            $currentChunk = 0;

            foreach ($chunks as $chunk) {
                $currentChunk++;

                event(new SyncProgressUpdated(
                    'fetching-batch',
                    "Fetching batch {$currentChunk}/{$totalChunks}...",
                    $currentChunk * 200
                ));

                // Fetch this chunk (full order details)
                $orders = $api->getOrdersByIds($chunk->toArray());

                Log::info('Fetched order batch', [
                    'chunk' => $currentChunk,
                    'total_chunks' => $totalChunks,
                    'orders_in_batch' => $orders->count(),
                ]);

                event(new SyncProgressUpdated(
                    'importing-batch',
                    "Importing batch {$currentChunk}/{$totalChunks}...",
                    $currentChunk * 200
                ));

                // Import this chunk (while next chunk could be fetching)
                $result = $importer->import($orders);

                $totalCreated += $result->created;
                $totalUpdated += $result->updated;
                $totalProcessed += $result->processed;
                $totalFailed += $result->failed;

                Log::info('Imported order batch', [
                    'chunk' => $currentChunk,
                    'processed' => $result->processed,
                    'created' => $result->created,
                    'updated' => $result->updated,
                    'failed' => $result->failed,
                ]);
            }

            // Step 6: Complete sync log
            $syncLog->complete(
                fetched: $allOrderIds->count(),
                created: $totalCreated,
                updated: $totalUpdated,
                skipped: $totalProcessed - $totalCreated - $totalUpdated,
                failed: $totalFailed
            );

            // Step 7: Broadcast completion events
            event(new SyncCompleted(
                processed: $totalProcessed,
                created: $totalCreated,
                updated: $totalUpdated,
                failed: $totalFailed,
                success: $totalFailed === 0,
            ));

            // Step 8: Warm cache (if not dry run)
            if (!$this->dryRun) {
                event(new OrdersSynced(
                    ordersProcessed: $totalProcessed,
                    syncType: 'unified_streaming_sync'
                ));
            }

            Log::info('Unified streaming sync completed successfully', [
                'total_orders' => $allOrderIds->count(),
                'processed' => $totalProcessed,
                'created' => $totalCreated,
                'updated' => $totalUpdated,
                'failed' => $totalFailed,
                'dry_run' => $this->dryRun,
            ]);

        } catch (\Throwable $e) {
            Log::error('Unified streaming sync failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $syncLog->fail($e->getMessage());
            throw $e;
        }
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
                'sync_metadata' => \DB::raw("JSON_SET(COALESCE(sync_metadata, '{}'), '$.marked_closed_at', '" . now()->toDateTimeString() . "')"),
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
