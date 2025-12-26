<?php

declare(strict_types=1);

namespace App\Services\Metrics\Orders;

use App\Actions\Calculations\Sales\CalculatePeriodDates;
use App\Models\Order;
use App\Repositories\Metrics\Orders\OrderRepository;
use App\Services\OrderBadgeService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Orchestration layer for order analytics.
 *
 * Ties together the OrderRepository (data access) and
 * calculations to provide order metrics.
 */
final readonly class OrderService
{
    public function __construct(
        private OrderRepository $orderRepo,
        private OrderBadgeService $badgeService,
    ) {}

    /**
     * Get order metrics for a period.
     */
    public function getOrderMetrics(
        string $period = '7',
        ?string $channel = null,
        ?string $status = null,
        ?string $customFrom = null,
        ?string $customTo = null
    ): Collection {
        $dates = (new CalculatePeriodDates)($period, $customFrom, $customTo);

        $metrics = $this->orderRepo->getOrderMetrics(
            $dates['start'],
            $dates['end'],
            $channel,
            $status
        );

        return collect([
            'total_orders' => (int) $metrics->total_orders,
            'total_revenue' => (float) $metrics->total_revenue,
            'avg_order_value' => (float) $metrics->avg_order_value,
            'total_profit' => (float) $metrics->total_profit,
            'total_items' => (int) $metrics->total_items,
            'total_postage' => (float) $metrics->total_postage,
            'total_tax' => (float) $metrics->total_tax,
            'unique_channels' => (int) $metrics->unique_channels,
            'paid_orders' => (int) $metrics->paid_orders,
            'open_orders' => (int) $metrics->open_orders,
            'period_days' => $dates['start']->diffInDays($dates['end']) + 1,
        ]);
    }

    /**
     * Get daily chart data for orders.
     */
    public function getDailyChartData(
        string $period = '7',
        ?string $channel = null,
        ?string $customFrom = null,
        ?string $customTo = null
    ): Collection {
        $dates = (new CalculatePeriodDates)($period, $customFrom, $customTo);

        return $this->orderRepo->getOrderDailySales(
            $dates['start'],
            $dates['end'],
            $channel
        )->map(fn ($row) => [
            'date' => $row->date,
            'order_count' => (int) $row->order_count,
            'revenue' => (float) $row->revenue,
            'avg_value' => (float) $row->avg_value,
            'items_sold' => (int) $row->items_sold,
        ]);
    }

    /**
     * Get top customers.
     */
    public function getTopCustomers(
        string $period = '7',
        int $limit = 10,
        ?string $customFrom = null,
        ?string $customTo = null
    ): Collection {
        $dates = (new CalculatePeriodDates)($period, $customFrom, $customTo);

        return $this->orderRepo->getTopCustomers(
            $dates['start'],
            $dates['end'],
            $limit
        )->map(fn ($row) => collect([
            'channel' => $row->source,
            'reference' => $row->channel_reference_number,
            'order_count' => (int) $row->order_count,
            'total_spent' => (float) $row->total_spent,
            'avg_order_value' => (float) $row->avg_order_value,
            'last_order_date' => $row->last_order_date,
            'first_order_date' => $row->first_order_date,
        ]));
    }

    /**
     * Get comparison metrics with previous period.
     */
    public function getComparisonMetrics(
        string $period = '7',
        ?string $channel = null,
        ?string $customFrom = null,
        ?string $customTo = null
    ): Collection {
        $dates = (new CalculatePeriodDates)($period, $customFrom, $customTo);
        $periodDays = $dates['start']->diffInDays($dates['end']) + 1;

        // Calculate previous period dates
        $previousEnd = $dates['start']->copy()->subDay();
        $previousStart = $previousEnd->copy()->subDays($periodDays - 1);

        $comparison = $this->orderRepo->getComparisonMetrics(
            $dates['start'],
            $dates['end'],
            $previousStart,
            $previousEnd,
            $channel
        );

        return collect([
            'current' => [
                'total_orders' => (int) $comparison->current->total_orders,
                'total_revenue' => (float) $comparison->current->total_revenue,
                'avg_order_value' => (float) $comparison->current->avg_order_value,
                'total_profit' => (float) $comparison->current->total_profit,
            ],
            'previous' => [
                'total_orders' => (int) $comparison->previous->total_orders,
                'total_revenue' => (float) $comparison->previous->total_revenue,
                'avg_order_value' => (float) $comparison->previous->avg_order_value,
                'total_profit' => (float) $comparison->previous->total_profit,
            ],
            'changes' => [
                'revenue' => $comparison->revenue_change,
                'orders' => $comparison->orders_change,
                'avg_value' => $comparison->avg_value_change,
                'profit' => $comparison->profit_change,
            ],
        ]);
    }

    /**
     * Get paginated orders for table.
     */
    public function getPaginatedOrders(
        string $period = '7',
        ?string $channel = null,
        ?string $status = null,
        ?string $search = null,
        string $sortBy = 'received_at',
        string $sortDirection = 'desc',
        int $perPage = 25,
        ?string $customFrom = null,
        ?string $customTo = null
    ): LengthAwarePaginator {
        $dates = (new CalculatePeriodDates)($period, $customFrom, $customTo);

        return $this->orderRepo->getPaginatedOrders(
            $dates['start'],
            $dates['end'],
            $channel,
            $status,
            $search,
            $sortBy,
            $sortDirection,
            $perPage
        );
    }

    /**
     * Search orders.
     */
    public function searchOrders(
        string $query,
        string $period = '30',
        ?string $customFrom = null,
        ?string $customTo = null
    ): Collection {
        $dates = (new CalculatePeriodDates)($period, $customFrom, $customTo);

        return $this->orderRepo->searchOrders(
            $query,
            $dates['start'],
            $dates['end']
        );
    }

    /**
     * Get profit analysis for orders.
     */
    public function getProfitAnalysis(
        string $period = '7',
        int $limit = 50,
        ?string $customFrom = null,
        ?string $customTo = null
    ): Collection {
        $dates = (new CalculatePeriodDates)($period, $customFrom, $customTo);

        return $this->orderRepo->getOrdersWithProfit(
            $dates['start'],
            $dates['end'],
            $limit
        )->map(fn ($row) => collect([
            'id' => $row->id,
            'number' => $row->number,
            'source' => $row->source,
            'total_charge' => (float) $row->total_charge,
            'total_cost' => (float) $row->total_cost,
            'profit' => (float) $row->profit,
            'margin_percentage' => round((float) $row->margin_percentage, 2),
            'received_at' => $row->received_at,
        ]));
    }

    /**
     * Get channel breakdown.
     */
    public function getChannelBreakdown(
        string $period = '7',
        ?string $customFrom = null,
        ?string $customTo = null
    ): Collection {
        $dates = (new CalculatePeriodDates)($period, $customFrom, $customTo);

        return $this->orderRepo->getChannelBreakdown(
            $dates['start'],
            $dates['end']
        )->map(fn ($row) => collect([
            'channel' => $row->channel ?? 'Unknown',
            'order_count' => (int) $row->order_count,
            'total_revenue' => (float) $row->total_revenue,
            'avg_order_value' => (float) $row->avg_order_value,
            'total_items' => (int) $row->total_items,
        ]));
    }

    /**
     * Get single order detail.
     */
    public function getOrderDetail(string $orderNumber, int $period = 30): ?Collection
    {
        $order = $this->orderRepo->getOrderByNumber($orderNumber);

        if (! $order) {
            return null;
        }

        $badges = $this->badgeService->getOrderBadges($order, $period);
        $relatedOrders = $this->orderRepo->getRelatedOrders($order);

        // Calculate profit from items
        $order->loadMissing('orderItems');
        $totalCost = $order->orderItems->sum(fn ($item) => ($item->unit_cost ?? 0) * $item->quantity);
        $totalRevenue = $order->orderItems->sum('line_total');
        $profit = $totalRevenue - $totalCost;
        $margin = $totalRevenue > 0 ? ($profit / $totalRevenue) * 100 : 0;

        return collect([
            'order' => $order,
            'badges' => $badges->map(fn ($badge) => $badge->toArray()),
            'related_orders' => $relatedOrders,
            'profit_analysis' => [
                'total_revenue' => (float) $totalRevenue,
                'total_cost' => (float) $totalCost,
                'profit' => (float) $profit,
                'margin_percentage' => round($margin, 2),
            ],
            'timeline' => $this->buildOrderTimeline($order),
        ]);
    }

    /**
     * Build order timeline.
     */
    private function buildOrderTimeline(Order $order): Collection
    {
        $timeline = collect();

        if ($order->received_at) {
            $timeline->push([
                'event' => 'Order Received',
                'date' => $order->received_at->toISOString(),
                'formatted_date' => $order->received_at->format('M j, Y H:i'),
                'icon' => 'inbox',
                'color' => 'blue',
            ]);
        }

        if ($order->paid_at) {
            $timeline->push([
                'event' => 'Payment Received',
                'date' => $order->paid_at->toISOString(),
                'formatted_date' => $order->paid_at->format('M j, Y H:i'),
                'icon' => 'credit-card',
                'color' => 'green',
            ]);
        }

        if ($order->processed_at) {
            $timeline->push([
                'event' => 'Order Processed',
                'date' => $order->processed_at->toISOString(),
                'formatted_date' => $order->processed_at->format('M j, Y H:i'),
                'icon' => 'check-circle',
                'color' => 'emerald',
            ]);
        }

        return $timeline->sortBy('date')->values();
    }

    /**
     * Get unique channels for filter dropdown.
     */
    public function getUniqueChannels(): Collection
    {
        return $this->orderRepo->getUniqueChannels();
    }
}
