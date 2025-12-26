<?php

declare(strict_types=1);

namespace App\Services\Metrics;

use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Memory-efficient metrics calculator for large datasets
 *
 * Uses database aggregation and streaming to calculate metrics
 * without loading all orders into memory at once.
 *
 * Designed for 365d and 730d periods with 100K+ orders.
 * Also handles custom date ranges without OOM issues.
 */
final readonly class ChunkedMetricsCalculator
{
    public function __construct(
        private string $period,
        private string $channel = 'all',
        private string $status = 'all',
        private ?string $customFrom = null,
        private ?string $customTo = null
    ) {}

    /**
     * Calculate all metrics using chunked/streaming approach
     *
     * Memory optimization strategy:
     * 1. Simple aggregates: Use database SUM/COUNT/AVG
     * 2. Daily data: Use database GROUP BY date
     * 3. Top N lists: Stream and aggregate incrementally
     * 4. Charts: Build from daily aggregates
     */
    public function calculate(): array
    {
        [$start, $end] = $this->getDateRange();

        // Get simple aggregates directly from database (very memory efficient)
        $aggregates = $this->calculateSimpleAggregates($start, $end);

        // Get daily aggregated data (grouped at DB level)
        $dailyData = $this->calculateDailyAggregates($start, $end);

        // Get top channels using database aggregation
        $topChannels = $this->calculateTopChannels($start, $end);

        // Get top products by streaming orders and aggregating items
        $topProducts = $this->calculateTopProducts($start, $end);

        // Get recent orders (limited, so memory safe)
        $recentOrders = $this->getRecentOrders($start, $end);

        // Find best performing day from daily data
        $bestDay = $dailyData->sortByDesc('revenue')->first();

        return [
            'revenue' => $aggregates['revenue'],
            'orders' => $aggregates['orders'],
            'items' => $aggregates['items'],
            'avg_order_value' => $aggregates['avg_order_value'],
            'processed_orders' => $aggregates['processed_orders'],
            'open_orders' => $aggregates['open_orders'],
            'top_channels' => $topChannels,
            'top_products' => $topProducts,
            'chart_doughnut' => $this->buildDoughnutChart($topChannels),
            'recent_orders' => $recentOrders,
            'best_day' => $bestDay,
            'daily_breakdown' => $dailyData->toArray(),
            'warmed_at' => now()->toISOString(),
        ];
    }

    /**
     * Calculate simple aggregates using database queries
     *
     * Single query with conditional aggregation - very efficient!
     */
    private function calculateSimpleAggregates(Carbon $start, Carbon $end): array
    {
        $query = DB::table('orders')
            ->whereBetween('received_at', [$start, $end])
            ->where('source', '!=', 'DIRECT')
            ->when($this->channel !== 'all', fn ($q) => $q->where('source', $this->channel));

        $this->applyStatusFilter($query);

        $result = $query->selectRaw('
                COUNT(*) as total_orders,
                SUM(total_charge) as total_revenue,
                SUM(CASE WHEN status = 1 THEN 1 ELSE 0 END) as processed_orders,
                SUM(CASE WHEN status = 0 THEN 1 ELSE 0 END) as open_orders,
                AVG(total_charge) as avg_order_value
            ')
            ->first();

        // Count items from order_items table (more efficient than JSON parsing)
        $itemsQuery = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereBetween('orders.received_at', [$start, $end])
            ->where('orders.source', '!=', 'DIRECT')
            ->when($this->channel !== 'all', fn ($q) => $q->where('orders.source', $this->channel));

        $this->applyStatusFilter($itemsQuery);

        $itemsCount = $itemsQuery->sum('order_items.quantity');

        return [
            'orders' => (int) $result->total_orders,
            'revenue' => (float) $result->total_revenue,
            'processed_orders' => (int) $result->processed_orders,
            'open_orders' => (int) $result->open_orders,
            'avg_order_value' => (float) $result->avg_order_value,
            'items' => (int) $itemsCount,
        ];
    }

    /**
     * Calculate daily aggregates using database GROUP BY
     *
     * Returns one row per day with all metrics pre-aggregated
     */
    private function calculateDailyAggregates(Carbon $start, Carbon $end): Collection
    {
        // For single-day periods (Today/Yesterday), add padding days to center the bar
        $actualStart = $start;
        $actualEnd = $end;

        if ($this->period === '0' || $this->period === '1') {
            // Expand range to include day before and day after for chart padding
            $start = $start->copy()->subDay();
            $end = $end->copy()->addDay();
        }

        // Get all dates in the range
        $dates = collect(CarbonPeriod::create($start, '1 day', $end))
            ->mapWithKeys(fn (Carbon $date) => [
                $date->format('Y-m-d') => [
                    'date' => $date->format('M j, Y'),
                    'iso_date' => $date->format('Y-m-d'),
                    'day' => $date->format('D'),
                    'revenue' => 0.0,
                    'orders' => 0,
                    'items' => 0,
                    'open_orders' => 0,
                    'processed_orders' => 0,
                    'open_revenue' => 0.0,
                    'processed_revenue' => 0.0,
                    'avg_order_value' => 0.0,
                ],
            ]);

        // Aggregate orders by date (use actualStart/actualEnd for single-day periods to only fetch real data)
        $queryStart = ($this->period === '0' || $this->period === '1') ? $actualStart : $start;
        $queryEnd = ($this->period === '0' || $this->period === '1') ? $actualEnd : $end;

        $orderStatsQuery = DB::table('orders')
            ->whereBetween('received_at', [$queryStart, $queryEnd])
            ->where('source', '!=', 'DIRECT')
            ->when($this->channel !== 'all', fn ($q) => $q->where('source', $this->channel));

        $this->applyStatusFilter($orderStatsQuery);

        $orderStats = $orderStatsQuery
            ->selectRaw('
                DATE(received_at) as date,
                COUNT(*) as orders,
                SUM(total_charge) as revenue,
                SUM(CASE WHEN status = 1 THEN 1 ELSE 0 END) as processed_orders,
                SUM(CASE WHEN status = 0 THEN 1 ELSE 0 END) as open_orders,
                SUM(CASE WHEN status = 1 THEN total_charge ELSE 0 END) as processed_revenue,
                SUM(CASE WHEN status = 0 THEN total_charge ELSE 0 END) as open_revenue
            ')
            ->groupByRaw('DATE(received_at)')
            ->get()
            ->keyBy('date');

        // Aggregate items by date (use actualStart/actualEnd for single-day periods to only fetch real data)
        $itemStatsQuery = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereBetween('orders.received_at', [$queryStart, $queryEnd])
            ->where('orders.source', '!=', 'DIRECT')
            ->when($this->channel !== 'all', fn ($q) => $q->where('orders.source', $this->channel));

        $this->applyStatusFilter($itemStatsQuery);

        $itemStats = $itemStatsQuery
            ->selectRaw('
                DATE(orders.received_at) as date,
                SUM(order_items.quantity) as items
            ')
            ->groupByRaw('DATE(orders.received_at)')
            ->get()
            ->keyBy('date');

        // Merge stats into date structure
        return $dates->map(function (array $day, string $dateKey) use ($orderStats, $itemStats) {
            if (isset($orderStats[$dateKey])) {
                $stats = $orderStats[$dateKey];
                $day['revenue'] = (float) $stats->revenue;
                $day['orders'] = (int) $stats->orders;
                $day['processed_orders'] = (int) $stats->processed_orders;
                $day['open_orders'] = (int) $stats->open_orders;
                $day['processed_revenue'] = (float) $stats->processed_revenue;
                $day['open_revenue'] = (float) $stats->open_revenue;
                $day['avg_order_value'] = $day['orders'] > 0 ? $day['revenue'] / $day['orders'] : 0.0;
            }

            if (isset($itemStats[$dateKey])) {
                $day['items'] = (int) $itemStats[$dateKey]->items;
            }

            return $day;
        })->values();
    }

    /**
     * Calculate top channels using database aggregation
     */
    private function calculateTopChannels(Carbon $start, Carbon $end, int $limit = 6): Collection
    {
        $totalRevenueQuery = DB::table('orders')
            ->whereBetween('received_at', [$start, $end])
            ->where('source', '!=', 'DIRECT')
            ->when($this->channel !== 'all', fn ($q) => $q->where('source', $this->channel));

        $this->applyStatusFilter($totalRevenueQuery);

        $totalRevenue = $totalRevenueQuery->sum('total_charge');

        $channelsQuery = DB::table('orders')
            ->whereBetween('received_at', [$start, $end])
            ->where('source', '!=', 'DIRECT')
            ->when($this->channel !== 'all', fn ($q) => $q->where('source', $this->channel));

        $this->applyStatusFilter($channelsQuery);

        return $channelsQuery
            ->selectRaw('
                source,
                COALESCE(subsource, "") as subsource,
                COUNT(*) as orders,
                SUM(total_charge) as revenue
            ')
            ->groupBy('source', 'subsource')
            ->orderByDesc('revenue')
            ->limit($limit)
            ->get()
            ->map(function ($channel) use ($totalRevenue) {
                $revenue = (float) $channel->revenue;
                $orders = (int) $channel->orders;

                $displayName = $channel->subsource
                    ? "{$channel->subsource} ({$channel->source})"
                    : $channel->source;

                // Wrap in collect() to match SalesMetrics format (blade template expects Collection)
                return collect([
                    'name' => $displayName,
                    'channel' => $channel->source,
                    'subsource' => $channel->subsource ?: null,
                    'orders' => $orders,
                    'revenue' => $revenue,
                    'avg_order_value' => $orders > 0 ? $revenue / $orders : 0,
                    'percentage' => $totalRevenue > 0 ? ($revenue / $totalRevenue) * 100 : 0,
                ]);
            });
    }

    /**
     * Calculate top products using streaming aggregation
     *
     * Uses order_items table aggregation - much more efficient!
     */
    private function calculateTopProducts(Carbon $start, Carbon $end, int $limit = 5): Collection
    {
        // FIXED: Use correct column names from order_items table
        // - item_title (not title)
        // - line_total (not total_price)
        $productStatsQuery = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereBetween('orders.received_at', [$start, $end])
            ->where('orders.source', '!=', 'DIRECT')
            ->when($this->channel !== 'all', fn ($q) => $q->where('orders.source', $this->channel));

        $this->applyStatusFilter($productStatsQuery);

        $productStats = $productStatsQuery
            ->selectRaw('
                order_items.sku,
                order_items.item_title,
                SUM(order_items.quantity) as quantity,
                SUM(order_items.line_total) as revenue,
                COUNT(DISTINCT orders.id) as order_count
            ')
            ->groupBy('order_items.sku', 'order_items.item_title')
            ->orderByDesc('revenue')
            ->limit($limit)
            ->get();

        // Fetch product titles from products table
        $skus = $productStats->pluck('sku')->toArray();
        $products = DB::table('products')
            ->whereIn('sku', $skus)
            ->pluck('title', 'sku');

        return $productStats->map(function ($product) use ($products) {
            $quantity = (int) $product->quantity;
            $revenue = (float) $product->revenue;

            // Prefer product table title (if real), fall back to item title
            $productTitle = $products[$product->sku] ?? null;
            $title = ($productTitle && $productTitle !== 'Unknown Product')
                ? $productTitle
                : ($product->item_title ?? 'Unknown Product');

            // Wrap in collect() to match SalesMetrics format (blade template expects Collection)
            return collect([
                'sku' => $product->sku,
                'title' => $title,
                'quantity' => $quantity,
                'revenue' => $revenue,
                'orders' => (int) $product->order_count,
                'avg_price' => $quantity > 0 ? $revenue / $quantity : 0,
            ]);
        });
    }

    /**
     * Get recent orders (limited, so memory safe)
     */
    private function getRecentOrders(Carbon $start, Carbon $end, int $limit = 15): Collection
    {
        // FIXED: Removed 'items' column reference - it doesn't exist in orders table
        // Items are in the order_items relationship, not a JSON column
        $query = DB::table('orders')
            ->select([
                'id',
                'number',
                'order_id',
                'received_at',
                'source',
                'subsource',
                'total_charge',
                'is_paid',
                'status',
                // 'items' column does NOT exist - removed
            ])
            ->whereBetween('received_at', [$start, $end])
            ->where('source', '!=', 'DIRECT')
            ->when($this->channel !== 'all', fn ($q) => $q->where('source', $this->channel));

        $this->applyStatusFilter($query);

        return $query
            ->orderByDesc('received_at')
            ->limit($limit)
            ->get()
            ->map(function ($order) {
                // Convert date string to Carbon for consistency
                if (is_string($order->received_at)) {
                    $order->received_at = Carbon::parse($order->received_at);
                }

                // Add is_open property (derived from status)
                // status: 0 = open, 1 = processed
                $order->is_open = $order->status === 0;

                return $order;
            });
    }

    /**
     * Build line chart from daily data
     */
    private function buildLineChart(Collection $dailyData): array
    {
        $labels = $dailyData->map(function ($day) {
            $revenue = $day['revenue'];
            $formattedRevenue = '£'.number_format($revenue, 0);

            return "{$day['date']} - {$formattedRevenue}";
        })->toArray();

        $chart = [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Total Revenue (£)',
                    'data' => $dailyData->pluck('revenue')->toArray(),
                    'borderColor' => '#3B82F6',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                    'tension' => 0.4,
                    'fill' => true,
                    'borderWidth' => 2,
                ],
                [
                    'label' => 'Processed Orders Revenue (£)',
                    'data' => $dailyData->pluck('processed_revenue')->toArray(),
                    'borderColor' => '#10B981',
                    'backgroundColor' => 'rgba(16, 185, 129, 0.1)',
                    'tension' => 0.4,
                    'fill' => true,
                    'borderWidth' => 2,
                ],
                [
                    'label' => 'Open Orders Revenue (£)',
                    'data' => $dailyData->pluck('open_revenue')->toArray(),
                    'borderColor' => '#F59E0B',
                    'backgroundColor' => 'rgba(245, 158, 11, 0.1)',
                    'tension' => 0.4,
                    'fill' => true,
                    'borderWidth' => 2,
                    'borderDash' => [5, 5],
                ],
            ],
            'meta' => [
                'iso_dates' => $dailyData->pluck('iso_date')->toArray(),
            ],
        ];

        // Add padding for single-day periods
        if ($this->period === '0' || $this->period === '1') {
            $chart['options'] = $this->getSingleDayChartOptions();
        }

        return $chart;
    }

    /**
     * Build order count chart from daily data
     */
    private function buildOrderCountChart(Collection $dailyData): array
    {
        $labels = $dailyData->map(function ($day) {
            $orders = $day['orders'];

            return "{$day['date']} - {$orders}";
        })->toArray();

        $chart = [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Total Orders',
                    'data' => $dailyData->pluck('orders')->toArray(),
                    'borderColor' => '#3B82F6',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                    'tension' => 0.4,
                    'fill' => true,
                    'borderWidth' => 2,
                ],
                [
                    'label' => 'Processed Orders',
                    'data' => $dailyData->pluck('processed_orders')->toArray(),
                    'borderColor' => '#10B981',
                    'backgroundColor' => 'rgba(16, 185, 129, 0.1)',
                    'tension' => 0.4,
                    'fill' => true,
                    'borderWidth' => 2,
                ],
                [
                    'label' => 'Open Orders',
                    'data' => $dailyData->pluck('open_orders')->toArray(),
                    'borderColor' => '#F59E0B',
                    'backgroundColor' => 'rgba(245, 158, 11, 0.1)',
                    'tension' => 0.4,
                    'fill' => true,
                    'borderWidth' => 2,
                    'borderDash' => [5, 5],
                ],
            ],
            'meta' => [
                'iso_dates' => $dailyData->pluck('iso_date')->toArray(),
            ],
        ];

        // Add padding for single-day periods
        if ($this->period === '0' || $this->period === '1') {
            $chart['options'] = $this->getSingleDayChartOptions();
        }

        return $chart;
    }

    /**
     * Build doughnut chart from channel data
     */
    private function buildDoughnutChart(Collection $channels): array
    {
        $colors = [
            '#3B82F6', '#10B981', '#F59E0B', '#EF4444',
            '#8B5CF6', '#EC4899', '#14B8A6', '#F97316',
        ];

        return [
            'labels' => $channels->pluck('name')->toArray(),
            'datasets' => [[
                'label' => 'Revenue by Channel',
                'data' => $channels->pluck('revenue')->toArray(),
                'backgroundColor' => array_slice($colors, 0, $channels->count()),
                'borderWidth' => 2,
            ]],
        ];
    }

    /**
     * Build items sold chart from daily data
     */
    private function buildItemsChart(Collection $dailyData): array
    {
        $labels = $dailyData->map(function ($day) {
            $items = $day['items'];

            return "{$day['date']} - {$items}";
        })->toArray();

        $chart = [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Items Sold',
                    'data' => $dailyData->pluck('items')->toArray(),
                    'borderColor' => '#8B5CF6',
                    'backgroundColor' => 'rgba(139, 92, 246, 0.1)',
                    'tension' => 0.4,
                    'fill' => true,
                    'borderWidth' => 2,
                ],
            ],
            'meta' => [
                'iso_dates' => $dailyData->pluck('iso_date')->toArray(),
            ],
        ];

        // Add padding for single-day periods
        if ($this->period === '0' || $this->period === '1') {
            $chart['options'] = $this->getSingleDayChartOptions();
        }

        return $chart;
    }

    /**
     * Build orders vs revenue dual-axis chart
     */
    private function buildOrdersVsRevenueChart(Collection $dailyData): array
    {
        $labels = $dailyData->map(function ($day) {
            $orders = $day['orders'];
            $revenue = $day['revenue'];
            $formattedRevenue = '£'.number_format($revenue, 0);

            return "{$day['date']} - {$orders} / {$formattedRevenue}";
        })->toArray();

        $chart = [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Orders',
                    'data' => $dailyData->pluck('orders')->toArray(),
                    'borderColor' => '#3B82F6',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                    'yAxisID' => 'y',
                    'tension' => 0.4,
                    'fill' => true,
                    'borderWidth' => 2,
                ],
                [
                    'label' => 'Revenue (£)',
                    'data' => $dailyData->pluck('revenue')->toArray(),
                    'borderColor' => '#10B981',
                    'backgroundColor' => 'rgba(16, 185, 129, 0.1)',
                    'yAxisID' => 'y1',
                    'tension' => 0.4,
                    'fill' => true,
                    'borderWidth' => 2,
                ],
            ],
            'meta' => [
                'iso_dates' => $dailyData->pluck('iso_date')->toArray(),
            ],
        ];

        // Add padding for single-day periods (with dual-axis support)
        if ($this->period === '0' || $this->period === '1') {
            $chart['options'] = $this->getSingleDayChartOptions(true);
        }

        return $chart;
    }

    /**
     * Get date range for period (or custom dates)
     */
    private function getDateRange(): array
    {
        // Custom date range takes priority
        if ($this->customFrom && $this->customTo) {
            return [
                Carbon::parse($this->customFrom)->startOfDay(),
                Carbon::parse($this->customTo)->endOfDay(),
            ];
        }

        // Special case: period '0' means "today" (not last 24 hours)
        if ($this->period === '0') {
            return [
                Carbon::today()->startOfDay(),
                Carbon::today()->endOfDay(),
            ];
        }

        // Special case: period '1' means "yesterday"
        if ($this->period === '1') {
            return [
                Carbon::yesterday()->startOfDay(),
                Carbon::yesterday()->endOfDay(),
            ];
        }

        $days = (int) $this->period;
        $now = Carbon::now();

        return [
            $now->copy()->subDays($days)->startOfDay(),
            $now->endOfDay(),
        ];
    }

    /**
     * Check if this is a custom date range (not a standard period)
     */
    public function isCustomRange(): bool
    {
        return $this->customFrom !== null && $this->customTo !== null;
    }

    /**
     * Apply status filter to query builder
     */
    private function applyStatusFilter($query)
    {
        return $query->when($this->status !== 'all', function ($q) {
            if ($this->status === 'open_paid') {
                $q->where('is_paid', true);
            } elseif ($this->status === 'open') {
                $q->where('status', 0)->where('is_paid', true);
            } elseif ($this->status === 'processed') {
                $q->where('status', 1)->where('is_paid', true);
            }
        });
    }

    /**
     * Get Chart.js options for single-day periods (Today/Yesterday)
     *
     * Adds extra padding to improve visualization of single data points
     */
    private function getSingleDayChartOptions(bool $dualAxis = false): array
    {
        $options = [
            'layout' => [
                'padding' => [
                    'left' => 40,
                    'right' => 40,
                    'top' => 20,
                    'bottom' => 10,
                ],
            ],
            'scales' => [
                'x' => [
                    'offset' => true, // Add padding on x-axis to center the points
                    'grid' => [
                        'offset' => true,
                    ],
                ],
                'y' => [
                    'beginAtZero' => true, // Force Y-axis to start at 0
                    'grace' => '15%', // Add 15% padding above the highest value
                ],
            ],
        ];

        // For dual-axis charts, add padding to both y-axes
        if ($dualAxis) {
            $options['scales']['y1'] = [
                'beginAtZero' => true,
                'grace' => '15%',
            ];
        }

        return $options;
    }
}
