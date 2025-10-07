<?php

namespace App\Services\Metrics;

use App\Models\Product;
use App\Services\Metrics\MetricBase;
use Illuminate\Support\Collection;

class ProductsMetrics extends MetricBase
{

    public function __construct(Collection $data)
    {
        parent::__construct($data);
    }

    public function getTopProducts(int $limit = 10): Collection
    {
        return $this->cache("top_products_{$limit}", function () use ($limit) {
            // Analyze orders data to extract product performance metrics
            return $this->data
                ->flatMap(fn($order) => collect($order->items ?? []))
                ->groupBy('sku')
                ->map(function (Collection $items, string $sku) {
                    $product = \App\Models\Product::where('sku', $sku)->first();
                    
                    return collect([
                        'sku' => $sku,
                        'title' => $product?->title ?? 'Unknown Product',
                        'quantity_sold' => $items->sum('quantity'),
                        'total_revenue' => $items->sum('line_total'),
                        'order_count' => $items->count(),
                        'avg_price' => $items->avg('price_per_unit'),
                        'avg_quantity_per_order' => $items->avg('quantity'),
                        'revenue_per_unit' => $items->sum('line_total') / max($items->sum('quantity'), 1),
                    ]);
                })
                ->sortByDesc('total_revenue')
                ->take($limit)
                ->values();
        });
    }
    
    public function getProductPerformanceByRevenue(int $limit = 5): Collection
    {
        return $this->getTopProducts($limit);
    }
    
    public function getProductPerformanceByQuantity(int $limit = 5): Collection
    {
        return $this->getTopProducts(50)
            ->sortByDesc('quantity_sold')
            ->take($limit)
            ->values();
    }
    
    public function getProductPerformanceByOrders(int $limit = 5): Collection
    {
        return $this->getTopProducts(50)
            ->sortByDesc('order_count')
            ->take($limit)
            ->values();
    }
    
    public function getTotalProductsCount(): int
    {
        return $this->cache('total_products_count', function () {
            return $this->data
                ->flatMap(fn($order) => collect($order->items ?? []))
                ->pluck('sku')
                ->unique()
                ->count();
        });
    }
    
    public function getTotalUnitsScheduled(): int
    {
        return $this->cache('total_units_scheduled', function () {
            return $this->data
                ->flatMap(fn($order) => collect($order->items ?? []))
                ->sum('quantity');
        });
    }
    
    public function getAverageUnitsPerProduct(): float
    {
        $totalProducts = $this->getTotalProductsCount();
        
        if ($totalProducts === 0) {
            return 0.0;
        }
        
        return $this->getTotalUnitsScheduled() / $totalProducts;
    }
    
    public function getProductMetricsSummary(): Collection
    {
        return $this->cache('product_metrics_summary', function () {
            return collect([
                'total_products' => $this->getTotalProductsCount(),
                'total_units_sold' => $this->getTotalUnitsScheduled(),
                'avg_units_per_product' => $this->getAverageUnitsPerProduct(),
                'top_product_by_revenue' => $this->getTopProducts(1)->first(),
                'top_product_by_quantity' => $this->getProductPerformanceByQuantity(1)->first(),
            ]);
        });
    }

    /**
     * Warm up the cache by running all expensive operations
     */
    public function warmUpCache(): void
    {
        // Cache basic metrics
        $this->getTotalProductsCount();
        $this->getTotalUnitsScheduled();
        $this->getAverageUnitsPerProduct();
        
        // Cache top products with different limits
        $this->getTopProducts(5);
        $this->getTopProducts(10);
        $this->getTopProducts(25);
        
        // Cache performance metrics
        $this->getProductPerformanceByRevenue(5);
        $this->getProductPerformanceByRevenue(10);
        $this->getProductPerformanceByQuantity(5);
        $this->getProductPerformanceByQuantity(10);
        $this->getProductPerformanceByOrders(5);
        $this->getProductPerformanceByOrders(10);
        
        // Cache summary
        $this->getProductMetricsSummary();
        
        // Mark cache as warmed
        $this->putCache('_cache_status', 'warm');
        $this->putCache('_last_warmed', now()->toISOString());
    }
}