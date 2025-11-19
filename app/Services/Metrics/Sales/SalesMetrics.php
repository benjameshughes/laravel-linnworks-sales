<?php

declare(strict_types=1);

namespace App\Services\Metrics\Sales;

use App\Actions\Calculations\Sales\BuildDailyBreakdown;
use App\Actions\Calculations\Sales\BuildDateRangeForPeriod;
use App\Actions\Calculations\Sales\CalculatePeriodDates;
use App\Factories\Metrics\Sales\SalesFactory;
use App\Repositories\Metrics\Sales\SalesRepository;
use Carbon\Carbon;
use Illuminate\Support\Collection;

final readonly class SalesMetrics
{
    public function __construct(private SalesRepository $salesRepo) {}

    /**
     * Get metrics summary (total revenue, orders, avg order value, items, orders per day)
     */
    public function getMetricsSummary(
        string $period = '7',
        string $channel = 'all',
        ?string $customFrom = null,
        ?string $customTo = null
    ): Collection {
        $dates = (new CalculatePeriodDates)($period, $customFrom, $customTo);
        $orders = $this->salesRepo->getOrdersForPeriod($dates['start'], $dates['end']);

        // Filter by channel if not 'all'
        if ($channel !== 'all') {
            $orders = $orders->where('source', $channel);
        }

        $factory = new SalesFactory($orders);

        return collect([
            'total_revenue' => $factory->totalRevenue(),
            'total_orders' => $factory->totalOrders(),
            'average_order_value' => $factory->averageOrderValue(),
            'total_items' => $factory->totalItemsSold(),
            'orders_per_day' => $dates['days'] > 0 ? $factory->totalOrders() / $dates['days'] : 0,
        ]);
    }

    /**
     * Get top performing channels by revenue
     */
    public function getTopChannels(
        string $period,
        string $channel = 'all',
        int $limit = 6,
        ?string $customFrom = null,
        ?string $customTo = null
    ): Collection {
        $dates = (new CalculatePeriodDates)($period, $customFrom, $customTo);
        $orders = $this->salesRepo->getOrdersForPeriod($dates['start'], $dates['end']);

        // Filter by channel if not 'all'
        if ($channel !== 'all') {
            $orders = $orders->where('source', $channel);
        }

        $factory = new SalesFactory($orders);

        return $factory->topChannels($limit);
    }

    /**
     * Get top performing products by quantity sold
     */
    public function getTopProducts(
        string $period,
        string $channel = 'all',
        int $limit = 10,
        ?string $customFrom = null,
        ?string $customTo = null
    ): Collection {
        $dates = (new CalculatePeriodDates)($period, $customFrom, $customTo);
        $orders = $this->salesRepo->getOrdersForPeriod($dates['start'], $dates['end']);

        // Filter by channel if not 'all'
        if ($channel !== 'all') {
            $orders = $orders->where('source', $channel);
        }

        $factory = new SalesFactory($orders);

        return $factory->topProducts($limit);
    }

    /**
     * Get recent orders (simple passthrough to repository)
     */
    public function getRecentOrders(int $limit = 15): Collection
    {
        return $this->salesRepo->getRecentOrders($limit);
    }

    /**
     * Get daily revenue breakdown for chart visualization
     */
    public function getDailyRevenueData(
        string $period,
        ?string $customFrom = null,
        ?string $customTo = null
    ): Collection {
        $dates = (new CalculatePeriodDates)($period, $customFrom, $customTo);
        $orders = $this->salesRepo->getOrdersForPeriod($dates['start'], $dates['end']);
        $dateRange = (new BuildDateRangeForPeriod)($period, $customFrom, $customTo);

        return (new BuildDailyBreakdown)($orders, $dateRange);
    }

    /**
     * Calculate growth rate compared to previous period
     */
    public function growthRate(int $days): float
    {
        $endDate = Carbon::now();
        $startDate = Carbon::now()->copy()->subDays($days);

        // Current period
        $currentOrders = $this->salesRepo->getOrdersBetweenDates($startDate, $endDate);

        // Previous period
        $previousStartDate = $startDate->copy()->subDays($days);
        $previousEndDate = $endDate->copy()->subDays($days);
        $previousOrders = $this->salesRepo->getOrdersBetweenDates($previousStartDate, $previousEndDate);

        // Create factories and compare
        $currentFactory = new SalesFactory($currentOrders);
        $previousFactory = new SalesFactory($previousOrders);

        return $currentFactory->growthRate($previousFactory);
    }

    /**
     * Get the date range for the given period
     */
    public function getDateRange(
        string $period,
        ?string $customFrom = null,
        ?string $customTo = null
    ): Collection {
        $dates = (new CalculatePeriodDates)($period, $customFrom, $customTo);

        return collect([
            'start' => $dates['start'],
            'end' => $dates['end'],
            'days' => $dates['days'],
        ]);
    }

    /**
     * Get channel distribution data formatted for doughnut chart
     */
    public function getChannelDistributionData(
        string $period,
        string $channel = 'all',
        ?string $customFrom = null,
        ?string $customTo = null
    ): array {
        $topChannels = $this->getTopChannels($period, $channel, 10, $customFrom, $customTo);

        if ($topChannels->isEmpty()) {
            return [
                'labels' => [],
                'datasets' => [
                    [
                        'label' => 'Revenue by Channel',
                        'data' => [],
                        'backgroundColor' => [],
                        'borderWidth' => 2,
                    ],
                ],
            ];
        }

        // Color palette for channels
        $colors = [
            '#3B82F6', // blue
            '#10B981', // green
            '#F59E0B', // amber
            '#EF4444', // red
            '#8B5CF6', // purple
            '#EC4899', // pink
            '#14B8A6', // teal
            '#F97316', // orange
            '#6366F1', // indigo
            '#84CC16', // lime
        ];

        $labels = $topChannels->pluck('channel')->toArray();
        $data = $topChannels->pluck('revenue')->toArray();
        $backgroundColor = array_slice($colors, 0, count($labels));

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Revenue by Channel',
                    'data' => $data,
                    'backgroundColor' => $backgroundColor,
                    'borderWidth' => 2,
                ],
            ],
        ];
    }

    /**
     * Calculate the highest revenue on a period
     */
    public function getBestPerformingDay(
        string $period,
        string $channel = 'all',
        ?string $customFrom = null,
        ?string $customTo = null
    ): Collection|array|null {
        $dates = (new CalculatePeriodDates)($period, $customFrom, $customTo);
        $orders = $this->salesRepo->getOrdersForPeriod($dates['start'], $dates['end']);

        // Filter by channel if not 'all'
        if ($channel !== 'all') {
            $orders = $orders->where('source', $channel);
        }

        // Get daily breakdown
        $dateRange = (new BuildDateRangeForPeriod)($period, $customFrom, $customTo);
        $dailyBreakdown = (new BuildDailyBreakdown)($orders, $dateRange);

        // Find the day with highest revenue
        $bestDay = $dailyBreakdown->sortByDesc('revenue')->first();

        return $bestDay ?: null;
    }
}
