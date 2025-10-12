<?php

declare(strict_types=1);

namespace App\Actions\Linnworks\Orders;

use App\DataTransferObjects\ImportOrdersResult;
use App\DataTransferObjects\LinnworksOrder;
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
            ->unique(fn (LinnworksOrder $order) => $order->orderId ?? 'order-number:' . $order->orderNumber)
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

        $counts = [
            'processed' => 0,
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'failed' => 0,
        ];

        $dtoCollection->chunk(25)->each(function (Collection $chunk) use (&$counts, $forceUpdate) {
            DB::transaction(function () use ($chunk, &$counts, $forceUpdate) {
                foreach ($chunk as $linnworksOrder) {
                    assert($linnworksOrder instanceof LinnworksOrder);
                    $counts['processed']++;

                    $orderId = $linnworksOrder->orderId;
                    $orderNumber = $linnworksOrder->orderNumber;

                    $existingOrder = $this->findExistingOrder($orderId, $orderNumber);

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

        return new ImportOrdersResult(
            processed: $counts['processed'],
            created: $counts['created'],
            updated: $counts['updated'],
            skipped: $counts['skipped'],
            failed: $counts['failed'],
        );
    }

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

        if (!$existingOrder && $orderNumber) {
            $existingOrder = Order::where('order_number', $orderNumber)->first();
        }

        return $existingOrder;
    }
}
