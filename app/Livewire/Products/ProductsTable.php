<?php

declare(strict_types=1);

namespace App\Livewire\Products;

use Livewire\Component;
use App\Enums\SearchType;
use Livewire\Attributes\On;
use Livewire\WithPagination;
use Livewire\Attributes\Computed;
use Illuminate\Support\Collection;
use Illuminate\Contracts\View\View;
use App\ValueObjects\SearchCriteria;
use App\Services\ProductBadgeService;
use App\Services\ProductFilterService;
use App\Services\ProductSearchService;
use App\Services\ProductAnalyticsService;

/**
 * Products Table Island
 *
 * Main product listing with sorting and pagination.
 * Listens for 'products-filters-updated' event to refresh data.
 *
 * @property-read Collection $topSellingProducts
 */
final class ProductsTable extends Component
{
    use WithPagination;

    private const TOP_PRODUCTS_LIMIT = 20;

    private ?ProductAnalyticsService $productAnalyticsService = null;

    private ?ProductBadgeService $productBadgeService = null;

    private ?ProductFilterService $productFilterService = null;

    private ?ProductSearchService $productSearchService = null;

    /**
     * Livewire cannot inject through the constructor, so dependencies are
     * resolved here - boot() runs on every request, mount and hydrate alike.
     */
    public function boot(ProductAnalyticsService $productAnalyticsService, ProductBadgeService $productBadgeService, ProductFilterService $productFilterService, ProductSearchService $productSearchService): void
    {
        $this->productAnalyticsService = $productAnalyticsService;
        $this->productBadgeService = $productBadgeService;
        $this->productFilterService = $productFilterService;
        $this->productSearchService = $productSearchService;
    }

    /**
     * Livewire skips boot() on the lazy-load request, so always reach the
     * dependency through here rather than the property directly.
     */
    private function productAnalyticsService(): ProductAnalyticsService
    {
        return $this->productAnalyticsService ??= app(ProductAnalyticsService::class);
    }

    /**
     * Livewire skips boot() on the lazy-load request, so always reach the
     * dependency through here rather than the property directly.
     */
    private function productBadgeService(): ProductBadgeService
    {
        return $this->productBadgeService ??= app(ProductBadgeService::class);
    }

    /**
     * Livewire skips boot() on the lazy-load request, so always reach the
     * dependency through here rather than the property directly.
     */
    private function productFilterService(): ProductFilterService
    {
        return $this->productFilterService ??= app(ProductFilterService::class);
    }

    /**
     * Livewire skips boot() on the lazy-load request, so always reach the
     * dependency through here rather than the property directly.
     */
    private function productSearchService(): ProductSearchService
    {
        return $this->productSearchService ??= app(ProductSearchService::class);
    }

    public string $period = '30';

    public string $search = '';

    public string $searchType = 'combined';

    public ?string $selectedCategory = null;

    public bool $showOnlyWithSales = true;

    /** @var array<string, mixed> */
    public array $filters = [];

    public bool $exactMatch = false;

    public bool $fuzzySearch = true;

    public string $sortBy = 'revenue';

    public string $sortDirection = 'desc';

    public ?string $selectedProduct = null;

    public function mount(): void
    {
        $this->period = request('period', '30');
    }

    #[On('products-filters-updated')]
    public function updateFilters(
        string $period,
        string $search = '',
        string $searchType = 'combined',
        ?string $selectedCategory = null,
        bool $showOnlyWithSales = true,
        array $filters = [],
        bool $exactMatch = false,
        bool $fuzzySearch = true
    ): void {
        $this->period = $period;
        $this->search = $search;
        $this->searchType = $searchType;
        $this->selectedCategory = $selectedCategory;
        $this->showOnlyWithSales = $showOnlyWithSales;
        $this->filters = $filters;
        $this->exactMatch = $exactMatch;
        $this->fuzzySearch = $fuzzySearch;

        $this->resetPage();

        // Clear cached computed properties
        unset($this->topSellingProducts);
        unset($this->products);
    }

    public function sortBy(string $column): void
    {
        if ($this->sortBy === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDirection = 'desc';
        }

        $this->resetPage();
    }

    public function selectProduct(string $sku): void
    {
        $this->selectedProduct = $sku;
        $this->dispatch('product-selected', sku: $sku);
    }

    public function clearSelection(): void
    {
        $this->selectedProduct = null;
        $this->dispatch('product-selection-cleared');
    }

    #[Computed]
    public function topSellingProducts(): Collection
    {
        // Use enhanced search if there's a search query
        if (! empty($this->search)) {
            return $this->performEnhancedSearch();
        }

        $products = $this->productAnalyticsService()->getTopSellingProducts(
            period: (int) $this->period,
            search: null,
            category: $this->selectedCategory,
            limit: 100
        );

        // Filter products based on sales if needed
        if ($this->showOnlyWithSales) {
            $products = $products->filter(fn ($item) => $item['total_sold'] > 0);
        }

        // Apply custom filters
        $products = $this->applyFilters($products);

        // Add badges to each product
        $products = $products->map(function ($item) {
            $badges = $this->productBadgeService()->getProductBadges($item['product'], (int) $this->period);
            $item['badges'] = $badges->map(fn ($badge) => $badge->toArray());

            return $item;
        });

        // Apply sorting based on UI selection
        return $products->sortBy(function ($item) {
            return match ($this->sortBy) {
                'quantity' => $item['total_sold'],
                'revenue' => $item['total_revenue'],
                'profit' => $item['total_profit'],
                'margin' => $item['profit_margin_percent'],
                'price' => $item['avg_selling_price'],
                'name' => $item['product']->title,
                default => $item['total_revenue'],
            };
        }, SORT_REGULAR, $this->sortDirection === 'desc')
            ->values();
    }

    private function performEnhancedSearch(): Collection
    {
        $searchService = $this->productSearchService();
        $searchType = SearchType::tryFrom($this->searchType) ?? SearchType::COMBINED;

        // Only pass actual database column filters to SearchCriteria
        // UI filters (profit-margin, sales-velocity, etc.) are applied post-search via applyFilters()
        $dbFilters = [];
        if ($this->selectedCategory) {
            $dbFilters['category_name'] = $this->selectedCategory;
        }

        $criteria = new SearchCriteria(
            query: $this->search,
            type: $searchType,
            fuzzySearch: $this->fuzzySearch,
            exactMatch: $this->exactMatch,
            limit: 100,
            filters: $dbFilters,
            sortBy: null, // Sort after filtering
            sortDirection: $this->sortDirection,
            includeInactive: false,
            includeOutOfStock: true, // Get all, filter later
        );

        $searchResults = $searchService->search($criteria);

        $products = $searchResults->map(function ($product) {
            $analytics = $product->getProfitAnalysis();
            $badges = $this->productBadgeService()->getProductBadges($product, (int) $this->period);

            return array_merge($analytics, [
                'product' => $product,
                'badges' => $badges->map(fn ($badge) => $badge->toArray()),
            ]);
        });

        // When explicitly searching, show ALL matching products regardless of sales
        // The user is looking for something specific - don't hide it!
        // UI filters are still applied but sales filter is skipped for search results

        // Apply UI filters (profit-margin, sales-velocity, etc.) but NOT sales filter
        $products = $this->applyFilters($products);

        // Apply sorting
        return $products->sortBy(function ($item) {
            return match ($this->sortBy) {
                'quantity' => $item['total_sold'],
                'revenue' => $item['total_revenue'],
                'profit' => $item['total_profit'],
                'margin' => $item['profit_margin_percent'],
                'price' => $item['avg_selling_price'],
                'name' => $item['product']->title,
                default => $item['total_revenue'],
            };
        }, SORT_REGULAR, $this->sortDirection === 'desc')
            ->values();
    }

    private function applyFilters(Collection $products): Collection
    {
        $filterService = $this->productFilterService();
        $filterCriteria = $filterService->createFiltersFromArray($this->filters);

        return $filterService->applyFilters($products, $filterCriteria, (int) $this->period);
    }

    #[Computed]
    public function products(): Collection
    {
        return $this->topSellingProducts->take(self::TOP_PRODUCTS_LIMIT);
    }

    public function render(): View
    {
        return view('livewire.products.products-table');
    }

    /**
     * Skeleton loader shown while lazy loading
     */
    public function placeholder(array $params = []): \Illuminate\Contracts\View\View
    {
        return view('livewire.placeholders.products-table', $params);
    }
}
