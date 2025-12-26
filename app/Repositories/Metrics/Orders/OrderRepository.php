<?php

declare(strict_types=1);

namespace App\Repositories\Metrics\Orders;

use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Data access layer for order analytics.
 *
 * Provides methods to fetch orders and aggregated metrics
 * for use in the OrderService calculations.
 */
final class OrderRepository
{
    /**
     * Get order metrics aggregation for a period.
     */
    public function getOrderMetrics(Carbon $start, Carbon $end, ?string $channel = null, ?string $status = null): object
    {
        $query = DB::table('orders')
            ->whereBetween('received_at', [$start, $end])
            ->where('is_cancelled', false);

        if ($channel && $channel !== 'all') {
            $query->where('source', $channel);
        }

        if ($status === 'open') {
            $query->where('status', 0);
        } elseif ($status === 'paid') {
            $query->where('is_paid', true);
        }

        return $query->select(
            DB::raw('COUNT(*) as total_orders'),
            DB::raw('COALESCE(SUM(total_charge), 0) as total_revenue'),
            DB::raw('COALESCE(AVG(total_charge), 0) as avg_order_value'),
            DB::raw('COALESCE(SUM(profit_margin), 0) as total_profit'),
            DB::raw('COALESCE(SUM(num_items), 0) as total_items'),
            DB::raw('COALESCE(SUM(postage_cost), 0) as total_postage'),
            DB::raw('COALESCE(SUM(tax), 0) as total_tax'),
            DB::raw('COUNT(DISTINCT source) as unique_channels'),
            DB::raw('SUM(CASE WHEN is_paid = 1 THEN 1 ELSE 0 END) as paid_orders'),
            DB::raw('SUM(CASE WHEN status = 0 THEN 1 ELSE 0 END) as open_orders')
        )->first() ?? (object) [
            'total_orders' => 0,
            'total_revenue' => 0,
            'avg_order_value' => 0,
            'total_profit' => 0,
            'total_items' => 0,
            'total_postage' => 0,
            'total_tax' => 0,
            'unique_channels' => 0,
            'paid_orders' => 0,
            'open_orders' => 0,
        ];
    }

    /**
     * Get daily order aggregation for charts.
     */
    public function getOrderDailySales(Carbon $start, Carbon $end, ?string $channel = null): Collection
    {
        $query = DB::table('orders')
            ->whereBetween('received_at', [$start, $end])
            ->where('is_cancelled', false);

        if ($channel && $channel !== 'all') {
            $query->where('source', $channel);
        }

        return $query->select(
            DB::raw('DATE(received_at) as date'),
            DB::raw('COUNT(*) as order_count'),
            DB::raw('COALESCE(SUM(total_charge), 0) as revenue'),
            DB::raw('COALESCE(AVG(total_charge), 0) as avg_value'),
            DB::raw('COALESCE(SUM(num_items), 0) as items_sold')
        )
            ->groupByRaw('DATE(received_at)')
            ->orderBy('date')
            ->get();
    }

    /**
     * Get top customers by order count and value.
     */
    public function getTopCustomers(Carbon $start, Carbon $end, int $limit = 10): Collection
    {
        return DB::table('orders')
            ->whereBetween('received_at', [$start, $end])
            ->where('is_cancelled', false)
            ->whereNotNull('channel_reference_number')
            ->where('channel_reference_number', '!=', '')
            ->select(
                'source',
                'channel_reference_number',
                DB::raw('COUNT(*) as order_count'),
                DB::raw('COALESCE(SUM(total_charge), 0) as total_spent'),
                DB::raw('COALESCE(AVG(total_charge), 0) as avg_order_value'),
                DB::raw('MAX(received_at) as last_order_date'),
                DB::raw('MIN(received_at) as first_order_date')
            )
            ->groupBy('source', 'channel_reference_number')
            ->orderByDesc('total_spent')
            ->limit($limit)
            ->get();
    }

    /**
     * Search orders by number or reference.
     */
    public function searchOrders(string $query, Carbon $start, Carbon $end, int $limit = 50): Collection
    {
        return Order::query()
            ->whereBetween('received_at', [$start, $end])
            ->where(function ($q) use ($query) {
                $q->where('number', 'like', "%{$query}%")
                    ->orWhere('channel_reference_number', 'like', "%{$query}%")
                    ->orWhere('external_reference_num', 'like', "%{$query}%");
            })
            ->orderByDesc('received_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Get orders with profit analysis.
     */
    public function getOrdersWithProfit(Carbon $start, Carbon $end, int $limit = 50): Collection
    {
        return DB::table('orders')
            ->join('order_items', 'orders.id', '=', 'order_items.order_id')
            ->whereBetween('orders.received_at', [$start, $end])
            ->where('orders.is_cancelled', false)
            ->select(
                'orders.id',
                'orders.number',
                'orders.source',
                'orders.total_charge',
                'orders.received_at',
                DB::raw('SUM(order_items.unit_cost * order_items.quantity) as total_cost'),
                DB::raw('orders.total_charge - SUM(order_items.unit_cost * order_items.quantity) as profit'),
                DB::raw('CASE WHEN orders.total_charge > 0 THEN ((orders.total_charge - SUM(order_items.unit_cost * order_items.quantity)) / orders.total_charge) * 100 ELSE 0 END as margin_percentage')
            )
            ->groupBy('orders.id', 'orders.number', 'orders.source', 'orders.total_charge', 'orders.received_at')
            ->orderByDesc('profit')
            ->limit($limit)
            ->get();
    }

    /**
     * Get comparison metrics for previous period.
     */
    public function getComparisonMetrics(Carbon $currentStart, Carbon $currentEnd, Carbon $previousStart, Carbon $previousEnd, ?string $channel = null): object
    {
        $current = $this->getOrderMetrics($currentStart, $currentEnd, $channel);
        $previous = $this->getOrderMetrics($previousStart, $previousEnd, $channel);

        return (object) [
            'current' => $current,
            'previous' => $previous,
            'revenue_change' => $this->calculatePercentageChange((float) $previous->total_revenue, (float) $current->total_revenue),
            'orders_change' => $this->calculatePercentageChange((float) $previous->total_orders, (float) $current->total_orders),
            'avg_value_change' => $this->calculatePercentageChange((float) $previous->avg_order_value, (float) $current->avg_order_value),
            'profit_change' => $this->calculatePercentageChange((float) $previous->total_profit, (float) $current->total_profit),
        ];
    }

    private function calculatePercentageChange(float $previous, float $current): float
    {
        if ($previous == 0) {
            return $current > 0 ? 100.0 : 0.0;
        }

        return round((($current - $previous) / $previous) * 100, 2);
    }

    /**
     * Get paginated orders for table display.
     */
    public function getPaginatedOrders(
        Carbon $start,
        Carbon $end,
        ?string $channel = null,
        ?string $status = null,
        ?string $search = null,
        string $sortBy = 'received_at',
        string $sortDirection = 'desc',
        int $perPage = 25
    ): LengthAwarePaginator {
        $query = Order::query()
            ->with('orderItems:id,order_id,sku,item_title,quantity,line_total')
            ->whereBetween('received_at', [$start, $end])
            ->where('is_cancelled', false);

        if ($channel && $channel !== 'all') {
            $query->where('source', $channel);
        }

        if ($status === 'open') {
            $query->where('status', 0);
        } elseif ($status === 'paid') {
            $query->where('is_paid', true);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('number', 'like', "%{$search}%")
                    ->orWhere('channel_reference_number', 'like', "%{$search}%");
            });
        }

        $allowedSorts = ['received_at', 'total_charge', 'num_items', 'source'];
        $sortBy = in_array($sortBy, $allowedSorts) ? $sortBy : 'received_at';

        return $query
            ->orderBy($sortBy, $sortDirection)
            ->paginate($perPage);
    }

    /**
     * Get channel performance breakdown.
     */
    public function getChannelBreakdown(Carbon $start, Carbon $end): Collection
    {
        return DB::table('orders')
            ->whereBetween('received_at', [$start, $end])
            ->where('is_cancelled', false)
            ->select(
                'source as channel',
                DB::raw('COUNT(*) as order_count'),
                DB::raw('COALESCE(SUM(total_charge), 0) as total_revenue'),
                DB::raw('COALESCE(AVG(total_charge), 0) as avg_order_value'),
                DB::raw('COALESCE(SUM(num_items), 0) as total_items')
            )
            ->groupBy('source')
            ->orderByDesc('total_revenue')
            ->get();
    }

    /**
     * Get order by number.
     */
    public function getOrderByNumber(string $number): ?Order
    {
        return Order::query()
            ->with(['orderItems', 'shipping', 'notes'])
            ->where('number', $number)
            ->first();
    }

    /**
     * Get related orders (same channel reference or source).
     */
    public function getRelatedOrders(Order $order, int $limit = 5): Collection
    {
        return Order::query()
            ->where('id', '!=', $order->id)
            ->where(function ($q) use ($order) {
                if ($order->channel_reference_number) {
                    $q->where('channel_reference_number', $order->channel_reference_number);
                }
                $q->orWhere(function ($subQ) use ($order) {
                    $subQ->where('source', $order->source)
                        ->where('received_at', '>=', $order->received_at?->subDays(30))
                        ->where('received_at', '<=', $order->received_at?->addDays(30));
                });
            })
            ->orderByDesc('received_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Get unique channels for filter dropdown.
     */
    public function getUniqueChannels(): Collection
    {
        return Order::query()
            ->whereNotNull('source')
            ->distinct()
            ->pluck('source')
            ->filter()
            ->sort()
            ->values();
    }
}
