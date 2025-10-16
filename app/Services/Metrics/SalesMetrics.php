<?php

declare(strict_types=1);

namespace App\Services\Metrics;

use App\Models\Order;
use App\Traits\PreparesChartData;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;

class SalesMetrics extends MetricBase
{
    use PreparesChartData;

    /**
     * Cache calculated revenue per order to avoid recalculating repeatedly within a single request
     */
    private array $orderRevenueCache = [];

    /**
     * Cache for daily sales data to avoid recalculating for every chart method
     */
    private ?Collection $dailySalesCache = null;

    private ?string $dailySalesCacheKey = null;

    public function __construct(Collection $data)
    {
        parent::__construct($data);
    }

    /**
     * Get total revenue from all orders
     */
    public function totalRevenue(): float
    {
        return (float) $this->data->sum(fn ($order) => $this->calculateOrderRevenue($order));
    }

    /**
     * Get total number of orders
     */
    public function totalOrders(): int
    {
        return $this->data->count();
    }

    /**
     * Get average order value
     */
    public function averageOrderValue(): float
    {
        if ($this->totalOrders() === 0) {
            return 0.0;
        }

        return $this->totalRevenue() / $this->totalOrders();
    }

    /**
     * Get total items sold across all orders
     * Falls back to JSON column if order_items table is empty
     */
    public function totalItemsSold(): int
    {
        $orderIds = $this->data->pluck('id')->toArray();

        if (empty($orderIds)) {
            return 0;
        }

        // Try order_items table first
        $fromTable = \App\Models\OrderItem::whereIn('order_id', $orderIds)->sum('quantity');

        // If no items in table, use JSON column as fallback
        if ($fromTable == 0) {
            return (int) $this->data->sum(function ($order) {
                return collect($order->items ?? [])->sum('quantity');
            });
        }

        return (int) $fromTable;
    }

    /**
     * Get total processed orders count
     */
    public function totalProcessedOrders(): int
    {
        return $this->data->where('is_processed', true)->count();
    }

    /**
     * Get total open orders count
     */
    public function totalOpenOrders(): int
    {
        return $this->data->where('is_processed', false)->count();
    }

    /**
     * Get processed orders revenue
     */
    public function processedOrdersRevenue(): float
    {
        return (float) $this->data
            ->where('is_processed', true)
            ->sum(fn ($order) => $this->calculateOrderRevenue($order));
    }

    /**
     * Get open orders revenue
     */
    public function openOrdersRevenue(): float
    {
        return (float) $this->data
            ->where('is_processed', false)
            ->sum(fn ($order) => $this->calculateOrderRevenue($order));
    }

    /**
     * Get growth rate compared to previous period
     */
    public function growthRate(Collection $previousPeriodData): float
    {
        $currentRevenue = $this->totalRevenue();
        $previousRevenue = (new self($previousPeriodData))->totalRevenue();

        if ($previousRevenue === 0.0) {
            return $currentRevenue > 0 ? 100.0 : 0.0;
        }

        return (($currentRevenue - $previousRevenue) / $previousRevenue) * 100;
    }

    /**
     * Get orders per day for the given period
     */
    public function ordersPerDay(int $days): float
    {
        if ($days === 0) {
            return 0.0;
        }

        return $this->totalOrders() / $days;
    }

    /**
     * Get top performing channels by revenue (grouped by channel + subsource)
     */
    public function topChannels(int $limit = 6): Collection
    {
        $totalRevenue = $this->totalRevenue();

        return $this->data
            ->groupBy(fn ($order) => $order->channel_name.'|'.($order->subsource ?? $order->sub_source ?? ''))
            /** @phpstan-ignore-next-line  Template covariance issue */
            ->map(function (Collection $channelOrders, string $groupKey) use ($totalRevenue) {
                [$channel, $subsource] = explode('|', $groupKey, 2);
                $channelRevenue = $channelOrders->sum(fn ($order) => $this->calculateOrderRevenue($order));
                $ordersCount = $channelOrders->count();

                $displayName = $subsource
                    ? "{$subsource} ({$channel})"
                    : $channel;

                /** @phpstan-ignore-next-line Template covariance issue */
                return collect([
                    'name' => $displayName,
                    'channel' => $channel,
                    'subsource' => $subsource ?: null,
                    'orders' => $ordersCount,
                    'revenue' => $channelRevenue,
                    'avg_order_value' => $ordersCount > 0 ? $channelRevenue / $ordersCount : 0,
                    'percentage' => $totalRevenue > 0 ? ($channelRevenue / $totalRevenue) * 100 : 0,
                ]);
            })
            ->sortByDesc('revenue')
            ->take($limit)
            ->values();
    }

    /**
     * Get top performing channels by revenue (grouped by channel only, no subsource)
     */
    public function topChannelsGrouped(int $limit = 6): Collection
    {
        $totalRevenue = $this->totalRevenue();

        return $this->data
            ->groupBy(fn ($order) => $order->channel_name)
            ->map(function (Collection $channelOrders, string $channel) use ($totalRevenue) {
                $channelRevenue = $channelOrders->sum(fn ($order) => $this->calculateOrderRevenue($order));
                $ordersCount = $channelOrders->count();

                return collect([
                    'name' => $channel,
                    'orders' => $ordersCount,
                    'revenue' => $channelRevenue,
                    'avg_order_value' => $ordersCount > 0 ? $channelRevenue / $ordersCount : 0,
                    'percentage' => $totalRevenue > 0 ? ($channelRevenue / $totalRevenue) * 100 : 0,
                ]);
            })
            ->sortByDesc('revenue')
            ->take($limit)
            ->values();
    }

    /**
     * Get top performing products by revenue (memory optimized)
     *
     * Instead of flatMapping all items into a huge intermediate collection,
     * we aggregate directly by SKU in a single pass using an array.
     */
    public function topProducts(int $limit = 5): Collection
    {
        // Aggregate by SKU using an array (more memory efficient than collection)
        $grouped = [];

        foreach ($this->data as $order) {
            $items = $order->items ?? [];

            foreach ($items as $item) {
                $sku = $item['sku'] ?? 'unknown-sku';
                $quantity = (int) ($item['quantity'] ?? 0);
                $lineTotal = isset($item['line_total']) ? (float) $item['line_total'] : 0.0;
                $pricePerUnit = isset($item['price_per_unit']) ? (float) $item['price_per_unit'] : 0.0;
                $revenue = $lineTotal > 0 ? $lineTotal : $pricePerUnit * $quantity;

                if (! isset($grouped[$sku])) {
                    $grouped[$sku] = [
                        'sku' => $sku,
                        'title' => $item['item_title'] ?? null,
                        'quantity' => 0,
                        'revenue' => 0.0,
                        'order_ids' => [],
                    ];
                }

                $grouped[$sku]['quantity'] += $quantity;
                $grouped[$sku]['revenue'] += $revenue;

                if ($order->id) {
                    $grouped[$sku]['order_ids'][$order->id] = true;
                }

                // Prefer non-null titles
                if (! $grouped[$sku]['title'] && isset($item['item_title'])) {
                    $grouped[$sku]['title'] = $item['item_title'];
                }
            }
        }

        if (empty($grouped)) {
            return collect();
        }

        // Convert to collection for sorting/manipulation
        $result = collect($grouped)->map(function (array $product) {
            $quantity = $product['quantity'];
            $revenue = $product['revenue'];

            return collect([
                'sku' => $product['sku'],
                'title' => $product['title'],
                'quantity' => $quantity,
                'revenue' => $revenue,
                'orders' => count($product['order_ids']),
                'avg_price' => $quantity > 0 ? $revenue / $quantity : 0,
            ]);
        });

        // Fetch product titles from database
        $skus = array_keys($grouped);
        $products = \App\Models\Product::whereIn('sku', $skus)
            ->pluck('title', 'sku');

        $result = $result->map(function (Collection $product) use ($products) {
            $sku = $product->get('sku');

            if (! $product->get('title') && isset($products[$sku])) {
                $product->put('title', $products[$sku]);
            }

            if (! $product->get('title')) {
                $product->put('title', 'Unknown Product');
            }

            return $product;
        });

        return $result
            ->sortByDesc('revenue')
            ->take($limit)
            ->values();
    }

    /**
     * Get daily sales data for chart visualization (with memoization)
     */
    public function dailySalesData(string $period, ?string $startDate = null, ?string $endDate = null): Collection
    {
        // Create cache key from parameters
        $cacheKey = md5($period.$startDate.$endDate);

        // Return cached result if available
        if ($this->dailySalesCacheKey === $cacheKey && $this->dailySalesCache !== null) {
            return $this->dailySalesCache;
        }

        // Calculate and cache the result
        $result = $this->calculateDailySalesData($period, $startDate, $endDate);
        $this->dailySalesCache = $result;
        $this->dailySalesCacheKey = $cacheKey;

        return $result;
    }

    /**
     * Internal method to calculate daily sales data
     */
    private function calculateDailySalesData(string $period, ?string $startDate, ?string $endDate): Collection
    {
        if ($startDate && $endDate) {
            $start = Carbon::parse($startDate)->startOfDay();
            $end = Carbon::parse($endDate)->startOfDay();

            if ($start->greaterThan($end)) {
                [$start, $end] = [$end, $start];
            }

            $dates = collect(CarbonPeriod::create($start, '1 day', $end));

            return $this->buildDailyBreakdownBatch($dates);
        }

        // Special handling for single-day periods (yesterday and today)
        // Returns 3 data points (yesterday, target day, tomorrow) to center the bar in charts
        if ($period === 'yesterday' || $period === '1') {
            $date = $period === 'yesterday' ? Carbon::yesterday() : Carbon::today();
            $dayOrders = $this->data;
            $openOrders = $dayOrders->where('is_processed', false);
            $processedOrders = $dayOrders->where('is_processed', true);

            $revenue = $dayOrders->sum(fn ($order) => $this->calculateOrderRevenue($order));
            $openRevenue = $openOrders->sum(fn ($order) => $this->calculateOrderRevenue($order));
            $processedRevenue = $processedOrders->sum(fn ($order) => $this->calculateOrderRevenue($order));
            $orderCount = $dayOrders->count();

            $itemsCount = $dayOrders->sum(function ($order) {
                return collect($order->items ?? [])->sum('quantity');
            });

            // Pad with empty data points to center the single day in the chart
            return collect([
                collect([
                    'date' => $date->copy()->subDay()->format('M j, Y'),
                    'iso_date' => $date->copy()->subDay()->format('Y-m-d'),
                    'day' => $date->copy()->subDay()->format('D'),
                    'revenue' => 0,
                    'orders' => 0,
                    'items' => 0,
                    'avg_order_value' => 0,
                    'open_orders' => 0,
                    'processed_orders' => 0,
                    'open_revenue' => 0,
                    'processed_revenue' => 0,
                ]),
                collect([
                    'date' => $date->format('M j, Y'),
                    'iso_date' => $date->format('Y-m-d'),
                    'day' => $date->format('D'),
                    'revenue' => $revenue,
                    'orders' => $orderCount,
                    'items' => $itemsCount,
                    'avg_order_value' => $orderCount > 0 ? $revenue / $orderCount : 0,
                    'open_orders' => $openOrders->count(),
                    'processed_orders' => $processedOrders->count(),
                    'open_revenue' => $openRevenue,
                    'processed_revenue' => $processedRevenue,
                ]),
                collect([
                    'date' => $date->copy()->addDay()->format('M j, Y'),
                    'iso_date' => $date->copy()->addDay()->format('Y-m-d'),
                    'day' => $date->copy()->addDay()->format('D'),
                    'revenue' => 0,
                    'orders' => 0,
                    'items' => 0,
                    'avg_order_value' => 0,
                    'open_orders' => 0,
                    'processed_orders' => 0,
                    'open_revenue' => 0,
                    'processed_revenue' => 0,
                ]),
            ]);
        }

        $days = (int) max(1, $period);

        $dates = collect(range($days - 1, 0))
            ->map(fn (int $daysAgo) => Carbon::now()->subDays($daysAgo));

        return $this->buildDailyBreakdownBatch($dates);
    }

    /**
     * Build daily breakdown for multiple dates in a single pass (memory optimized)
     *
     * Instead of filtering the entire collection 90 times (once per day),
     * we iterate through orders ONCE and bucket them by date.
     */
    private function buildDailyBreakdownBatch(Collection $dates): Collection
    {
        // Initialize empty data structure for each date using ARRAY (not Collection)
        // Arrays are more memory efficient and allow direct modification
        $dailyData = [];

        foreach ($dates as $date) {
            $dailyData[$date->format('Y-m-d')] = [
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
            ];
        }

        // Single pass through orders - bucket by date
        foreach ($this->data as $order) {
            if (! $order->received_date) {
                continue;
            }

            // Handle both Carbon instances (Eloquent) and strings (DB::table)
            $dateKey = $order->received_date instanceof \Carbon\Carbon
                ? $order->received_date->format('Y-m-d')
                : Carbon::parse($order->received_date)->format('Y-m-d');

            if (! isset($dailyData[$dateKey])) {
                continue;
            }

            $revenue = $this->calculateOrderRevenue($order);
            $itemsCount = collect($order->items ?? [])->sum('quantity');
            $isProcessed = $order->is_processed;

            $dailyData[$dateKey]['revenue'] += $revenue;
            $dailyData[$dateKey]['orders']++;
            $dailyData[$dateKey]['items'] += $itemsCount;

            if ($isProcessed) {
                $dailyData[$dateKey]['processed_orders']++;
                $dailyData[$dateKey]['processed_revenue'] += $revenue;
            } else {
                $dailyData[$dateKey]['open_orders']++;
                $dailyData[$dateKey]['open_revenue'] += $revenue;
            }
        }

        // Calculate avg_order_value for each day and convert to Collection
        return collect($dailyData)->map(function (array $day) {
            $day['avg_order_value'] = $day['orders'] > 0 ? $day['revenue'] / $day['orders'] : 0;

            return collect($day);
        })->values();
    }

    protected function buildDailyBreakdown(Carbon $date): Collection
    {
        $dayOrders = $this->data->filter(function ($order) use ($date) {
            if (! $order->received_date) {
                return false;
            }

            // Handle both Carbon instances (Eloquent) and strings (DB::table)
            $receivedDate = $order->received_date instanceof \Carbon\Carbon
                ? $order->received_date
                : Carbon::parse($order->received_date);

            return $receivedDate->isSameDay($date);
        });
        $openOrders = $dayOrders->where('is_processed', false);
        $processedOrders = $dayOrders->where('is_processed', true);

        $revenue = $dayOrders->sum(fn ($order) => $this->calculateOrderRevenue($order));
        $openRevenue = $openOrders->sum(fn ($order) => $this->calculateOrderRevenue($order));
        $processedRevenue = $processedOrders->sum(fn ($order) => $this->calculateOrderRevenue($order));
        $orderCount = $dayOrders->count();

        $itemsCount = $dayOrders->sum(function ($order) {
            return collect($order->items ?? [])->sum('quantity');
        });

        return collect([
            'date' => $date->format('M j, Y'),
            'iso_date' => $date->format('Y-m-d'),
            'day' => $date->format('D'),
            'revenue' => $revenue,
            'orders' => $orderCount,
            'items' => $itemsCount,
            'avg_order_value' => $orderCount > 0 ? $revenue / $orderCount : 0,
            'open_orders' => $openOrders->count(),
            'processed_orders' => $processedOrders->count(),
            'open_revenue' => $openRevenue,
            'processed_revenue' => $processedRevenue,
        ]);
    }

    /**
     * Get recent orders (most recent first)
     */
    public function recentOrders(int $limit = 15): Collection
    {
        return $this->data
            ->sortByDesc('received_date')
            ->take($limit)
            ->values();
    }

    /**
     * Get the day with the highest revenue
     */
    public function bestPerformingDay(?string $startDate = null, ?string $endDate = null): ?Collection
    {
        $period = '30'; // Default to last 30 days

        if ($startDate && $endDate) {
            $start = Carbon::parse($startDate);
            $end = Carbon::parse($endDate);
            $period = (string) max(1, $start->diffInDays($end));
        }

        $dailyData = $this->dailySalesData($period, $startDate, $endDate);

        if ($dailyData->isEmpty()) {
            return null;
        }

        return $dailyData->sortByDesc('revenue')->first();
    }

    /**
     * Get all available channels from the data
     */
    public function availableChannels(): Collection
    {
        return $this->data
            ->pluck('channel_name')
            ->filter()
            ->unique()
            ->reject(fn ($channel) => $channel === 'DIRECT')
            ->sort()
            ->map(fn ($channel) => collect([
                'name' => $channel,
                'label' => ucfirst($channel),
            ]))
            ->values();
    }

    /**
     * Get comprehensive metrics summary
     */
    public function getMetricsSummary(int $periodDays, ?Collection $previousPeriodData = null): Collection
    {
        $metrics = collect([
            'total_revenue' => $this->totalRevenue(),
            'total_orders' => $this->totalOrders(),
            'average_order_value' => $this->averageOrderValue(),
            'total_items' => $this->totalItemsSold(),
            'orders_per_day' => $this->ordersPerDay($periodDays),
        ]);

        if ($previousPeriodData) {
            $metrics->put('growth_rate', $this->growthRate($previousPeriodData));
        }

        return $metrics;
    }

    /**
     * Prepare data for line charts
     */
    public function getLineChartData(string $period, ?string $startDate = null, ?string $endDate = null): array
    {
        $chartData = $this->dailySalesData($period, $startDate, $endDate);

        $labels = $chartData->map(function ($day) {
            $revenue = $day['revenue'];
            $formattedRevenue = '£'.number_format($revenue, 0);

            return "{$day['date']} - {$formattedRevenue}";
        })->toArray();

        $chart = [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Total Revenue (£)',
                    'data' => $chartData->pluck('revenue')->toArray(),
                    'borderColor' => '#3B82F6',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                    'tension' => 0.4,
                    'fill' => true,
                    'borderWidth' => 2,
                ],
                [
                    'label' => 'Processed Orders Revenue (£)',
                    'data' => $chartData->pluck('processed_revenue')->toArray(),
                    'borderColor' => '#10B981',
                    'backgroundColor' => 'rgba(16, 185, 129, 0.1)',
                    'tension' => 0.4,
                    'fill' => true,
                    'borderWidth' => 2,
                ],
                [
                    'label' => 'Open Orders Revenue (£)',
                    'data' => $chartData->pluck('open_revenue')->toArray(),
                    'borderColor' => '#F59E0B',
                    'backgroundColor' => 'rgba(245, 158, 11, 0.1)',
                    'tension' => 0.4,
                    'fill' => true,
                    'borderWidth' => 2,
                    'borderDash' => [5, 5],
                ],
            ],
            'meta' => [
                'iso_dates' => $chartData->pluck('iso_date')->toArray(),
            ],
        ];

        // Add padding for single-day periods
        if ($period === '1' || $period === 'yesterday') {
            $chart['options'] = $this->getSingleDayChartOptions();
        }

        return $chart;
    }

    /**
     * Prepare data for bar charts
     */
    public function getBarChartData(string $period, ?string $startDate = null, ?string $endDate = null): array
    {
        $chartData = $this->dailySalesData($period, $startDate, $endDate);

        return [
            'labels' => $chartData->pluck('date')->toArray(),
            'datasets' => [
                [
                    'label' => 'Processed Orders (£)',
                    'data' => $chartData->pluck('processed_revenue')->toArray(),
                    'backgroundColor' => '#10B981',
                    'borderColor' => '#10B981',
                    'borderWidth' => 1,
                ],
                [
                    'label' => 'Open Orders (£)',
                    'data' => $chartData->pluck('open_revenue')->toArray(),
                    'backgroundColor' => '#F59E0B',
                    'borderColor' => '#F59E0B',
                    'borderWidth' => 1,
                ],
            ],
            'meta' => [
                'iso_dates' => $chartData->pluck('iso_date')->toArray(),
            ],
        ];
    }

    /**
     * Prepare data for orders vs revenue dual-axis chart
     */
    public function getOrdersVsRevenueChartData(string $period, ?string $startDate = null, ?string $endDate = null): array
    {
        $chartData = $this->dailySalesData($period, $startDate, $endDate);

        $labels = $chartData->map(function ($day) {
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
                    'data' => $chartData->pluck('orders')->toArray(),
                    'borderColor' => '#3B82F6',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                    'yAxisID' => 'y',
                    'tension' => 0.4,
                    'fill' => true,
                    'borderWidth' => 2,
                ],
                [
                    'label' => 'Revenue (£)',
                    'data' => $chartData->pluck('revenue')->toArray(),
                    'borderColor' => '#10B981',
                    'backgroundColor' => 'rgba(16, 185, 129, 0.1)',
                    'yAxisID' => 'y1',
                    'tension' => 0.4,
                    'fill' => true,
                    'borderWidth' => 2,
                ],
            ],
            'meta' => [
                'iso_dates' => $chartData->pluck('iso_date')->toArray(),
            ],
        ];

        // Add padding for single-day periods (with dual-axis support)
        if ($period === '1' || $period === 'yesterday') {
            $chart['options'] = $this->getSingleDayChartOptions(true);
        }

        return $chart;
    }

    /**
     * Prepare data for items sold trend chart
     */
    public function getItemsSoldChartData(string $period, ?string $startDate = null, ?string $endDate = null): array
    {
        $chartData = $this->dailySalesData($period, $startDate, $endDate);

        $labels = $chartData->map(function ($day) {
            $items = $day['items'];

            return "{$day['date']} - {$items}";
        })->toArray();

        $chart = [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Items Sold',
                    'data' => $chartData->pluck('items')->toArray(),
                    'borderColor' => '#8B5CF6',
                    'backgroundColor' => 'rgba(139, 92, 246, 0.1)',
                    'tension' => 0.4,
                    'fill' => true,
                    'borderWidth' => 2,
                ],
            ],
            'meta' => [
                'iso_dates' => $chartData->pluck('iso_date')->toArray(),
            ],
        ];

        // Add padding for single-day periods
        if ($period === '1' || $period === 'yesterday') {
            $chart['options'] = $this->getSingleDayChartOptions();
        }

        return $chart;
    }

    /**
     * Prepare data for doughnut charts (with subsource breakdown)
     */
    public function getDoughnutChartData(): array
    {
        return $this->prepareDoughnutChartData(
            $this->topChannels(),
            'name',
            'revenue'
        );
    }

    /**
     * Prepare data for doughnut charts (grouped by channel only)
     */
    public function getDoughnutChartDataGrouped(): array
    {
        return $this->prepareDoughnutChartData(
            $this->topChannelsGrouped(),
            'name',
            'revenue'
        );
    }

    /**
     * Prepare data for order count charts (showing open vs processed as line chart)
     */
    public function getOrderCountChartData(string $period, ?string $startDate = null, ?string $endDate = null): array
    {
        $chartData = $this->dailySalesData($period, $startDate, $endDate);

        $labels = $chartData->map(function ($day) {
            $orders = $day['orders'];

            return "{$day['date']} - {$orders}";
        })->toArray();

        $chart = [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Total Orders',
                    'data' => $chartData->pluck('orders')->toArray(),
                    'borderColor' => '#3B82F6',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                    'tension' => 0.4,
                    'fill' => true,
                    'borderWidth' => 2,
                ],
                [
                    'label' => 'Processed Orders',
                    'data' => $chartData->pluck('processed_orders')->toArray(),
                    'borderColor' => '#10B981',
                    'backgroundColor' => 'rgba(16, 185, 129, 0.1)',
                    'tension' => 0.4,
                    'fill' => true,
                    'borderWidth' => 2,
                ],
                [
                    'label' => 'Open Orders',
                    'data' => $chartData->pluck('open_orders')->toArray(),
                    'borderColor' => '#F59E0B',
                    'backgroundColor' => 'rgba(245, 158, 11, 0.1)',
                    'tension' => 0.4,
                    'fill' => true,
                    'borderWidth' => 2,
                    'borderDash' => [5, 5],
                ],
            ],
            'meta' => [
                'iso_dates' => $chartData->pluck('iso_date')->toArray(),
            ],
        ];

        // Add padding for single-day periods
        if ($period === '1' || $period === 'yesterday') {
            $chart['options'] = $this->getSingleDayChartOptions();
        }

        return $chart;
    }

    /**
     * Prepare data for order status doughnut chart (for analytics page)
     */
    public function getOrderStatusDoughnutData(): array
    {
        $processedOrders = $this->data->where('is_processed', true)->count();
        $openOrders = $this->data->where('is_processed', false)->count();

        return [
            'labels' => ['Processed Orders', 'Open Orders'],
            'datasets' => [[
                'label' => 'Order Status Distribution',
                'data' => [$processedOrders, $openOrders],
                'backgroundColor' => ['#10B981', '#F59E0B'],
                'borderColor' => ['#059669', '#D97706'],
                'borderWidth' => 2,
            ]],
        ];
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
                    'left' => 20,
                    'right' => 20,
                    'top' => 20,
                    'bottom' => 10,
                ],
            ],
            'scales' => [
                'y' => [
                    'grace' => '15%', // Add 15% padding above the highest value
                ],
            ],
        ];

        // For dual-axis charts, add padding to both y-axes
        if ($dualAxis) {
            $options['scales']['y1'] = [
                'grace' => '15%',
            ];
        }

        return $options;
    }

    private function calculateOrderRevenue(object $order): float
    {
        // Use ID for cache key if available, otherwise use object ID
        $cacheKey = $order->id ?? spl_object_id($order);

        if (array_key_exists($cacheKey, $this->orderRevenueCache)) {
            return $this->orderRevenueCache[$cacheKey];
        }

        $totalCharge = (float) $order->total_charge;
        if ($totalCharge > 0) {
            return $this->orderRevenueCache[$cacheKey] = $totalCharge;
        }

        $items = collect($order->items ?? []);
        if ($items->isNotEmpty()) {
            $itemsTotal = $items->sum(function (array $item) {
                $lineTotal = isset($item['line_total']) ? (float) $item['line_total'] : 0.0;
                $quantity = isset($item['quantity']) ? (int) $item['quantity'] : 0;
                $pricePerUnit = isset($item['price_per_unit']) ? (float) $item['price_per_unit'] : 0.0;

                if ($lineTotal > 0) {
                    return $lineTotal;
                }

                if ($pricePerUnit > 0 && $quantity > 0) {
                    return $pricePerUnit * $quantity;
                }

                return 0.0;
            });

            if ($itemsTotal > 0) {
                return $this->orderRevenueCache[$cacheKey] = $itemsTotal;
            }
        }

        // Check if orderItems relationship is loaded (Eloquent only, not stdClass)
        if (method_exists($order, 'relationLoaded') && $order->relationLoaded('orderItems')) {
            $itemsTotal = $order->orderItems->sum(function ($item) {
                $totalPrice = (float) $item->total_price;

                if ($totalPrice > 0) {
                    return $totalPrice;
                }

                $unitPrice = (float) $item->unit_price;
                $quantity = (int) $item->quantity;

                return $unitPrice > 0 && $quantity > 0 ? $unitPrice * $quantity : 0.0;
            });

            if ($itemsTotal > 0) {
                return $this->orderRevenueCache[$cacheKey] = $itemsTotal;
            }
        }

        $totalPaid = (float) $order->total_paid;
        if ($totalPaid > 0) {
            return $this->orderRevenueCache[$cacheKey] = $totalPaid;
        }

        return $this->orderRevenueCache[$cacheKey] = 0.0;
    }
}
