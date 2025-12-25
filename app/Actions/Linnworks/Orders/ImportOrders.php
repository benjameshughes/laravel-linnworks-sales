<?php

declare(strict_types=1);

namespace App\Actions\Linnworks\Orders;

use App\DataTransferObjects\ImportOrdersResult;
use App\DataTransferObjects\LinnworksOrder;
use App\Jobs\SyncProductsFromOrdersJob;
use App\Models\Order;
use App\Services\Performance\PerformanceMonitor;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Centralised importer that persists Linnworks orders without customer data.
 * Aligns with the action-first guidance in AGENTS.md.
 */
final class ImportOrders
{
    public function __construct(
        private readonly PerformanceMonitor $performanceMonitor
    ) {}

    public function handle(iterable $orders, bool $forceUpdate = false): ImportOrdersResult
    {
        return $this->performanceMonitor->measure(
            operation: 'ImportOrders',
            callback: fn () => $this->importOrders($orders, $forceUpdate),
            metadata: ['force_update' => $forceUpdate]
        );
    }

    private function importOrders(iterable $orders, bool $forceUpdate): ImportOrdersResult
    {
        $startTime = microtime(true);
        $peakMemoryBefore = memory_get_peak_usage(true);

        $ordersCollection = $orders instanceof Collection ? $orders : collect($orders);

        $dtoCollection = $ordersCollection
            ->map(function ($order) {
                if ($order instanceof LinnworksOrder) {
                    return $order;
                }

                $payload = is_array($order) ? $order : (array) $order;

                return LinnworksOrder::fromArray($payload);
            })
            ->filter(fn (LinnworksOrder $order) => $order->orderId !== null || $order->orderNumber !== null);

        // Prioritise processed records when duplicates exist.
        $dtoCollection = $dtoCollection
            ->sortByDesc(fn (LinnworksOrder $order) => $order->isProcessed() ? 1 : 0)
            ->unique(fn (LinnworksOrder $order) => $order->orderId ?? 'order-number:'.$order->orderNumber)
            ->values();

        if ($dtoCollection->isEmpty()) {
            Log::info('ImportOrders: no orders to import', [
                'incoming_count' => $ordersCollection instanceof Collection
                    ? $ordersCollection->count()
                    : (is_countable($ordersCollection) ? count($ordersCollection) : null),
            ]);

            return new ImportOrdersResult(
                processed: 0,
                created: 0,
                updated: 0,
                skipped: 0,
                failed: 0,
            );
        }

        Log::info('ImportOrders: Starting import', [
            'total_orders' => $dtoCollection->count(),
            'force_update' => $forceUpdate,
        ]);

        // Performance optimization: Batch load all existing orders upfront to avoid N+1 queries
        $batchLoadStart = microtime(true);
        $existingOrdersMap = $this->loadExistingOrdersBatch($dtoCollection);
        $batchLoadDuration = round(microtime(true) - $batchLoadStart, 2);

        Log::info('ImportOrders: Batch loading completed', [
            'duration_seconds' => $batchLoadDuration,
            'orders_loaded' => $existingOrdersMap->get('by_order_id')->count() + $existingOrdersMap->get('by_order_number')->count(),
        ]);

        $counts = [
            'processed' => 0,
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'failed' => 0,
        ];

        $dtoCollection->chunk(25)->each(function (Collection $chunk) use (&$counts, $forceUpdate, $existingOrdersMap) {
            DB::transaction(function () use ($chunk, &$counts, $forceUpdate, $existingOrdersMap) {
                foreach ($chunk as $linnworksOrder) {
                    assert($linnworksOrder instanceof LinnworksOrder);
                    $counts['processed']++;

                    $orderId = $linnworksOrder->orderId;
                    $orderNumber = $linnworksOrder->orderNumber;

                    // Use pre-loaded map instead of querying DB for each order
                    $existingOrder = $this->getExistingOrder($existingOrdersMap, $orderId, $orderNumber);

                    try {
                        $modelFromDto = Order::fromLinnworksOrder($linnworksOrder);

                        if ($existingOrder) {
                            $existingOrder->fill($modelFromDto->getAttributes());
                            $existingOrder->items = $modelFromDto->items;
                            // Transfer all pending related data
                            $existingOrder->pendingItems = $modelFromDto->pendingItems;
                            $existingOrder->pendingShipping = $modelFromDto->pendingShipping;
                            $existingOrder->pendingNotes = $modelFromDto->pendingNotes;
                            $existingOrder->pendingProperties = $modelFromDto->pendingProperties;
                            $existingOrder->pendingIdentifiers = $modelFromDto->pendingIdentifiers;

                            // DEBUG: Log what pending data we have
                            Log::info('ImportOrders: Pending data transferred', [
                                'order_id' => $orderId,
                                'order_number' => $orderNumber,
                                'has_shipping' => $existingOrder->pendingShipping !== null,
                                'notes_count' => $existingOrder->pendingNotes ? $existingOrder->pendingNotes->count() : 0,
                                'properties_count' => $existingOrder->pendingProperties ? $existingOrder->pendingProperties->count() : 0,
                                'identifiers_count' => $existingOrder->pendingIdentifiers ? $existingOrder->pendingIdentifiers->count() : 0,
                            ]);

                            $shouldPersist = $forceUpdate || $existingOrder->isDirty();

                            if ($shouldPersist) {
                                $existingOrder->save();
                                $counts['updated']++;
                            } else {
                                $counts['skipped']++;
                            }

                            // ALWAYS sync related data (items, shipping, notes, etc.) even if order wasn't updated
                            // This ensures we capture any new notes, properties, or identifier changes from Linnworks
                            // Note: The order MUST have been saved at least once (has an ID) for relationships to work
                            if ($existingOrder->exists) {
                                try {
                                    Log::info('ImportOrders: Calling syncAllRelatedData', [
                                        'order_id' => $orderId,
                                        'order_number' => $orderNumber,
                                    ]);
                                    $existingOrder->syncAllRelatedData();
                                    Log::info('ImportOrders: syncAllRelatedData completed', [
                                        'order_id' => $orderId,
                                        'order_number' => $orderNumber,
                                    ]);
                                } catch (\Throwable $syncException) {
                                    // Log but don't fail the entire import if related data sync fails
                                    Log::warning('Failed to sync related data for order', [
                                        'order_id' => $orderId,
                                        'order_number' => $orderNumber,
                                        'error' => $syncException->getMessage(),
                                        'trace' => $syncException->getTraceAsString(),
                                    ]);
                                }
                            } else {
                                Log::warning('ImportOrders: Order does not exist, cannot sync related data', [
                                    'order_id' => $orderId,
                                    'order_number' => $orderNumber,
                                ]);
                            }

                            continue;
                        }

                        $modelFromDto->save();
                        try {
                            $modelFromDto->syncAllRelatedData();
                        } catch (\Throwable $syncException) {
                            // Log but don't fail the entire import if related data sync fails
                            Log::warning('Failed to sync related data for new order', [
                                'order_id' => $orderId,
                                'order_number' => $orderNumber,
                                'error' => $syncException->getMessage(),
                            ]);
                        }
                        $counts['created']++;
                    } catch (\Throwable $exception) {
                        $counts['failed']++;

                        Log::error('Failed to persist Linnworks order', [
                            'order_id' => $orderId,
                            'order_number' => $orderNumber,
                            'message' => $exception->getMessage(),
                        ]);
                    }
                }
            }, 5);
        });

        $duration = round(microtime(true) - $startTime, 2);
        $peakMemoryAfter = memory_get_peak_usage(true);
        $memoryUsed = $peakMemoryAfter - $peakMemoryBefore;

        Log::info('ImportOrders: Import completed', [
            'total_orders' => $dtoCollection->count(),
            'processed' => $counts['processed'],
            'created' => $counts['created'],
            'updated' => $counts['updated'],
            'skipped' => $counts['skipped'],
            'failed' => $counts['failed'],
            'duration_seconds' => $duration,
            'orders_per_second' => $counts['processed'] > 0 ? round($counts['processed'] / $duration, 2) : 0,
            'memory_used_mb' => round($memoryUsed / 1024 / 1024, 2),
            'peak_memory_mb' => round($peakMemoryAfter / 1024 / 1024, 2),
        ]);

        // Dispatch product sync job for new/updated orders
        $this->dispatchProductSync($dtoCollection);

        return new ImportOrdersResult(
            processed: $counts['processed'],
            created: $counts['created'],
            updated: $counts['updated'],
            skipped: $counts['skipped'],
            failed: $counts['failed'],
        );
    }

    /**
     * Dispatch a job to sync product details for order items.
     *
     * Collects unique stock_item_ids from order items and dispatches
     * SyncProductsFromOrdersJob to fetch product details from Linnworks.
     */
    private function dispatchProductSync(Collection $dtoCollection): void
    {
        // Collect unique stock_item_ids from all order items
        $stockItemIds = $dtoCollection
            ->flatMap(fn (LinnworksOrder $order) => $order->items)
            ->pluck('stockItemId')
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        if (empty($stockItemIds)) {
            Log::debug('ImportOrders: No stock item IDs to sync');

            return;
        }

        Log::info('ImportOrders: Dispatching product sync job', [
            'stock_item_ids_count' => count($stockItemIds),
        ]);

        SyncProductsFromOrdersJob::dispatch($stockItemIds);
    }

    /**
     * Batch load all existing orders to avoid N+1 queries
     *
     * Loads orders by Linnworks IDs and order numbers in bulk, then creates
     * a lookup map for O(1) access during import loop.
     */
    private function loadExistingOrdersBatch(Collection $dtoCollection): Collection
    {
        $orderIds = $dtoCollection
            ->pluck('orderId')
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        $orderNumbers = $dtoCollection
            ->pluck('orderNumber')
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        Log::info('ImportOrders: Batch loading existing orders', [
            'order_ids_count' => count($orderIds),
            'order_numbers_count' => count($orderNumbers),
        ]);

        // Load all existing orders in one or two queries
        $existingOrders = collect();

        if (! empty($orderIds)) {
            $byLinnworksId = Order::query()
                ->where(function ($query) use ($orderIds) {
                    $query->whereIn('linnworks_order_id', $orderIds)
                        ->orWhereIn('order_id', $orderIds);
                })
                ->get();
            $existingOrders = $existingOrders->merge($byLinnworksId);
        }

        if (! empty($orderNumbers)) {
            $byOrderNumber = Order::whereIn('number', $orderNumbers)->get();
            $existingOrders = $existingOrders->merge($byOrderNumber);
        }

        // Create lookup maps for fast O(1) access
        $orderIdMap = $existingOrders
            ->filter(fn ($order) => $order->linnworks_order_id || $order->order_id)
            ->keyBy(fn ($order) => $order->linnworks_order_id ?? $order->order_id);

        $orderNumberMap = $existingOrders
            ->filter(fn ($order) => $order->number)
            ->keyBy('number');

        Log::info('ImportOrders: Existing orders loaded', [
            'total_loaded' => $existingOrders->unique('id')->count(),
            'by_order_id' => $orderIdMap->count(),
            'by_order_number' => $orderNumberMap->count(),
        ]);

        return collect([
            'by_order_id' => $orderIdMap,
            'by_order_number' => $orderNumberMap,
        ]);
    }

    /**
     * Get existing order from pre-loaded map (O(1) lookup)
     */
    private function getExistingOrder(Collection $existingOrdersMap, ?string $orderId, ?int $orderNumber): ?Order
    {
        $orderIdMap = $existingOrdersMap->get('by_order_id');
        $orderNumberMap = $existingOrdersMap->get('by_order_number');

        if ($orderId && $orderIdMap->has($orderId)) {
            return $orderIdMap->get($orderId);
        }

        if ($orderNumber && $orderNumberMap->has($orderNumber)) {
            return $orderNumberMap->get($orderNumber);
        }

        return null;
    }

    /**
     * @deprecated Use loadExistingOrdersBatch() and getExistingOrder() instead
     */
    private function findExistingOrder(?string $orderId, ?int $orderNumber): ?Order
    {
        $existingOrder = null;

        if ($orderId) {
            $existingOrder = Order::query()
                ->where(function ($query) use ($orderId) {
                    $query->where('linnworks_order_id', $orderId)
                        ->orWhere('order_id', $orderId);
                })
                ->first();
        }

        if (! $existingOrder && $orderNumber) {
            $existingOrder = Order::where('number', $orderNumber)->first();
        }

        return $existingOrder;
    }
}
