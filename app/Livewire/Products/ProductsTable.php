<?php

declare(strict_types=1);

namespace App\Livewire\Products;

use Livewire\Component;
use App\Enums\SearchType;
use Livewire\Attributes\On;
use Livewire\Attributes\Computed;
use Illuminate\Support\Collection;
use Illuminate\Contracts\View\View;
use App\ValueObjects\SearchCriteria;
use App\Services\ProductBadgeService;
use Illuminate\Support\Facades\Cache;
use App\Services\ProductFilterService;
use App\Services\ProductSearchService;

/**
 * @property-read Collection $topSellingProducts
 */
final class ProductsTable extends Component
{
    private const TOP_PRODUCTS_LIMIT = 20;

    private ?ProductBadgeService $productBadgeService = null;

    private ?ProductFilterService $productFilterService = null;

    private ?ProductSearchService $productSearchService = null;

    public function boot(ProductBadgeService $productBadgeService, ProductFilterService $productFilterService, ProductSearchService $productSearchService): void
    {
        $this->productBadgeService = $productBadgeService;
        $this->productFilterService = $productFilterService;
        $this->productSearchService = $productSearchService;
    }

    private function productBadgeService(): ProductBadgeService
    {
        return $this->productBadgeService ??= app(ProductBadgeService::class);
    }

    private function productFilterService(): ProductFilterService
    {
        return $this->productFilterService ??= app(ProductFilterService::class);
    }

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
        if (! empty($this->search)) {
            return $this->performEnhancedSearch();
        }

        $cached = Cache::get("product_metrics_{$this->period}d");
        $products = $cached && isset($cached['top_products'])
            ? collect($cached['top_products'])
            : collect();

        if ($products->isEmpty()) {
            return collect();
        }

        if ($this->showOnlyWithSales) {
            $products = $products->filter(fn ($item) => $item['total_sold'] > 0);
        }

        $products = $this->applyFilters($products);

        $allBadges = $this->productBadgeService()->getBulkProductBadges(
            $products->pluck('product'),
            (int) $this->period
        );

        $products = $products->map(function ($item) use ($allBadges) {
            $badges = $allBadges[$item['product']->sku] ?? collect();
            $item['badges'] = $badges->map(fn ($badge) => $badge->toArray());

            return $item;
        });

        return $this->sortProducts($products);
    }

    private function performEnhancedSearch(): Collection
    {
        $searchService = $this->productSearchService();
        $searchType = SearchType::tryFrom($this->searchType) ?? SearchType::COMBINED;

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
            sortBy: null,
            sortDirection: $this->sortDirection,
            includeInactive: false,
            includeOutOfStock: true,
        );

        $searchResults = $searchService->search($criteria);

        $allBadges = $this->productBadgeService()->getBulkProductBadges(
            $searchResults,
            (int) $this->period
        );

        $products = $searchResults->map(function ($product) use ($allBadges) {
            $analytics = $product->getProfitAnalysis();
            $badges = $allBadges[$product->sku] ?? collect();

            return array_merge($analytics, [
                'product' => $product,
                'badges' => $badges->map(fn ($badge) => $badge->toArray()),
            ]);
        });

        $products = $this->applyFilters($products);

        return $this->sortProducts($products);
    }

    private function applyFilters(Collection $products): Collection
    {
        $filterService = $this->productFilterService();
        $filterCriteria = $filterService->createFiltersFromArray($this->filters);

        return $filterService->applyFilters($products, $filterCriteria, (int) $this->period);
    }

    private function sortProducts(Collection $products): Collection
    {
        return $products->sortBy(function ($item) {
            return match ($this->sortBy) {
                'quantity' => $item['total_sold'],
                'revenue' => $item['total_revenue'],
                'profit' => $item['total_profit'],
                'margin' => $item['profit_margin_percent'],
                'price' => $item['avg_selling_price'],
                'name' => $item['product']->title ?? $item['product']['title'] ?? '',
                default => $item['total_revenue'],
            };
        }, SORT_REGULAR, $this->sortDirection === 'desc')
            ->values();
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

    public function placeholder(array $params = []): \Illuminate\Contracts\View\View
    {
        return view('livewire.placeholders.products-table', $params);
    }
}
