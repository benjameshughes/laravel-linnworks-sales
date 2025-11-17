<?php

declare(strict_types=1);

namespace App\Repositories\Metrics\Sales;

use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Gets all data related to orders to be used in the Factory
 */
final class SalesRepository
{
    /**
     * Get all orders
     * Probably not best to use this as this will result in a huge amount of data
     */
    public function getAllOrders(): Collection
    {
        return Order::all();
    }

    /**
     * Recent orders
     *
     * @params limit<int>
     */
    public function getRecentOrders(int $limit = 50): Collection
    {
        return Order::latest()->limit($limit)->get();
    }

    /**
     * Get all open orders
     */
    public function getAllOpenOrders(): Collection
    {
        return Order::where('is_processed', 0 | false)->get();
    }

    /**
     * Get all processed orders
     */
    public function getAllProcessedOrders(): Collection
    {
        return Order::where('is_processed', 1 | true)->get();
    }

    /**
     * Get orders between dates
     *
     * @params datetime
     */
    public function getOrdersBetweenDates(Carbon $start, Carbon $end): Collection
    {
        return Order::whereBetween('created_at', [$start, $end])->get();
    }

    /**
     * Get orders between a period. 1 day, 7 days, 30 days, etc...
     */
    public function getOrdersForPeriod(Carbon $start, Carbon $end): Collection
    {
        return Order::whereBetween('created_at', [$start, $end])->get();
    }
}
