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
        $dates = new CalculatePeriodDates($period, $customFrom, $customTo);
        $orders = $this->salesRepo->getOrdersForPeriod($dates['start'], $dates['end']);

        // Filter by channel if not 'all'
        if ($channel !== 'all') {
            $orders = $orders->where('channel_name', $channel);
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
        $dates = new CalculatePeriodDates($period, $customFrom, $customTo);
        $orders = $this->salesRepo->getOrdersForPeriod($dates['start'], $dates['end']);

        // Filter by channel if not 'all'
        if ($channel !== 'all') {
            $orders = $orders->where('channel_name', $channel);
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
        $dates = new CalculatePeriodDates($period, $customFrom, $customTo);
        $orders = $this->salesRepo->getOrdersForPeriod($dates['start'], $dates['end']);

        // Filter by channel if not 'all'
        if ($channel !== 'all') {
            $orders = $orders->where('channel_name', $channel);
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
        $dates = new CalculatePeriodDates($period, $customFrom, $customTo);
        $orders = $this->salesRepo->getOrdersForPeriod($dates['start'], $dates['end']);
        $dateRange = new BuildDateRangeForPeriod($period, $customFrom, $customTo);

        return new BuildDailyBreakdown($orders, $dateRange);
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
     * Calculate the highest revenue on a period
     */
    public function getBestPerformingDays(int $days): float
    {
        // TODO: Implement this at some point haha
        return 9999.99;
    }
}
