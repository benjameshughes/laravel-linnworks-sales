<?php

declare(strict_types=1);

namespace App\Services\Linnworks\Sync\Orders;

use App\Services\Linnworks\Orders\OpenOrdersService;
use App\Services\Linnworks\Orders\OrdersApiService;
use App\Services\Linnworks\Orders\ProcessedOrdersService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Orchestrates order syncing operations
 *
 * This service coordinates fetching orders from various endpoints
 * and preparing them for import. Domain: Order Synchronization.
 */
final readonly class OrderSyncOrchestrator
{
    public function __construct(
        private OpenOrdersService $openOrders,
        private ProcessedOrdersService $processedOrders,
        private OrdersApiService $orders,
    ) {}

    /**
     * Get all open order IDs that need syncing
     */
    public function getOpenOrderIds(int $userId): Collection
    {
        try {
            return $this->openOrders->getOpenOrderIds($userId);
        } catch (\Throwable $e) {
            Log::error('Failed to fetch open order IDs', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);

            return collect();
        }
    }

    /**
     * Get processed orders in date range with progress tracking
     */
    public function getProcessedOrders(
        int $userId,
        Carbon $from,
        Carbon $to,
        array $filters = [],
        int $maxOrders = 10_000,
        ?\Closure $progressCallback = null
    ): Collection {
        try {
            return $this->processedOrders->getAllProcessedOrders(
                $userId,
                $from,
                $to,
                $filters,
                $maxOrders,
                $progressCallback
            );
        } catch (\Throwable $e) {
            Log::error('Failed to fetch processed orders', [
                'user_id' => $userId,
                'from' => $from->toISOString(),
                'to' => $to->toISOString(),
                'error' => $e->getMessage(),
            ]);

            return collect();
        }
    }

    /**
     * Get full order details by IDs (works for both open and processed)
     */
    public function getOrdersByIds(int $userId, array $orderIds): Collection
    {
        if (empty($orderIds)) {
            return collect();
        }

        try {
            $response = $this->orders->getOrdersByIds($userId, $orderIds);

            if ($response->isError()) {
                Log::warning('Failed to fetch orders by IDs', [
                    'user_id' => $userId,
                    'order_count' => count($orderIds),
                    'error' => $response->error,
                ]);

                return collect();
            }

            return $response->getData();
        } catch (\Throwable $e) {
            Log::error('Failed to fetch orders by IDs', [
                'user_id' => $userId,
                'order_count' => count($orderIds),
                'error' => $e->getMessage(),
            ]);

            return collect();
        }
    }

    /**
     * Get order identifiers (tags) for multiple orders
     */
    public function getIdentifiersByOrderIds(int $userId, array $orderIds): Collection
    {
        if (empty($orderIds)) {
            return collect();
        }

        try {
            return $this->openOrders->getIdentifiersByOrderIds($userId, $orderIds);
        } catch (\Throwable $e) {
            Log::error('Failed to fetch order identifiers', [
                'user_id' => $userId,
                'order_count' => count($orderIds),
                'error' => $e->getMessage(),
            ]);

            return collect();
        }
    }
}
