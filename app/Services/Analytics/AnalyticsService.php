<?php

declare(strict_types=1);

namespace App\Services\Analytics;

use App\Factories\Metrics\Sales\SalesFactory;
use App\Models\Order;
use App\ValueObjects\Analytics\AnalyticsFilter;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

final class AnalyticsService
{
    public function __construct(
        private readonly ComparisonEngine $comparisonEngine,
    ) {}

    /**
     * Get filtered orders based on analytics filter
     *
     * NOTE: We do NOT cache raw Order collections - they're too large and cause memory issues.
     * Instead, we cache computed metrics results in the methods below.
     */
    public function getOrders(AnalyticsFilter $filter): Collection
    {
        $query = Order::query()->where('source', '!=', 'DIRECT');

        return $filter->applyToQuery($query)->get();
    }

    /**
     * Get sales metrics for the filtered dataset
     */
    public function getMetrics(AnalyticsFilter $filter): SalesFactory
    {
        return new SalesFactory($this->getOrders($filter));
    }

    /**
     * Get comparison data between current and previous period
     */
    public function getComparison(AnalyticsFilter $filter): ComparisonResult
    {
        $currentOrders = $this->getOrders($filter);
        $previousFilter = $filter->withDateRange($filter->dateRange->getPreviousPeriod());
        $previousOrders = $this->getOrders($previousFilter);

        return $this->comparisonEngine->compare($currentOrders, $previousOrders);
    }

    /**
     * Get channel breakdown for drill-down
     */
    public function getChannelBreakdown(AnalyticsFilter $filter): Collection
    {
        $orders = $this->getOrders($filter);
        $factory = new SalesFactory($orders);

        return $factory->topChannels(limit: 20)->map(function (Collection $channel) use ($filter) {
            return [
                'name' => $channel['name'],
                'subsource' => $channel['subsource'] ?? null,
                'revenue' => $channel['revenue'],
                'orders' => $channel['orders'],
                'avg_order_value' => $channel['avg_order_value'],
                'percentage' => $channel['percentage'],
                // Add drill-down URL
                'drill_down_url' => route('analytics', [
                    ...$filter->toArray(),
                    'channels' => [$channel['name']],
                ]),
            ];
        });
    }

    /**
     * Get product breakdown for drill-down
     */
    public function getProductBreakdown(AnalyticsFilter $filter, int $limit = 20): Collection
    {
        $orders = $this->getOrders($filter);
        $factory = new SalesFactory($orders);

        return $factory->topProducts(limit: $limit)->map(function (Collection $product) {
            return [
                'sku' => $product['sku'],
                'title' => $product['title'],
                'revenue' => $product['revenue'],
                'quantity' => $product['quantity'],
                'orders' => $product['orders'],
                'avg_price' => $product['avg_price'],
                // Add drill-down URL
                'drill_down_url' => route('products.detail', ['sku' => $product['sku']]),
            ];
        });
    }

    /**
     * Get daily trend data for charts
     */
    public function getDailyTrend(AnalyticsFilter $filter): Collection
    {
        $orders = $this->getOrders($filter);
        $factory = new SalesFactory($orders);

        return $factory->dailySalesData();
    }

    /**
     * Get available filter options
     */
    public function getAvailableChannels(): Collection
    {
        return Cache::remember('analytics:available_channels', now()->addHour(), function () {
            return Order::query()
                ->where('source', '!=', 'DIRECT')
                ->distinct()
                ->pluck('source')
                ->filter()
                ->sort()
                ->values();
        });
    }

    /**
     * Get summary statistics
     */
    public function getSummary(AnalyticsFilter $filter): array
    {
        $metrics = $this->getMetrics($filter);

        return [
            'total_revenue' => $metrics->totalRevenue(),
            'total_orders' => $metrics->totalOrders(),
            'avg_order_value' => $metrics->averageOrderValue(),
            'total_items' => $metrics->totalItemsSold(),
            'processed_orders' => $metrics->totalProcessedOrders(),
            'open_orders' => $metrics->totalOpenOrders(),
            'processed_revenue' => $metrics->processedOrdersRevenue(),
            'open_revenue' => $metrics->openOrdersRevenue(),
        ];
    }
}
