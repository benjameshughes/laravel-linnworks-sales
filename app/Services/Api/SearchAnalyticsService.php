<?php

namespace App\Services\Api;

use App\Services\ProductSearchService;
use App\ValueObjects\Api\ApiResponse;
use App\Enums\SearchType;
use Illuminate\Support\Collection;

readonly class SearchAnalyticsService
{
    public function __construct(
        private ProductSearchService $searchService
    ) {}

    public function getTrending(int $limit = 10): ApiResponse
    {
        $trendingSearches = $this->searchService->getTrendingSearches($limit);
        $popularProducts = $this->searchService->getPopularProducts($limit);

        return ApiResponse::success(
            data: collect([
                'trending_searches' => $trendingSearches,
                'popular_products' => $this->transformPopularProducts($popularProducts),
            ]),
            meta: collect([
                'limit' => $limit,
                'generated_at' => now()->toISOString(),
            ])
        );
    }

    public function getAnalytics(): ApiResponse
    {
        $analytics = $this->searchService->getSearchAnalytics();

        return ApiResponse::success(
            data: collect([
                'analytics' => $analytics,
                'search_types' => $this->transformSearchTypes(),
            ]),
            meta: collect([
                'generated_at' => now()->toISOString(),
            ])
        );
    }

    public function reindexAll(): ApiResponse
    {
        $count = $this->searchService->reindexAll();

        return ApiResponse::success(
            data: collect([
                'message' => 'Search index refreshed successfully',
                'indexed_products' => $count,
            ]),
            meta: collect([
                'timestamp' => now()->toISOString(),
            ])
        );
    }

    public function clearCache(?string $pattern = null): ApiResponse
    {
        $this->searchService->clearCache($pattern);

        return ApiResponse::success(
            data: collect([
                'message' => 'Search cache cleared successfully',
                'pattern' => $pattern ?? 'all',
            ]),
            meta: collect([
                'timestamp' => now()->toISOString(),
            ])
        );
    }

    private function transformPopularProducts(Collection $products): Collection
    {
        return $products->map(fn($product) => collect([
            'id' => $product->id,
            'sku' => $product->sku,
            'title' => $product->title,
            'category' => $product->category_name,
            'price' => $product->retail_price,
            'url' => route('products.detail', $product->sku),
        ]));
    }

    private function transformSearchTypes(): Collection
    {
        return collect(SearchType::cases())->map(fn($type) => collect([
            'value' => $type->value,
            'label' => $type->label(),
            'placeholder' => $type->getPlaceholder(),
            'icon' => $type->getIcon(),
            'supports_fuzzy' => $type->supportsFuzzySearch(),
            'supports_wildcards' => $type->supportsWildcards(),
        ]));
    }
}