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

            // Step 4: Fetch FULL details for recent open orders
            // Filter to recent orders (last 90 days) for efficiency
            event(new SyncProgressUpdated('fetching-open', 'Fetching open order details...'));

            $recentCutoff = Carbon::now()->subDays(90)->startOfDay();
            $maxOpenOrders = (int) config('linnworks.sync.max_open_orders', 1000);

            // Get full order details using GetOrdersByIds API for ALL the data (shipping, notes, etc.)
            // NOTE: Linnworks API has a limit of 200 IDs per request, so we need to chunk
            $recentOpenOrderIds = $openOrderIds->take($maxOpenOrders);
            Log::info('SyncOrdersJob: About to call getOrdersByIds in chunks', [
                'total_order_ids' => $recentOpenOrderIds->count(),
                'chunk_size' => 200,
                'first_few_ids' => $recentOpenOrderIds->take(3)->toArray(),
            ]);

            $openOrders = collect();
            foreach ($recentOpenOrderIds->chunk(200) as $chunkIndex => $chunk) {
                $chunkOrders = $api->getOrdersByIds($chunk->toArray());
                $openOrders = $openOrders->merge($chunkOrders);

                Log::info('SyncOrdersJob: getOrdersByIds chunk processed', [
                    'chunk_index' => $chunkIndex + 1,
                    'chunk_size' => $chunk->count(),
                    'orders_returned' => $chunkOrders->count(),
                    'total_so_far' => $openOrders->count(),
                ]);
            }

            Log::info('SyncOrdersJob: All getOrdersByIds chunks completed', [
                'total_orders_returned' => $openOrders->count(),
            ]);

            // Filter to recent orders only (received within last 90 days)
            $openOrders = $openOrders->filter(function ($order) use ($recentCutoff) {
                if (!$order instanceof \App\DataTransferObjects\LinnworksOrder) {
                    return true; // Keep if not a DTO (will be converted later)
                }
                return $order->receivedDate === null || $order->receivedDate->greaterThanOrEqualTo($recentCutoff);
            })->values();

            Log::info('Open orders with FULL details fetched', [
                'total_open_ids' => $openOrderIds->count(),
                'fetched_details' => $openOrders->count(),
                'cutoff_date' => $recentCutoff->toDateString(),
            ]);

            event(new SyncProgressUpdated('fetched-open', "Fetched {$openOrders->count()} open orders", $openOrders->count()));

            // Step 4.5: Fetch order identifiers (tags) for open orders
            event(new SyncProgressUpdated('fetching-identifiers', 'Fetching order identifiers...'));

            $orderIdsForIdentifiers = $openOrders
                ->filter(fn ($order) => $order instanceof \App\DataTransferObjects\LinnworksOrder)
                ->pluck('orderId')
                ->filter()
                ->values()
                ->toArray();

            if (!empty($orderIdsForIdentifiers)) {
                Log::info('SyncOrdersJob: Fetching identifiers for open orders', [
                    'order_count' => count($orderIdsForIdentifiers),
                ]);

                $identifiersByOrderId = $api->getIdentifiersByOrderIds($orderIdsForIdentifiers);

                // Merge identifiers into each order DTO
                $openOrders = $openOrders->map(function ($order) use ($identifiersByOrderId) {
                    if (!$order instanceof \App\DataTransferObjects\LinnworksOrder) {
                        return $order;
                    }

                    $orderId = $order->orderId;
                    if ($orderId && isset($identifiersByOrderId[$orderId])) {
                        // Update the identifiers collection on the DTO
                        $order->identifiers = collect($identifiersByOrderId[$orderId])
                            ->map(fn ($identifier) => [
                                'tag' => $identifier['Tag'] ?? $identifier['tag'] ?? null,
                                'created_at' => isset($identifier['CreatedDateTime']) || isset($identifier['created_at'])
                                    ? Carbon::parse($identifier['CreatedDateTime'] ?? $identifier['created_at'])
                                    : null,
                            ])
                            ->filter(fn ($identifier) => !is_null($identifier['tag']));
                    }

                    return $order;
                });

                $totalIdentifiers = $identifiersByOrderId->flatten(1)->count();
                Log::info('SyncOrdersJob: Order identifiers fetched and merged', [
                    'orders_with_identifiers' => $identifiersByOrderId->count(),
                    'total_identifiers' => $totalIdentifiers,
                ]);
            }

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
