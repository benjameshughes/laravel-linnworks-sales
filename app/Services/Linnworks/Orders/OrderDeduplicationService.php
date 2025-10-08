<?php

declare(strict_types=1);

namespace App\Services\Linnworks\Orders;

use App\DataTransferObjects\LinnworksOrder;
use App\Models\Order;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Service for deduplicating orders before import
 */
class OrderDeduplicationService
{
    /**
     * Deduplicate a collection of orders
     */
    public function deduplicate(Collection $orders): Collection
    {
        $startCount = $orders->count();

        // Step 1: Remove duplicates within the collection itself
        $uniqueOrders = $this->deduplicateCollection($orders);

        // Step 2: Filter out orders that already exist in database
        $newOrders = $this->filterExistingOrders($uniqueOrders);

        $removedCount = $startCount - $newOrders->count();

        if ($removedCount > 0) {
            Log::info('Order deduplication completed', [
                'initial_count' => $startCount,
                'final_count' => $newOrders->count(),
                'duplicates_removed' => $removedCount,
            ]);
        }

        return $newOrders;
    }

    /**
     * Deduplicate within collection (in-memory)
     */
    private function deduplicateCollection(Collection $orders): Collection
    {
        // Prioritize processed orders over open orders
        return $orders
            ->sortByDesc(fn (LinnworksOrder $order) => $order->isProcessed() ? 1 : 0)
            ->unique(fn (LinnworksOrder $order) => $this->getOrderKey($order))
            ->values();
    }

    /**
     * Filter orders that already exist in database
     */
    private function filterExistingOrders(Collection $orders): Collection
    {
        // Get all order IDs and numbers from the collection
        $orderIds = $orders
            ->pluck('orderId')
            ->filter()
            ->unique()
            ->toArray();

        $orderNumbers = $orders
            ->pluck('orderNumber')
            ->filter()
            ->unique()
            ->toArray();

        if (empty($orderIds) && empty($orderNumbers)) {
            return $orders;
        }

        // Query database for existing orders
        $existingOrders = $this->findExistingOrders($orderIds, $orderNumbers);

        // Create a lookup set for fast checking
        $existingKeys = $existingOrders->map(fn (Order $order) => [
            'linnworks_id' => $order->linnworks_order_id,
            'order_id' => $order->order_id,
            'order_number' => $order->order_number,
        ])->flatMap(function ($order) {
            $keys = [];
            if ($order['linnworks_id']) {
                $keys[] = 'id:' . $order['linnworks_id'];
            }
            if ($order['order_id']) {
                $keys[] = 'id:' . $order['order_id'];
            }
            if ($order['order_number']) {
                $keys[] = 'num:' . $order['order_number'];
            }
            return $keys;
        })->flip();

        // Filter out orders that exist in database
        return $orders->filter(function (LinnworksOrder $order) use ($existingKeys) {
            $key = $this->getOrderKey($order);

            // Also check alternative keys
            $alternativeKeys = [];
            if ($order->orderId) {
                $alternativeKeys[] = 'id:' . $order->orderId;
            }
            if ($order->orderNumber) {
                $alternativeKeys[] = 'num:' . $order->orderNumber;
            }

            foreach ($alternativeKeys as $altKey) {
                if ($existingKeys->has($altKey)) {
                    return false; // Order exists, filter it out
                }
            }

            return true; // Order doesn't exist, keep it
        })->values();
    }

    /**
     * Find existing orders in database
     */
    private function findExistingOrders(array $orderIds, array $orderNumbers): Collection
    {
        $query = Order::query();

        // Build query for IDs
        if (!empty($orderIds)) {
            $query->where(function ($q) use ($orderIds) {
                $q->whereIn('linnworks_order_id', $orderIds)
                    ->orWhereIn('order_id', $orderIds);
            });
        }

        // Add query for order numbers
        if (!empty($orderNumbers)) {
            if (!empty($orderIds)) {
                $query->orWhereIn('order_number', $orderNumbers);
            } else {
                $query->whereIn('order_number', $orderNumbers);
            }
        }

        return $query->get(['id', 'linnworks_order_id', 'order_id', 'order_number']);
    }

    /**
     * Get unique key for an order
     */
    private function getOrderKey(LinnworksOrder $order): string
    {
        // Prefer order ID, fall back to order number
        if ($order->orderId) {
            return 'id:' . $order->orderId;
        }

        if ($order->orderNumber) {
            return 'num:' . $order->orderNumber;
        }

        // Fallback: use combination of channel + reference
        return 'ref:' . ($order->channelName ?? 'unknown') . ':' . ($order->channelReferenceNumber ?? uniqid());
    }

    /**
     * Get deduplication stats for a collection
     */
    public function getDeduplicationStats(Collection $original, Collection $deduplicated): array
    {
        $originalCount = $original->count();
        $deduplicatedCount = $deduplicated->count();
        $removedCount = $originalCount - $deduplicatedCount;

        return [
            'original_count' => $originalCount,
            'deduplicated_count' => $deduplicatedCount,
            'duplicates_removed' => $removedCount,
            'duplicate_rate' => $originalCount > 0 ? round(($removedCount / $originalCount) * 100, 2) : 0,
        ];
    }

    /**
     * Check if order exists in database
     */
    public function orderExists(LinnworksOrder $order): bool
    {
        $query = Order::query();

        if ($order->orderId) {
            $query->where(function ($q) use ($order) {
                $q->where('linnworks_order_id', $order->orderId)
                    ->orWhere('order_id', $order->orderId);
            });

            if ($query->exists()) {
                return true;
            }
        }

        if ($order->orderNumber) {
            if (Order::where('order_number', $order->orderNumber)->exists()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Find duplicate orders in collection
     */
    public function findDuplicates(Collection $orders): Collection
    {
        $seen = [];
        $duplicates = collect();

        foreach ($orders as $order) {
            $key = $this->getOrderKey($order);

            if (isset($seen[$key])) {
                $duplicates->push([
                    'key' => $key,
                    'original' => $seen[$key],
                    'duplicate' => $order,
                ]);
            } else {
                $seen[$key] = $order;
            }
        }

        return $duplicates;
    }
}
