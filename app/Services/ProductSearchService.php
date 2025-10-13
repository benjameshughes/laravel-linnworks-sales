<?php

namespace App\Services;

use App\Enums\SearchType;
use App\Models\Product;
use App\ValueObjects\SearchCriteria;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Laravel\Scout\Builder;

readonly class ProductSearchService
{
    public function __construct(
        private int $defaultLimit = 50,
        private int $autocompleteLimit = 8,
        private int $cacheTtl = 900, // 15 minutes
    ) {}

    /**
     * Perform a product search using Scout
     */
    public function search(SearchCriteria $criteria): EloquentCollection
    {
        if ($criteria->isEmpty()) {
            return $this->getAllProducts($criteria);
        }

        $cacheKey = $this->getCacheKey('search', $criteria);

        return Cache::remember($cacheKey, $this->cacheTtl, function () use ($criteria) {
            return $this->performSearch($criteria);
        });
    }

    /**
     * Get autocomplete suggestions
     */
    public function autocomplete(string $query, SearchType $type = SearchType::COMBINED): Collection
    {
        if (strlen(trim($query)) < 2) {
            return collect();
        }

        $criteria = new SearchCriteria(
            query: $query,
            type: $type,
            limit: $this->autocompleteLimit,
            fuzzySearch: false, // Disable fuzzy for autocomplete
        );

        $cacheKey = $this->getCacheKey('autocomplete', $criteria);

        return Cache::remember($cacheKey, $this->cacheTtl / 3, function () use ($criteria) {
            $products = $this->performSearch($criteria);

            return $criteria->getSuggestions($products);
        });
    }

    /**
     * Search products by category
     */
    public function searchByCategory(string $category, ?string $query = null): EloquentCollection
    {
        $criteria = new SearchCriteria(
            query: $query ?? '',
            type: SearchType::CATEGORY,
            filters: ['category_name' => $category],
        );

        return $this->search($criteria);
    }

    /**
     * Search products by brand
     */
    public function searchByBrand(string $brand, ?string $query = null): EloquentCollection
    {
        $criteria = new SearchCriteria(
            query: $query ?? '',
            type: SearchType::BRAND,
            filters: ['brand' => $brand],
        );

        return $this->search($criteria);
    }

    /**
     * Find product by SKU (exact match)
     */
    public function findBySku(string $sku): ?Product
    {
        $cacheKey = "product_search:sku:{$sku}";

        return Cache::remember($cacheKey, $this->cacheTtl, function () use ($sku) {
            return Product::where('sku', $sku)->first();
        });
    }

    /**
     * Find products by multiple SKUs
     */
    public function findBySkus(array $skus): EloquentCollection
    {
        $cacheKey = 'product_search:skus:'.md5(implode(',', $skus));

        return Cache::remember($cacheKey, $this->cacheTtl, function () use ($skus) {
            return Product::whereIn('sku', $skus)->get();
        });
    }

    /**
     * Get trending search terms
     */
    public function getTrendingSearches(int $limit = 10): Collection
    {
        $cacheKey = "product_search:trending:{$limit}";

        return Cache::remember($cacheKey, $this->cacheTtl * 4, function () use ($limit) {
            // This would ideally come from search analytics
            // For now, return popular categories and brands
            return collect([
                ['term' => 'Electronics', 'type' => 'category', 'count' => 245],
                ['term' => 'Clothing', 'type' => 'category', 'count' => 189],
                ['term' => 'Books', 'type' => 'category', 'count' => 156],
                ['term' => 'Home & Garden', 'type' => 'category', 'count' => 134],
                ['term' => 'Sports', 'type' => 'category', 'count' => 98],
            ])->take($limit);
        });
    }

    /**
     * Get search suggestions for empty query
     */
    public function getPopularProducts(int $limit = 10): EloquentCollection
    {
        $cacheKey = "product_search:popular:{$limit}";

        return Cache::remember($cacheKey, $this->cacheTtl * 2, function () use ($limit) {
            return Product::where('is_active', true)
                ->where('stock_level', '>', 0)
                ->orderByDesc('updated_at') // Could be replaced with popularity score
                ->limit($limit)
                ->get();
        });
    }

    /**
     * Advanced search with multiple criteria
     */
    public function advancedSearch(array $params): EloquentCollection
    {
        $criteria = new SearchCriteria(
            query: $params['query'] ?? '',
            type: SearchType::tryFrom($params['type'] ?? 'combined') ?? SearchType::COMBINED,
            fuzzySearch: $params['fuzzy'] ?? true,
            exactMatch: $params['exact'] ?? false,
            limit: $params['limit'] ?? $this->defaultLimit,
            filters: $params['filters'] ?? [],
            sortBy: $params['sort_by'] ?? null,
            sortDirection: $params['sort_direction'] ?? 'desc',
            includeInactive: $params['include_inactive'] ?? false,
            includeOutOfStock: $params['include_out_of_stock'] ?? true,
        );

        return $this->search($criteria);
    }

    /**
     * Build Scout search query
     */
    private function buildScoutQuery(SearchCriteria $criteria): Builder
    {
        $query = Product::search($criteria->getProcessedQuery());

        // Apply Scout options
        $scoutOptions = $criteria->getScoutQuery();
        if (! empty($scoutOptions)) {
            $query->options($scoutOptions);
        }

        // Apply Eloquent constraints
        $query->query($criteria->getEloquentConstraints());

        return $query;
    }

    /**
     * Perform the actual search
     */
    private function performSearch(SearchCriteria $criteria): EloquentCollection
    {
        if ($criteria->isEmpty()) {
            return $this->getAllProducts($criteria);
        }

        $query = $this->buildScoutQuery($criteria);

        // Apply limit
        if ($criteria->limit) {
            $query = $query->take($criteria->limit);
        }

        $results = $query->get();

        // Apply additional sorting if needed
        if ($criteria->sortBy && $results->isNotEmpty()) {
            $results = $results->sortBy(function ($product) use ($criteria) {
                return match ($criteria->sortBy) {
                    'relevance' => 0, // Scout handles relevance
                    'title' => $product->title,
                    'sku' => $product->sku,
                    'price' => $product->retail_price,
                    'stock' => $product->stock_level,
                    'created_at' => $product->created_at,
                    default => $product->{$criteria->sortBy} ?? 0,
                };
            }, SORT_REGULAR, $criteria->sortDirection === 'desc');
        }

        return $results instanceof EloquentCollection ? $results : collect($results)->toBase();
    }

    /**
     * Get all products when no search query
     */
    private function getAllProducts(SearchCriteria $criteria): EloquentCollection
    {
        $query = Product::query();

        // Apply constraints
        $constraintsFn = $criteria->getEloquentConstraints();
        $constraintsFn($query);

        // Apply sorting
        if ($criteria->sortBy) {
            $query->orderBy($criteria->sortBy, $criteria->sortDirection);
        } else {
            $query->orderByDesc('updated_at');
        }

        // Apply limit
        if ($criteria->limit) {
            $query->limit($criteria->limit);
        }

        return $query->get();
    }

    /**
     * Generate cache key for search results
     */
    private function getCacheKey(string $type, SearchCriteria $criteria): string
    {
        $hash = md5(serialize($criteria->toArray()));

        return "product_search:{$type}:{$hash}";
    }

    /**
     * Clear search cache
     */
    public function clearCache(?string $pattern = null): void
    {
        if ($pattern) {
            Cache::forget("product_search:{$pattern}");
        } else {
            // Clear all search cache (would need Redis SCAN in production)
            Cache::flush();
        }
    }

    /**
     * Warm up search cache with common queries
     */
    public function warmUpCache(): void
    {
        $commonQueries = [
            '',
            'electronics',
            'clothing',
            'books',
            'phone',
            'laptop',
        ];

        foreach ($commonQueries as $query) {
            $criteria = new SearchCriteria(
                query: $query,
                limit: $this->defaultLimit,
            );

            $this->search($criteria);
        }

        // Warm up autocomplete
        foreach (['el', 'ph', 'la', 'bo'] as $prefix) {
            $this->autocomplete($prefix);
        }
    }

    /**
     * Get search analytics data
     */
    public function getSearchAnalytics(): array
    {
        return [
            'total_products' => Product::count(),
            'searchable_products' => Product::where('is_active', true)->count(),
            'indexed_products' => Product::where('is_active', true)->whereNotNull('sku')->count(),
            'popular_searches' => $this->getTrendingSearches(5),
            'cache_status' => [
                'enabled' => true,
                'ttl' => $this->cacheTtl,
            ],
        ];
    }

    /**
     * Re-index all products for search
     */
    public function reindexAll(): int
    {
        $products = Product::where('is_active', true)->get();
        $products->searchable();

        return $products->count();
    }

    /**
     * Remove product from search index
     */
    public function removeFromIndex(Product $product): void
    {
        $product->unsearchable();
    }

    /**
     * Add product to search index
     */
    public function addToIndex(Product $product): void
    {
        if ($product->shouldBeSearchable()) {
            $product->searchable();
        }
    }
}
