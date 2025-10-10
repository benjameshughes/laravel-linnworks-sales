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
     * Cache calculated revenue per order to avoid recalculating repeatedly
     */
    private array $orderRevenueCache = [];

    public function __construct(Collection $data)
    {
        parent::__construct($data);
    }

    /**
     * Get total revenue from all orders
     */
    public function totalRevenue(): float
    {
        return $this->cache('total_revenue', function () {
            return (float) $this->data->sum(fn (Order $order) => $this->calculateOrderRevenue($order));
        });
    }

    /**
     * Get total number of orders
     */
    public function totalOrders(): int
    {
        return $this->cache('total_orders', function () {
            return $this->data->count();
        });
    }

    /**
     * Get average order value
     */
    public function averageOrderValue(): float
    {
        return $this->cache('average_order_value', function () {
            if ($this->totalOrders() === 0) {
                return 0.0;
            }
            return $this->totalRevenue() / $this->totalOrders();
        });
    }

    /**
     * Get total items sold across all orders
     * Falls back to JSON column if order_items table is empty
     */
    public function totalItemsSold(): int
    {
        return $this->cache('total_items_sold', function () {
            $orderIds = $this->data->pluck('id')->toArray();

            if (empty($orderIds)) {
                return 0;
            }

            // Try order_items table first
            $fromTable = \App\Models\OrderItem::whereIn('order_id', $orderIds)->sum('quantity');

            // If no items in table, use JSON column as fallback
            if ($fromTable === 0) {
                return $this->data->sum(function ($order) {
                    return collect($order->items ?? [])->sum('quantity');
                });
            }

            return $fromTable;
        });
    }

    /**
     * Get total processed orders count
     */
    public function totalProcessedOrders(): int
    {
        return $this->cache('total_processed_orders', function () {
            return $this->data->where('is_processed', true)->count();
        });
    }

    /**
     * Get total open orders count
     */
    public function totalOpenOrders(): int
    {
        return $this->cache('total_open_orders', function () {
            return $this->data->where('is_processed', false)->count();
        });
    }

    /**
     * Get processed orders revenue
     */
    public function processedOrdersRevenue(): float
    {
        return $this->cache('processed_orders_revenue', function () {
            return (float) $this->data
                ->where('is_processed', true)
                ->sum(fn (Order $order) => $this->calculateOrderRevenue($order));
        });
    }

    /**
     * Get open orders revenue
     */
    public function openOrdersRevenue(): float
    {
        return $this->cache('open_orders_revenue', function () {
            return (float) $this->data
                ->where('is_processed', false)
                ->sum(fn (Order $order) => $this->calculateOrderRevenue($order));
        });
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
        return $this->cache("top_channels_{$limit}", function () use ($limit) {
            $totalRevenue = $this->totalRevenue();

            return $this->data
                ->groupBy(fn (Order $order) => $order->channel_name . '|' . ($order->sub_source ?? ''))
                ->map(function (Collection $channelOrders, string $groupKey) use ($totalRevenue) {
                    [$channel, $subsource] = explode('|', $groupKey, 2);
                    $channelRevenue = $channelOrders->sum(fn (Order $order) => $this->calculateOrderRevenue($order));
                    $ordersCount = $channelOrders->count();

                    $displayName = $subsource
                        ? "{$subsource} ({$channel})"
                        : $channel;

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
        });
    }

    /**
     * Get top performing channels by revenue (grouped by channel only, no subsource)
     */
    public function topChannelsGrouped(int $limit = 6): Collection
    {
        return $this->cache("top_channels_grouped_{$limit}", function () use ($limit) {
            $totalRevenue = $this->totalRevenue();

            return $this->data
                ->groupBy(fn (Order $order) => $order->channel_name)
                ->map(function (Collection $channelOrders, string $channel) use ($totalRevenue) {
                    $channelRevenue = $channelOrders->sum(fn (Order $order) => $this->calculateOrderRevenue($order));
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
        });
    }

    /**
     * Get top performing products by revenue
     */
    public function topProducts(int $limit = 5): Collection
    {
        return $this->cache("top_products_{$limit}", function () use ($limit) {
            $items = $this->data->flatMap(function (Order $order) {
                return collect($order->items ?? [])->map(function (array $item) use ($order) {
                    $quantity = (int) ($item['quantity'] ?? 0);
                    $lineTotal = isset($item['line_total']) ? (float) $item['line_total'] : 0.0;
                    $pricePerUnit = isset($item['price_per_unit']) ? (float) $item['price_per_unit'] : 0.0;
                    $revenue = $lineTotal > 0 ? $lineTotal : $pricePerUnit * $quantity;

                    return collect([
                        'order_id' => $order->id,
                        'sku' => $item['sku'] ?? 'unknown-sku',
                        'title' => $item['item_title'] ?? null,
                        'quantity' => $quantity,
                        'revenue' => $revenue,
                        'price_per_unit' => $pricePerUnit,
                    ]);
                });
            });

            if ($items->isEmpty()) {
                return collect();
            }

            $grouped = $items->groupBy('sku')->map(function (Collection $productItems, string $sku) {
                $revenue = $productItems->sum('revenue');
                $quantity = $productItems->sum('quantity');
                $orderCount = $productItems->pluck('order_id')->filter()->unique()->count();
                $avgPrice = $quantity > 0 ? $revenue / $quantity : 0;
                $title = $productItems->first()->get('title');

                return collect([
                    'sku' => $sku,
                    'title' => $title,
                    'quantity' => $quantity,
                    'revenue' => $revenue,
                    'orders' => $orderCount,
                    'avg_price' => $avgPrice,
                ]);
            });

            $skus = $grouped->keys()->all();
            if (!empty($skus)) {
                $products = \App\Models\Product::whereIn('sku', $skus)
                    ->pluck('title', 'sku');

                $grouped = $grouped->map(function (Collection $product, string $sku) use ($products) {
                    if (!$product->get('title') && isset($products[$sku])) {
                        $product->put('title', $products[$sku]);
                    }

                    if (!$product->get('title')) {
                        $product->put('title', 'Unknown Product');
                    }

                    return $product;
                });
            }

            return $grouped
                ->sortByDesc('revenue')
                ->take($limit)
                ->values();
        });
    }

    /**
     * Get daily sales data for chart visualization
     */
    public function dailySalesData(string $period, ?string $startDate = null, ?string $endDate = null): Collection
    {
        $cacheKey = "daily_sales_data_{$period}" . ($startDate ? ':' . $startDate : '') . ($endDate ? ':' . $endDate : '');

        return $this->cache($cacheKey, function () use ($period, $startDate, $endDate) {
            if ($startDate && $endDate) {
                $start = Carbon::parse($startDate)->startOfDay();
                $end = Carbon::parse($endDate)->startOfDay();

                if ($start->greaterThan($end)) {
                    [$start, $end] = [$end, $start];
                }

                return collect(CarbonPeriod::create($start, '1 day', $end))
                    ->map(function (Carbon $date) {
                        return $this->buildDailyBreakdown($date);
                    });
            }

            if ($period === 'yesterday') {
                $date = Carbon::yesterday();
                $dayOrders = $this->data;
                $openOrders = $dayOrders->where('is_processed', false);
                $processedOrders = $dayOrders->where('is_processed', true);

                $revenue = $dayOrders->sum(fn (Order $order) => $this->calculateOrderRevenue($order));
                $openRevenue = $openOrders->sum(fn (Order $order) => $this->calculateOrderRevenue($order));
                $processedRevenue = $processedOrders->sum(fn (Order $order) => $this->calculateOrderRevenue($order));
                $orderCount = $dayOrders->count();

                $itemsCount = $dayOrders->sum(function ($order) {
                    return collect($order->items ?? [])->sum('quantity');
                });

                return collect([
                    collect([
                        'date' => $date->format('M j'),
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
                    ])
                ]);
            }

            $days = (int) max(1, $period);

            return collect(range($days - 1, 0))
                ->map(function (int $daysAgo) {
                    $date = Carbon::now()->subDays($daysAgo);
                    return $this->buildDailyBreakdown($date);
                });
        });
    }

    protected function buildDailyBreakdown(Carbon $date): Collection
    {
        $dayOrders = $this->data->filter(
            fn($order) => $order->received_date?->isSameDay($date)
        );
        $openOrders = $dayOrders->where('is_processed', false);
        $processedOrders = $dayOrders->where('is_processed', true);

        $revenue = $dayOrders->sum(fn (Order $order) => $this->calculateOrderRevenue($order));
        $openRevenue = $openOrders->sum(fn (Order $order) => $this->calculateOrderRevenue($order));
        $processedRevenue = $processedOrders->sum(fn (Order $order) => $this->calculateOrderRevenue($order));
        $orderCount = $dayOrders->count();

        $itemsCount = $dayOrders->sum(function ($order) {
            return collect($order->items ?? [])->sum('quantity');
        });

        return collect([
            'date' => $date->format('M j'),
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
        $cacheKey = 'best_performing_day' . ($startDate ? ':' . $startDate : '') . ($endDate ? ':' . $endDate : '');

        return $this->cache($cacheKey, function () use ($startDate, $endDate) {
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
        });
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
            ->reject(fn($channel) => $channel === 'DIRECT')
            ->sort()
            ->map(fn($channel) => collect([
                'name' => $channel, 
                'label' => ucfirst($channel)
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

        return [
            'labels' => $chartData->pluck('date')->toArray(),
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

        return [
            'labels' => $chartData->pluck('date')->toArray(),
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
    }

    /**
     * Prepare data for items sold trend chart
     */
    public function getItemsSoldChartData(string $period, ?string $startDate = null, ?string $endDate = null): array
    {
        $chartData = $this->dailySalesData($period, $startDate, $endDate);

        return [
            'labels' => $chartData->pluck('date')->toArray(),
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
    }

    /**
     * Prepare data for doughnut charts (with subsource breakdown)
     */
    public function getDoughnutChartData(): array
    {
        return $this->cache('doughnut_chart_data', function () {
            return $this->prepareDoughnutChartData(
                $this->topChannels(),
                'name',
                'revenue'
            );
        });
    }

    /**
     * Prepare data for doughnut charts (grouped by channel only)
     */
    public function getDoughnutChartDataGrouped(): array
    {
        return $this->cache('doughnut_chart_data_grouped', function () {
            return $this->prepareDoughnutChartData(
                $this->topChannelsGrouped(),
                'name',
                'revenue'
            );
        });
    }

    /**
     * Prepare data for order count charts (showing open vs processed as line chart)
     */
    public function getOrderCountChartData(string $period, ?string $startDate = null, ?string $endDate = null): array
    {
        $chartData = $this->dailySalesData($period, $startDate, $endDate);

        return [
            'labels' => $chartData->pluck('date')->toArray(),
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
    }

    /**
     * Prepare data for order status doughnut chart (for analytics page)
     */
    public function getOrderStatusDoughnutData(): array
    {
        return $this->cache('order_status_doughnut_data', function () {
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
        });
    }

    /**
     * Warm up the cache by running all expensive operations
     */
    public function warmUpCache(): void
    {
        $periods = ['1', 'yesterday', '7', '30', '90'];
        
        // Cache basic metrics
        $this->totalRevenue();
        $this->totalOrders();
        $this->averageOrderValue();
        $this->totalItemsSold();
        
        // Cache order status metrics
        $this->totalProcessedOrders();
        $this->totalOpenOrders();
        $this->processedOrdersRevenue();
        $this->openOrdersRevenue();
        
        // Cache top data
        $this->topProducts(5);
        $this->topProducts(10);
        $this->topChannels(6);
        $this->topChannels(10);
        
        // Cache chart data for all common periods
        foreach ($periods as $period) {
            $this->dailySalesData($period);
            $this->getLineChartData($period);
            $this->getBarChartData($period);
            $this->getOrderCountChartData($period);
        }
        
        // Cache other chart data
        $this->getDoughnutChartData();
        $this->getOrderStatusDoughnutData();
        $this->recentOrders(15);
        $this->recentOrders(25);
        $this->availableChannels();
        
        // Mark cache as warmed
        $this->putCache('_cache_status', 'warm');
        $this->putCache('_last_warmed', now()->toISOString());
    }

    private function calculateOrderRevenue(Order $order): float
    {
        $cacheKey = $order->getKey() ?? spl_object_id($order);

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

        if ($order->relationLoaded('orderItems')) {
            $itemsTotal = $order->orderItems->sum(function ($item) {
                $lineTotal = (float) $item->line_total;

                if ($lineTotal > 0) {
                    return $lineTotal;
                }

                $price = (float) $item->price_per_unit;
                $quantity = (int) $item->quantity;

                return $price > 0 && $quantity > 0 ? $price * $quantity : 0.0;
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
