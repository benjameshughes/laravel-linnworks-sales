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
     * Get all orders with optional filtering
     *
     * @param  int|null  $period  Days to look back (null for all)
     * @param  string|null  $source  Channel/source filter
     * @param  int|null  $status  Status filter (0=open, 1=processed, 2=cancelled)
     */
    public function getAllOrders(?int $period = null, ?string $source = null, ?int $status = null): Collection
    {
        $query = Order::query();

        if ($period !== null) {
            $query->where('received_at', '>=', Carbon::now()->subDays($period));
        }

        if ($source !== null) {
            $query->where('source', $source);
        }

        if ($status !== null) {
            $query->where('status', $status);
        }

        return $query->get();
    }

    /**
     * Recent orders ordered by received_at (most recent first)
     *
     * @params limit<int>
     */
    public function getRecentOrders(int $limit = 50): Collection
    {
        return Order::latest('received_at')->limit($limit)->get();
    }

    /**
     * Get all open orders
     */
    public function getAllOpenOrders(): Collection
    {
        return Order::where('status', 0)->get();
    }

    /**
     * Get all processed orders
     */
    public function getAllProcessedOrders(): Collection
    {
        return Order::where('status', 1)->get();
    }

    /**
     * Get orders between dates
     *
     * @params datetime
     */
    public function getOrdersBetweenDates(Carbon $start, Carbon $end): Collection
    {
        return Order::with('orderItems')->whereBetween('received_at', [$start, $end])->get();
    }

    /**
     * Get orders between a period. 1 day, 7 days, 30 days, etc...
     */
    public function getOrdersForPeriod(Carbon $start, Carbon $end): Collection
    {
        return Order::with('orderItems')->whereBetween('received_at', [$start, $end])->get();
    }

    /**
     * Get best performing day using DB aggregation (memory efficient).
     *
     * @return array{date: string, revenue: float, orders: int}|null
     */
    public function getBestPerformingDayAggregated(Carbon $start, Carbon $end, ?string $channel = null): ?array
    {
        /** @var object{date: string, revenue: float, orders: int}|null $result */
        $result = Order::query()
            ->selectRaw('DATE(received_at) as date, SUM(total_charge) as revenue, COUNT(*) as orders')
            ->whereBetween('received_at', [$start, $end])
            ->when($channel && $channel !== 'all', fn ($q) => $q->where('source', $channel))
            ->groupByRaw('DATE(received_at)')
            ->orderByDesc('revenue')
            ->first();

        if (! $result || $result->revenue <= 0) {
            return null;
        }

        return [
            'date' => $result->date,
            'revenue' => (float) $result->revenue,
            'orders' => (int) $result->orders,
        ];
    }
}
