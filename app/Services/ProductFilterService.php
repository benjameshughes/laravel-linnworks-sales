<?php

namespace App\Services;

use App\Enums\ProductFilterType;
use App\Models\Product;
use App\ValueObjects\FilterCriteria;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

readonly class ProductFilterService
{
    public function __construct(
        private ProductBadgeService $badgeService = new ProductBadgeService,
    ) {}

    /**
     * @param  Collection<FilterCriteria>  $filters
     */
    public function applyFilters(Collection $products, Collection $filters, int $period = 30): Collection
    {
        if ($filters->isEmpty()) {
            return $products;
        }

        $activeFilters = $filters->filter(fn (FilterCriteria $filter) => $filter->isActive());

        if ($activeFilters->isEmpty()) {
            return $products;
        }

        return $products->filter(function (array $productData) use ($activeFilters, $period) {
            // Enhance product data with computed metrics for filtering
            $enhancedData = $this->enhanceProductDataForFiltering($productData, $period);

            return $activeFilters->every(
                fn (FilterCriteria $filter) => $filter->matches($enhancedData)
            );
        });
    }

    private function enhanceProductDataForFiltering(array $productData, int $period): array
    {
        $product = $productData['product'] ?? null;

        if (! $product instanceof Product) {
            return $productData;
        }

        // Add computed metrics for filtering
        $enhancedData = $productData + [
            'avg_daily_sales' => ($productData['quantity_sold'] ?? 0) / max($period, 1),
            'created_at' => $product->created_at?->toISOString(),
            'category' => $product->category_name,
            'stock_level' => $product->stock_level ?? 0,
            'stock_minimum' => $product->stock_minimum ?? 0,
            'low_stock_alert' => ($product->stock_level ?? 0) <= ($product->stock_minimum ?? 0),
            'performance_score' => $this->calculatePerformanceScore($productData),
            'growth_rate' => $this->calculateGrowthRate($product->sku, $period),
        ];

        return $enhancedData;
    }

    private function calculatePerformanceScore(array $productData): float
    {
        // Simple weighted performance score algorithm
        $revenue = $productData['total_revenue'] ?? 0;
        $margin = $productData['profit_margin'] ?? 0;
        $quantity = $productData['quantity_sold'] ?? 0;
        $orders = $productData['order_count'] ?? 0;

        // Normalize metrics (0-100 scale)
        $revenueScore = min(($revenue / 1000) * 100, 100); // £1000 = 100 points
        $marginScore = min($margin, 100); // 100% margin = 100 points
        $quantityScore = min(($quantity / 50) * 100, 100); // 50 units = 100 points
        $orderScore = min(($orders / 20) * 100, 100); // 20 orders = 100 points

        // Weighted average
        return ($revenueScore * 0.3) + ($marginScore * 0.3) + ($quantityScore * 0.25) + ($orderScore * 0.15);
    }

    private function calculateGrowthRate(string $sku, int $period): float
    {
        $cacheKey = "growth_rate:{$sku}:{$period}";

        return Cache::remember($cacheKey, now()->addMinutes(15), function () use ($sku, $period) {
            $currentPeriod = [
                'from' => now()->subDays($period),
                'to' => now(),
            ];

            $previousPeriod = [
                'from' => now()->subDays($period * 2),
                'to' => now()->subDays($period),
            ];

            $currentQuantity = \App\Models\OrderItem::where('sku', $sku)
                ->whereHas('order', fn ($query) => $query->whereBetween('received_at', [$currentPeriod['from'], $currentPeriod['to']])
                )
                ->sum('quantity');

            $previousQuantity = \App\Models\OrderItem::where('sku', $sku)
                ->whereHas('order', fn ($query) => $query->whereBetween('received_at', [$previousPeriod['from'], $previousPeriod['to']])
                )
                ->sum('quantity');

            return $previousQuantity > 0
                ? (($currentQuantity - $previousQuantity) / $previousQuantity) * 100
                : 0.0;
        });
    }

    /**
     * @return Collection<string>
     */
    public function getAvailableCategories(): Collection
    {
        return Cache::remember('available_categories', now()->addHour(), function () {
            return Product::whereNotNull('category_name')
                ->where('category_name', '!=', '')
                ->distinct()
                ->pluck('category_name')
                ->sort()
                ->values();
        });
    }

    /**
     * @param  Collection<FilterCriteria>  $filters
     */
    public function getFilterSummary(Collection $filters): Collection
    {
        $activeFilters = $filters->filter(fn (FilterCriteria $filter) => $filter->isActive());

        return collect([
            'total_filters' => $filters->count(),
            'active_filters' => $activeFilters->count(),
            'filter_types' => $activeFilters->pluck('type')->map(fn ($type) => $type->value),
            'summary' => $activeFilters->map(fn (FilterCriteria $filter) => collect([
                'type' => $filter->type->value,
                'label' => $filter->label(),
                'value' => $filter->getDisplayValue(),
            ])),
        ]);
    }

    /**
     * @return Collection<FilterCriteria>
     */
    public function createDefaultFilters(): Collection
    {
        return collect([
            new FilterCriteria(ProductFilterType::PROFIT_MARGIN, null),
            new FilterCriteria(ProductFilterType::SALES_VELOCITY, null),
            new FilterCriteria(ProductFilterType::GROWTH_RATE, null),
            new FilterCriteria(ProductFilterType::REVENUE_TIER, null),
            new FilterCriteria(ProductFilterType::BADGE_TYPE, null),
            new FilterCriteria(ProductFilterType::CATEGORY, null),
            new FilterCriteria(ProductFilterType::STOCK_STATUS, null),
        ]);
    }

    /**
     * @param  array<string, mixed>  $filterData
     * @return Collection<FilterCriteria>
     */
    public function createFiltersFromArray(array $filterData): Collection
    {
        return collect($filterData)->map(function ($value, $typeString) {
            $type = ProductFilterType::tryFrom($typeString);

            if (! $type) {
                return null;
            }

            return new FilterCriteria($type, $value);
        })->filter();
    }

    public function getFilterPresets(): Collection
    {
        return collect([
            'top-performers' => collect([
                'label' => 'Top Performers',
                'description' => 'High margin, fast selling products',
                'icon' => 'star',
                'filters' => collect([
                    ProductFilterType::PROFIT_MARGIN->value => 'high',
                    ProductFilterType::SALES_VELOCITY->value => 'fast',
                ]),
            ]),
            'growth-opportunities' => collect([
                'label' => 'Growth Opportunities',
                'description' => 'Growing products with good margins',
                'icon' => 'trending-up',
                'filters' => collect([
                    ProductFilterType::GROWTH_RATE->value => 'growing',
                    ProductFilterType::PROFIT_MARGIN->value => 'medium',
                ]),
            ]),
            'needs-attention' => collect([
                'label' => 'Needs Attention',
                'description' => 'Declining or low-performing products',
                'icon' => 'exclamation-triangle',
                'filters' => collect([
                    ProductFilterType::GROWTH_RATE->value => 'declining',
                ]),
            ]),
            'new-products' => collect([
                'label' => 'New Products',
                'description' => 'Recently added products',
                'icon' => 'sparkles',
                'filters' => collect([
                    ProductFilterType::PRODUCT_AGE->value => 'new',
                ]),
            ]),
            'cash-cows' => collect([
                'label' => 'Cash Cows',
                'description' => 'High revenue, consistent sellers',
                'icon' => 'currency-pound',
                'filters' => collect([
                    ProductFilterType::REVENUE_TIER->value => 'high',
                    ProductFilterType::BADGE_TYPE->value => collect(['consistent', 'hot-seller']),
                ]),
            ]),
        ]);
    }
}
