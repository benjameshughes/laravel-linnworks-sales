<?php

namespace App\Jobs;

use App\Models\Order;
use App\Models\SyncLog;
use App\Services\LinnworksApiService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncOpenOrdersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected bool $force;
    protected ?string $startedBy;
    protected SyncLog $syncLog;

    /**
     * Create a new job instance.
     */
    public function __construct(bool $force = false, ?string $startedBy = null)
    {
        $this->force = $force;
        $this->startedBy = $startedBy ?? 'ui';
    }

    /**
     * Execute the job.
     */
    public function handle(LinnworksApiService $linnworksService): void
    {
        // Start sync log
        $this->syncLog = SyncLog::startSync(SyncLog::TYPE_OPEN_ORDERS, [
            'force' => $this->force,
            'started_by' => $this->startedBy,
        ]);

        Log::info('Starting sync job for all open orders', ['force' => $this->force, 'started_by' => $this->startedBy]);

        if (!$linnworksService->isConfigured()) {
            Log::error('Linnworks API is not configured');
            $this->syncLog->fail('Linnworks API not configured');
            throw new \Exception('Linnworks API is not configured. Please check your credentials.');
        }

        try {

            // Step 1: Get open order UUIDs from Linnworks
            Log::info('Fetching open order UUIDs from Linnworks...');
            $openOrderIds = $linnworksService->getAllOpenOrderIds();
            
            Log::info("Found {$openOrderIds->count()} open order UUIDs");
            
            if ($openOrderIds->isEmpty()) {
                Log::warning('No open orders found.');
                $this->syncLog->complete(0);
                return;
            }

            // Step 2: Filter out UUIDs that already exist in database
            $existingOrderIds = Order::whereIn('linnworks_order_id', $openOrderIds->toArray())
                ->pluck('linnworks_order_id')
                ->toArray();
            
            // Mark all existing orders as open (they're in the open orders API)
            if (!empty($existingOrderIds)) {
                Order::whereIn('linnworks_order_id', $existingOrderIds)
                    ->update([
                        'is_open' => true,
                        'last_synced_at' => now(),
                    ]);
                Log::info("Marked {count($existingOrderIds)} existing orders as open");
            }
            
            // Get UUIDs that need full order details
            $newOrderIds = $openOrderIds->diff($existingOrderIds);
            
            Log::info("Processing orders", [
                'total_uuids' => $openOrderIds->count(),
                'existing_orders' => count($existingOrderIds),
                'new_orders_to_fetch' => $newOrderIds->count()
            ]);

            // Step 3: Fetch full details only for new orders
            $openOrders = collect();
            if ($newOrderIds->isNotEmpty()) {
                Log::info('Fetching full details for new orders...');
                $openOrders = $linnworksService->getOrdersByIds($newOrderIds->toArray());
            }
            
            $this->syncLog->update(['total_fetched' => $openOrderIds->count()]);

            // Process only new orders that need full details
            $created = 0;
            $updated = count($existingOrderIds); // Existing orders that were marked as open
            $skipped = 0;
            $failed = 0;

            foreach ($openOrders as $linnworksOrder) {
                try {
                    // Create new order (we already filtered out existing ones)
                    $orderModel = Order::fromLinnworksOrder($linnworksOrder);
                    $orderModel->save();
                    $created++;
                    
                    Log::info("Created order: {$orderModel->order_number}");
                } catch (\Exception $e) {
                    $failed++;
                    Log::error('Failed to sync order', [
                        'order_id' => $linnworksOrder->orderId ?? 'unknown',
                        'order_number' => $linnworksOrder->orderNumber ?? 'unknown',
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                }
            }

            // Mark orders that weren't in the sync as potentially closed
            $this->markMissingOrdersAsClosed($openOrderIds);

            // Complete sync log
            $this->syncLog->complete($openOrderIds->count(), $created, $updated, $skipped, $failed);

            Log::info('All open orders sync completed!', [
                'created' => $created,
                'updated' => $updated,
                'skipped' => $skipped,
                'failed' => $failed,
                'total' => $openOrders->count(),
            ]);

        } catch (\Exception $e) {
            Log::error('Open orders sync failed', ['error' => $e->getMessage()]);
            $this->syncLog->fail($e->getMessage());
            throw $e; // Re-throw to mark job as failed
        }
    }


    /**
     * Mark orders that weren't in the sync response as potentially closed
     */
    protected function markMissingOrdersAsClosed($currentOpenOrderIds): void
    {
        Order::where('is_open', true)
            ->whereNotIn('linnworks_order_id', $currentOpenOrderIds->toArray())
            ->where('last_synced_at', '<', now()->subMinutes(30))
            ->update([
                'is_open' => false,
                'sync_metadata' => \DB::raw("JSON_SET(COALESCE(sync_metadata, '{}'), '$.marked_closed_at', '" . now()->toDateTimeString() . "')"),
            ]);
    }

    /**
     * The job failed to process.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('SyncOpenOrdersJob failed', [
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}