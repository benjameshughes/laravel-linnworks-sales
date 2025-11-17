<?php

declare(strict_types=1);

namespace App\Services\Metrics\Sales;

use App\Factories\Metrics\Sales\SalesFactory;
use App\Repositories\Metrics\Sales\SalesRepository;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
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
        $dates = $this->calculateDates($period, $customFrom, $customTo);
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
        $dates = $this->calculateDates($period, $customFrom, $customTo);
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
        $dates = $this->calculateDates($period, $customFrom, $customTo);
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
        $dates = $this->calculateDates($period, $customFrom, $customTo);
        $orders = $this->salesRepo->getOrdersForPeriod($dates['start'], $dates['end']);

        // Build date range for the period
        if ($period === 'custom' && $customFrom && $customTo) {
            $start = Carbon::parse($customFrom)->startOfDay();
            $end = Carbon::parse($customTo)->startOfDay();
            $dateRange = collect(CarbonPeriod::create($start, '1 day', $end));
        } elseif ($period === '0') {
            // Today - return 3 points to center the bar in charts
            $today = Carbon::today();
            $dateRange = collect([
                $today->copy()->subDay(),
                $today,
                $today->copy()->addDay(),
            ]);
        } elseif ($period === '1') {
            // Yesterday - return 3 points to center the bar in charts
            $yesterday = Carbon::yesterday();
            $dateRange = collect([
                $yesterday->copy()->subDay(),
                $yesterday,
                $yesterday->copy()->addDay(),
            ]);
        } else {
            // Last N days
            $days = (int) max(1, $period);
            $dateRange = collect(range($days - 1, 0))
                ->map(fn (int $daysAgo) => Carbon::now()->subDays($daysAgo));
        }

        // Build daily breakdown
        return $this->buildDailyBreakdown($orders, $dateRange, $period);
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
        // Implement this at some point haha
        return 9999.99;
    }

    /**
     * Calculate start/end dates and days for a given period
     */
    private function calculateDates(string $period, ?string $customFrom, ?string $customTo): array
    {
        if ($period === 'custom' && $customFrom && $customTo) {
            $start = Carbon::parse($customFrom)->startOfDay();
            $end = Carbon::parse($customTo)->endOfDay();
            $days = max(1, $start->diffInDays($end) + 1);

            return ['start' => $start, 'end' => $end, 'days' => $days];
        }

        if ($period === '1') {
            // Yesterday
            $start = Carbon::yesterday()->startOfDay();
            $end = Carbon::yesterday()->endOfDay();

            return ['start' => $start, 'end' => $end, 'days' => 1];
        }

        if ($period === '0') {
            // Today
            $start = Carbon::today()->startOfDay();
            $end = Carbon::now();

            return ['start' => $start, 'end' => $end, 'days' => 1];
        }

        // Last N days (e.g., "7", "30", "90")
        $days = max(1, (int) $period);
        $end = Carbon::now();
        $start = Carbon::now()->subDays($days)->startOfDay();

        return ['start' => $start, 'end' => $end, 'days' => $days];
    }

    /**
     * Build daily breakdown for chart data
     */
    private function buildDailyBreakdown(Collection $orders, Collection $dateRange, string $period): Collection
    {
        // Initialize empty data structure for each date
        $dailyData = [];

        foreach ($dateRange as $date) {
            $dailyData[$date->format('Y-m-d')] = [
                'date' => $date->format('M j, Y'),
                'iso_date' => $date->format('Y-m-d'),
                'day' => $date->format('D'),
                'revenue' => 0.0,
                'orders' => 0,
                'items' => 0,
            ];
        }

        // Single pass through orders - bucket by date
        foreach ($orders as $order) {
            if (! $order->received_date) {
                continue;
            }

            $dateKey = $order->received_date instanceof Carbon
                ? $order->received_date->format('Y-m-d')
                : Carbon::parse($order->received_date)->format('Y-m-d');

            if (! isset($dailyData[$dateKey])) {
                continue;
            }

            $revenue = (float) $order->total_charge;
            $itemsCount = collect($order->items ?? [])->sum('quantity');

            $dailyData[$dateKey]['revenue'] += $revenue;
            $dailyData[$dateKey]['orders']++;
            $dailyData[$dateKey]['items'] += $itemsCount;
        }

        // Convert to collection and calculate avg_order_value
        return collect($dailyData)->map(function (array $day) {
            $day['avg_order_value'] = $day['orders'] > 0 ? $day['revenue'] / $day['orders'] : 0;

            return collect($day);
        })->values();
    }
}
