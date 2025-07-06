<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Product;
use App\Repositories\ProductRepository;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

final class ProductAnalyticsService
{
    private const CACHE_TTL = 300; // 5 minutes
    private const CACHE_PREFIX = 'product_analytics';

    public function __construct(
        private readonly ProductRepository $productRepository
    ) {}

    public function getMetrics(int $period = 30, ?string $search = null, ?string $category = null): array
    {
        $cacheKey = $this->getCacheKey('metrics', $period, $search, $category);
        
        return Cache::remember($cacheKey, self::CACHE_TTL, function() use ($period, $search, $category) {
            // Get products with complete data for profit calculations
            $products = $this->getTopSellingProducts($period, $search, $category, 1000);
            
            // Get basic stats
            $categories = $this->getTopCategories($period);
            $stockAlerts = $this->getStockAlerts();
            
            return [
                'total_products' => $products->count(),
                'total_units_sold' => $products->sum('total_sold'),
                'total_revenue' => $products->sum('total_revenue'),
                'avg_profit_margin' => $products->where('total_revenue', '>', 0)->avg('profit_margin_percent') ?? 0,
                'top_performing_sku' => $products->first()['product']->sku ?? null,
                'categories_count' => $categories->count(),
                'low_stock_count' => $stockAlerts->count(),
            ];
        });
    }

    public function getTopSellingProducts(int $period = 30, ?string $search = null, ?string $category = null, int $limit = 20): Collection
    {
        $cacheKey = $this->getCacheKey('top_products', $period, $search, $category, $limit);
        
        return Cache::remember($cacheKey, self::CACHE_TTL, function() use ($period, $search, $category, $limit) {
            // Get active products using repository - increased limit to capture more products
            $products = $this->productRepository->getActiveProducts($search, $category, 5000);
            
            if ($products->isEmpty()) {
                return collect();
            }
            
            // Get bulk sales data for all products at once
            $skus = $products->pluck('sku')->toArray();
            $salesData = $this->productRepository->getBulkProductSalesData($skus);
            
            // Combine product info with sales data
            return $products->map(function($product) use ($salesData) {
                $sales = $salesData->get($product->sku, [
                    'total_sold' => 0,
                    'total_revenue' => 0,
                    'avg_selling_price' => 0,
                    'order_count' => 0,
                ]);
                
                $totalCost = $sales['total_sold'] * ($product->purchase_price ?? 0);
                $totalProfit = $sales['total_revenue'] - $totalCost;
                $profitMargin = $sales['total_revenue'] > 0 ? ($totalProfit / $sales['total_revenue']) * 100 : 0;
                
                return [
                    'product' => $product,
                    'total_sold' => $sales['total_sold'],
                    'total_revenue' => $sales['total_revenue'],
                    'total_profit' => $totalProfit,
                    'profit_margin_percent' => $profitMargin,
                    'avg_selling_price' => $sales['avg_selling_price'],
                    'purchase_price' => $product->purchase_price,
                    'order_count' => $sales['order_count'],
                ];
            })
            ->sortByDesc('total_revenue')
            ->take($limit)
            ->values();
        });
    }

    public function getTopCategories(int $period = 30): Collection
    {
        $cacheKey = $this->getCacheKey('top_categories', $period);
        
        return Cache::remember($cacheKey, self::CACHE_TTL, function() use ($period) {
            // Use repository's optimized query for category sales data
            return $this->productRepository->getCategorySalesData()
                ->filter(fn($cat) => $cat['total_revenue'] > 0)
                ->take(10)
                ->values();
        });
    }

    public function getStockAlerts(): Collection
    {
        return Cache::remember('stock_alerts', self::CACHE_TTL, function() {
            return $this->productRepository->getLowStockProducts(10)
                ->map(function($product) {
                    return [
                        'product' => $product,
                        'stock_level' => $product->stock_available,
                        'stock_minimum' => $product->stock_minimum,
                        'percentage' => $product->stock_minimum > 0 
                            ? ($product->stock_available / $product->stock_minimum) * 100 
                            : 0,
                    ];
                });
        });
    }

    public function getProductDetails(string $sku): ?array
    {
        $cacheKey = $this->getCacheKey('product_details', $sku);
        
        return Cache::remember($cacheKey, self::CACHE_TTL, function() use ($sku) {
            $product = $this->productRepository->findBySku($sku);
            
            if (!$product) {
                return null;
            }
            
            // Calculate sales data and channel performance  
            $salesData = $this->productRepository->getProductSalesData($sku);
            $channelPerformance = $this->productRepository->getProductChannelPerformance($sku);
            
            // Calculate profit analysis
            $totalCost = $salesData['total_sold'] * ($product->purchase_price ?? 0);
            $totalProfit = $salesData['total_revenue'] - $totalCost;
            $profitMargin = $salesData['total_revenue'] > 0 ? ($totalProfit / $salesData['total_revenue']) * 100 : 0;
            
            $profitAnalysis = [
                'total_sold' => $salesData['total_sold'],
                'total_revenue' => $salesData['total_revenue'],
                'total_cost' => $totalCost,
                'total_profit' => $totalProfit,
                'profit_margin_percent' => $profitMargin,
                'avg_selling_price' => $salesData['avg_selling_price'],
                'purchase_price' => $product->purchase_price,
            ];
            
            return [
                'product' => $product,
                'profit_analysis' => $profitAnalysis,
                'channel_performance' => $channelPerformance,
                'stock_info' => [
                    'current_stock' => $product->stock_available,
                    'minimum_stock' => $product->stock_minimum,
                    'in_orders' => $product->stock_in_orders,
                    'due_stock' => $product->stock_due,
                ]
            ];
        });
    }

    public function getProductSalesChart(string $sku, int $period = 30): array
    {
        $cacheKey = $this->getCacheKey('sales_chart', $sku, $period);
        
        return Cache::remember($cacheKey, self::CACHE_TTL, function() use ($sku, $period) {
            $product = $this->productRepository->findBySku($sku);
            if (!$product) {
                return [];
            }
            
            $startDate = Carbon::now()->subDays($period - 1)->startOfDay();
            
            // Get daily sales data using repository
            $dailySales = $this->productRepository->getProductDailySales($sku, $startDate);
            
            // Generate complete date range
            $salesData = [];
            for ($i = $period - 1; $i >= 0; $i--) {
                $date = Carbon::now()->subDays($i);
                $dateKey = $date->format('Y-m-d');
                $dayData = $dailySales->get($dateKey);
                
                $salesData[] = [
                    'date' => $date->format('M j'),
                    'quantity' => $dayData->quantity ?? 0,
                    'revenue' => $dayData->revenue ?? 0,
                ];
            }
            
            return $salesData;
        });
    }

    public function invalidateCache(?string $pattern = null): void
    {
        // For cache stores that don't support tagging, we'll clear specific keys
        $keysToForget = [
            'stock_alerts',
            // Add more specific keys as needed
        ];
        
        foreach ($keysToForget as $key) {
            Cache::forget($key);
        }
        
        // For pattern-based invalidation, we'd need to implement a key tracking system
        // For now, we'll just clear the common keys
    }
    
    /**
     * Pre-warm cache for common queries
     * This method can be called by scheduled commands to ensure fast dashboard loading
     */
    public function prewarmCache(array $periods = [1, 7, 30, 90]): array
    {
        $warmed = [];
        
        foreach ($periods as $period) {
            // Warm general metrics
            $this->getMetrics($period);
            $warmed[] = "metrics_p{$period}";
            
            // Warm top products
            $this->getTopSellingProducts($period, null, null, 50);
            $warmed[] = "top_products_p{$period}";
            
            // Warm categories
            $this->getTopCategories($period);
            $warmed[] = "categories_p{$period}";
        }
        
        // Always warm stock alerts
        $this->getStockAlerts();
        $warmed[] = 'stock_alerts';
        
        return $warmed;
    }

    public function invalidateProductCache(string $sku): void
    {
        // Generate the same cache keys that would be used for this product
        $productDetailKey = $this->getCacheKey('product_details', $sku);
        $salesChartKeys = [];
        
        // Generate chart keys for common periods
        foreach ([7, 30, 90, 365] as $period) {
            $salesChartKeys[] = $this->getCacheKey('sales_chart', $sku, $period);
        }
        
        // Forget specific product caches
        Cache::forget($productDetailKey);
        foreach ($salesChartKeys as $key) {
            Cache::forget($key);
        }
        
        // Also clear general caches since they depend on product data
        Cache::forget('stock_alerts');
        
        // Clear metric and category caches (they would need regeneration anyway)
        // We could be more surgical here but for simplicity, clear common patterns
    }

    private function getCacheKey(string $type, ...$params): string
    {
        $cleanParams = array_filter($params, fn($p) => $p !== null && $p !== '');
        return self::CACHE_PREFIX . '_' . $type . '_' . md5(serialize($cleanParams));
    }

    /**
     * Check if the cache store supports tagging
     */
    private function supportsTagging(): bool
    {
        return method_exists(Cache::getStore(), 'tags');
    }

    /**
     * Remember with tags if supported, otherwise use regular cache
     */
    private function cacheRemember(string $key, int $ttl, callable $callback, array $tags = [])
    {
        if ($this->supportsTagging() && !empty($tags)) {
            return Cache::tags($tags)->remember($key, $ttl, $callback);
        }
        
        return Cache::remember($key, $ttl, $callback);
    }

    /**
     * Enhanced cache invalidation
     */
    private function forgetCacheWithTags(string $key, array $tags = []): void
    {
        if ($this->supportsTagging() && !empty($tags)) {
            Cache::tags($tags)->forget($key);
        } else {
            Cache::forget($key);
        }
    }
}