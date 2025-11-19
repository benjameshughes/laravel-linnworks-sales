<?php

declare(strict_types=1);

namespace App\Services\Metrics;

use App\Models\Product;
use App\Services\ProductBadgeService;
use App\Traits\PreparesChartData;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class ProductMetrics extends MetricBase
{
    use PreparesChartData;

    public function __construct(
        Collection $data,
        private readonly ProductBadgeService $badgeService = new ProductBadgeService,
    ) {
        parent::__construct($data);
    }

    /**
     * Get total products sold (quantity)
     */
    public function totalProductsSold(): int
    {
        return $this->data->sum('quantity');
    }

    /**
     * Get total product revenue
     */
    public function totalProductRevenue(): float
    {
        return (float) $this->data->sum('total_price');
    }

    /**
     * Get average product price
     */
    public function averageProductPrice(): float
    {
        return $this->data->count() > 0 ? $this->data->avg('unit_price') : 0;
    }

    /**
     * Get number of unique products
     */
    public function uniqueProducts(): int
    {
        return $this->data->pluck('sku')->unique()->count();
    }

    /**
     * Get products by sales performance
     */
    public function topProductsBySales(int $limit = 10): Collection
    {
        return $this->data
            ->groupBy('sku')
            ->map(function (Collection $items, string $sku) {
                $firstItem = $items->first();
                $product = Product::where('sku', $sku)->first();

                $badges = $product ? $this->badgeService->getProductBadges($product) : collect();

                return collect([
                    'sku' => $sku,
                    'title' => $product?->title ?? 'Unknown Product',
                    'category' => $product?->category_name ?? 'Unknown Category',
                    'quantity_sold' => $items->sum('quantity'),
                    'revenue' => $items->sum('total_price'),
                    'order_count' => $items->count(),
                    'avg_price' => $items->avg('unit_price'),
                    'stock_level' => $product?->stock_available ?? 0,
                    'profit_margin' => $product?->getProfitMargin() ?? 0,
                    'badges' => $badges,
                    'product' => $product,
                ]);
            })
            ->sortByDesc('quantity_sold')
            ->take($limit)
            ->values();
    }

    /**
     * Get products by revenue performance
     */
    public function topProductsByRevenue(int $limit = 10): Collection
    {
        return $this->data
            ->groupBy('sku')
            ->map(function (Collection $items, string $sku) {
                $firstItem = $items->first();
                $product = Product::where('sku', $sku)->first();

                $badges = $product ? $this->badgeService->getProductBadges($product) : collect();

                return collect([
                    'sku' => $sku,
                    'title' => $product?->title ?? 'Unknown Product',
                    'category' => $product?->category_name ?? 'Unknown Category',
                    'quantity_sold' => $items->sum('quantity'),
                    'revenue' => $items->sum('total_price'),
                    'order_count' => $items->count(),
                    'avg_price' => $items->avg('unit_price'),
                    'stock_level' => $product?->stock_available ?? 0,
                    'profit_margin' => $product?->getProfitMargin() ?? 0,
                    'badges' => $badges,
                    'product' => $product,
                ]);
            })
            ->sortByDesc('revenue')
            ->take($limit)
            ->values();
    }

    /**
     * Get product performance by category
     */
    public function productsByCategory(): Collection
    {
        return $this->data
            ->map(function ($item) {
                $product = Product::where('sku', $item['sku'])->first();
                $item['category_name'] = $product?->category_name ?? 'Unknown Category';

                return $item;
            })
            ->groupBy('category_name')
            ->map(function (Collection $items, string $category) {
                return collect([
                    'category' => $category,
                    'product_count' => $items->pluck('sku')->unique()->count(),
                    'quantity_sold' => $items->sum('quantity'),
                    'revenue' => $items->sum('total_price'),
                    'avg_price' => $items->avg('unit_price'),
                ]);
            })
            ->sortByDesc('revenue')
            ->values();
    }

    /**
     * Get low stock products that are selling
     */
    public function lowStockSellers(): Collection
    {
        return $this->data
            ->groupBy('sku')
            ->map(function (Collection $items, string $sku) {
                $product = Product::where('sku', $sku)->first();

                if (! $product || $product->stock_available > $product->stock_minimum) {
                    return null;
                }

                return collect([
                    'sku' => $sku,
                    'title' => $product->title ?? 'Unknown Product',
                    'stock_available' => $product->stock_available,
                    'stock_minimum' => $product->stock_minimum,
                    'quantity_sold' => $items->sum('quantity'),
                    'revenue' => $items->sum('total_price'),
                    'urgency_score' => $items->sum('quantity') / max($product->stock_available, 1),
                ]);
            })
            ->filter()
            ->sortByDesc('urgency_score')
            ->values();
    }

    /**
     * Get products sales trends over time
     */
    public function productSalesTrends(string $period = '30'): Collection
    {
        $days = (int) $period;

        return collect(range($days - 1, 0))
            ->map(function (int $daysAgo) {
                $date = Carbon::now()->subDays($daysAgo);
                $dayItems = $this->data->filter(function ($item) use ($date) {
                    return isset($item['received_at']) &&
                           Carbon::parse($item['received_at'])->isSameDay($date);
                });

                return collect([
                    'date' => $date->format('M j, Y'),
                    'day' => $date->format('D'),
                    'products_sold' => $dayItems->sum('quantity'),
                    'revenue' => $dayItems->sum('total_price'),
                    'unique_products' => $dayItems->pluck('sku')->unique()->count(),
                    'avg_price' => $dayItems->avg('unit_price') ?? 0,
                ]);
            });
    }

    /**
     * Get chart data for product sales over time
     */
    public function getProductSalesChartData(string $period = '30'): array
    {
        $chartData = $this->productSalesTrends($period);

        return [
            'labels' => $chartData->pluck('date')->toArray(),
            'datasets' => [
                [
                    'label' => 'Products Sold',
                    'data' => $chartData->pluck('products_sold')->toArray(),
                    'borderColor' => '#3B82F6',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                    'tension' => 0.4,
                    'fill' => true,
                    'borderWidth' => 2,
                ],
                [
                    'label' => 'Revenue (£)',
                    'data' => $chartData->pluck('revenue')->toArray(),
                    'borderColor' => '#10B981',
                    'backgroundColor' => 'rgba(16, 185, 129, 0.1)',
                    'tension' => 0.4,
                    'fill' => true,
                    'borderWidth' => 2,
                    'yAxisID' => 'y1',
                ],
            ],
        ];
    }

    /**
     * Get doughnut chart data for category distribution
     */
    public function getCategoryDistributionChart(): array
    {
        $categories = $this->productsByCategory()->take(8);

        return [
            'labels' => $categories->pluck('category')->toArray(),
            'datasets' => [[
                'label' => 'Revenue by Category',
                'data' => $categories->pluck('revenue')->toArray(),
                'backgroundColor' => [
                    '#3B82F6', '#10B981', '#F59E0B', '#EF4444',
                    '#8B5CF6', '#F97316', '#06B6D4', '#84CC16',
                ],
                'borderWidth' => 2,
            ]],
        ];
    }
}
