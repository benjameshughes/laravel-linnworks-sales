<?php

namespace App\Services\Linnworks\Contracts;

use App\ValueObjects\Linnworks\ApiResponse;
use Carbon\Carbon;
use Illuminate\Support\Collection;

interface OrdersServiceInterface
{
    /**
     * Get orders with date range and pagination
     */
    public function getOrders(
        int $userId,
        Carbon $from,
        Carbon $to,
        int $page = 1,
        int $entriesPerPage = 200
    ): ApiResponse;

    /**
     * Get order details by ID
     */
    public function getOrderById(int $userId, string $orderId): ApiResponse;

    /**
     * Get all orders in date range
     */
    public function getAllOrders(
        int $userId,
        Carbon $from,
        Carbon $to,
        int $maxOrders = 5000
    ): Collection;

    /**
     * Get order statistics for a date range
     */
    public function getOrderStats(int $userId, Carbon $from, Carbon $to): array;
}
