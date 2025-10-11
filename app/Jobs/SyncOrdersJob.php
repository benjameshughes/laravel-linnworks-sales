<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Linnworks\Orders\ImportOrders;
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

final class SyncOrdersJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $uniqueFor = 3600; // 1 hour
    public int $tries = 1;
    public int $timeout = 600; // 10 minutes

    public function __construct(
        public ?string $startedBy = null
    ) {
        $this->startedBy = $startedBy ?? 'system';
        $this->onQueue('high');
    }

    public function uniqueId(): string
    {
        return 'sync-orders';
    }

    public function handle(LinnworksApiService $api, ImportOrders $importOrders): void
    {
        // Start sync log
        $syncLog = SyncLog::startSync(SyncLog::TYPE_OPEN_ORDERS, [
            'started_by' => $this->startedBy,
            'job_type' => 'unified_sync',
        ]);

        Log::info('Starting unified order sync', [
            'started_by' => $this->startedBy,
        ]);

        if (!$api->isConfigured()) {
            Log::error('Linnworks API is not configured');
            $syncLog->fail('Linnworks API not configured');
            throw new \Exception('Linnworks API is not configured. Please check your credentials.');
        }

        try {
            // Broadcast sync started
            event(new SyncStarted(90, 30));

            // Step 1: Get all open order UUIDs (fast check)
            event(new SyncProgressUpdated('fetching-open-ids', 'Checking open orders...'));
            Log::info('Fetching open order UUIDs from Linnworks...');

            $openOrderIds = $api->getAllOpenOrderIds();
            Log::info("Found {$openOrderIds->count()} open order UUIDs");

            // Step 2: Mark existing orders as open (quick DB operation)
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

                // Step 3: Mark orders not in the current sync as closed
                $this->markMissingOrdersAsClosed($openOrderIds);
            }

            // Step 4: Fetch recent open orders with FULL details
            event(new SyncProgressUpdated('fetching-open', 'Fetching open order details...'));

            $openOrders = $api->getRecentOpenOrders(
                userId: null,
                days: 90,
                maxOrders: (int) config('linnworks.sync.max_open_orders', 1000),
            );

            Log::info('Open orders with details fetched', [
                'open_orders_count' => $openOrders->count(),
            ]);

            event(new SyncProgressUpdated('fetched-open', "Fetched {$openOrders->count()} open orders", $openOrders->count()));

            // Step 5: Fetch processed orders with FULL details
            event(new SyncProgressUpdated('fetching-processed', 'Fetching processed orders...'));

            $from = Carbon::now()->subDays(30)->startOfDay();
            $to = Carbon::now()->endOfDay();

            $processedOrders = $api->getAllProcessedOrders(
                from: $from,
                to: $to,
                filters: [],
                maxOrders: (int) config('linnworks.sync.max_processed_orders', 5000),
                userId: null,
            );

            Log::info('Processed orders with details fetched', [
                'processed_orders_count' => $processedOrders->count(),
                'from' => $from->toISOString(),
                'to' => $to->toISOString(),
            ]);

            event(new SyncProgressUpdated('fetched-processed', "Fetched {$processedOrders->count()} processed orders", $processedOrders->count()));

            // Step 6: Combine both collections
            $combinedOrders = $openOrders->concat($processedOrders)->values();

            Log::info('Combined orders ready for import', [
                'open_count' => $openOrders->count(),
                'processed_count' => $processedOrders->count(),
                'combined_count' => $combinedOrders->count(),
            ]);

            // Step 7: Import/update ALL orders
            event(new SyncProgressUpdated('importing', "Importing {$combinedOrders->count()} orders...", $combinedOrders->count()));

            $result = $importOrders->handle($combinedOrders, false);

            Log::info('Order import finished', [
                'processed' => $result->processed,
                'created' => $result->created,
                'updated' => $result->updated,
                'skipped' => $result->skipped,
                'failed' => $result->failed,
            ]);

            // Step 8: Complete sync log
            $syncLog->complete(
                fetched: $combinedOrders->count(),
                created: $result->created,
                updated: $result->updated,
                skipped: $result->skipped,
                failed: $result->failed
            );

            // Step 9: Broadcast completion events
            event(new SyncCompleted(
                processed: $result->processed,
                created: $result->created,
                updated: $result->updated,
                failed: $result->failed,
                success: $result->failed === 0,
            ));

            // Step 10: Warm cache
            event(new OrdersSynced(
                ordersProcessed: $combinedOrders->count(),
                syncType: 'unified_sync'
            ));

            Log::info('Unified sync completed successfully', [
                'total_orders' => $combinedOrders->count(),
                'created' => $result->created,
                'updated' => $result->updated,
            ]);

        } catch (\Throwable $e) {
            Log::error('Unified sync failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $syncLog->fail($e->getMessage());
            throw $e;
        }
    }

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
