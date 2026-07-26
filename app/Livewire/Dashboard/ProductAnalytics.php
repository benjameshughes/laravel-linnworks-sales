<?php

namespace App\Livewire\Dashboard;

use Livewire\Component;
use App\Enums\SearchType;
use Livewire\WithPagination;
use App\Jobs\SyncProductsJob;
use App\Enums\ProductFilterType;
use Livewire\Attributes\Computed;
use Illuminate\Support\Collection;
use Illuminate\Contracts\View\View;
use App\ValueObjects\FilterCriteria;
use App\ValueObjects\SearchCriteria;
use App\Services\ProductBadgeService;
use App\Services\ProductFilterService;
use App\Services\ProductSearchService;
use App\Services\ProductAnalyticsService;

/**
 * @property-read Collection $topSellingProducts
 */
final class ProductAnalytics extends Component
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

    public string $sortBy = 'revenue';

    public string $sortDirection = 'desc';

    public ?string $selectedProduct = null;

    public ?string $selectedCategory = null;

    public bool $showMetrics = true;

    public bool $showCharts = false;

    public bool $showOnlyWithSales = true;

    // Filter properties
    public array $filters = [];

    public bool $showFilters = false;

    public ?string $activePreset = null;

    // Enhanced search properties
    public string $searchType = 'combined';

    public bool $showSearchOptions = false;

    public array $searchSuggestions = [];

    public bool $exactMatch = false;

    public bool $fuzzySearch = true;

    public function mount(): void
    {
        $this->initializeFilters();
    }

    private function initializeFilters(): void
    {
        $filterService = $this->productFilterService();
        $defaultFilters = $filterService->createDefaultFilters();

        $this->filters = $defaultFilters->mapWithKeys(fn (FilterCriteria $filter) => [
            $filter->type->value => $filter->value,
        ])->toArray();
    }

    #[Computed]
    public function periodSummary(): Collection
    {
        $days = (int) $this->period;
        $periodEnum = \App\Enums\Period::tryFrom((string) $days);

        return collect([
            'period_label' => $periodEnum?->label() ?? "Last {$days} days",
            'days' => $days,
        ]);
    }

    #[Computed]
    public function metrics(): Collection
    {
        return collect($this->productAnalyticsService()->getMetrics(
            period: (int) $this->period,
            search: $this->search ?: null,
            category: $this->selectedCategory
        ));
    }

    #[Computed]
    public function topSellingProducts()
    {
        // Use enhanced search if there's a search query
        if (! empty($this->search)) {
            return $this->performEnhancedSearch();
        }

        $products = $this->productAnalyticsService()->getTopSellingProducts(
            period: (int) $this->period,
            search: null, // Let our enhanced search handle this
            category: $this->selectedCategory,
            limit: 100 // Increased to allow for filtering
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

        $criteria = new SearchCriteria(
            query: $this->search,
            type: $searchType,
            fuzzySearch: $this->fuzzySearch,
            exactMatch: $this->exactMatch,
            limit: 100,
            filters: array_merge($this->filters, [
                'category_name' => $this->selectedCategory,
            ]),
            sortBy: $this->sortBy === 'name' ? 'title' : $this->sortBy,
            sortDirection: $this->sortDirection,
            includeInactive: false,
            includeOutOfStock: ! $this->showOnlyWithSales,
        );

        $searchResults = $searchService->search($criteria);

        // Convert search results to analytics format
        return $searchResults->map(function ($product) {
            $analytics = $product->getProfitAnalysis();
            $badges = $this->productBadgeService()->getProductBadges($product, (int) $this->period);

            return array_merge($analytics, [
                'product' => $product,
                'badges' => $badges->map(fn ($badge) => $badge->toArray()),
            ]);
        });
    }

    private function applyFilters(Collection $products): Collection
    {
        $filterService = $this->productFilterService();
        $filterCriteria = $filterService->createFiltersFromArray($this->filters);

        return $filterService->applyFilters($products, $filterCriteria, (int) $this->period);
    }

    #[Computed]
    public function products()
    {
        // Return paginated top selling products for the main table
        return $this->topSellingProducts->take(self::TOP_PRODUCTS_LIMIT);
    }

    #[Computed]
    public function topCategories()
    {
        return $this->productAnalyticsService()->getTopCategories((int) $this->period);
    }

    #[Computed]
    public function stockAlerts()
    {
        return $this->productAnalyticsService()->getStockAlerts();
    }

    #[Computed]
    public function productDetails()
    {
        if (! $this->selectedProduct) {
            return null;
        }

        return $this->productAnalyticsService()->getProductDetails($this->selectedProduct);
    }

    #[Computed]
    public function productSalesChart()
    {
        if (! $this->selectedProduct) {
            return [];
        }

        return $this->productAnalyticsService()->getProductSalesChart(
            $this->selectedProduct,
            (int) $this->period
        );
    }

    public function toggleMetrics(): void
    {
        $this->showMetrics = ! $this->showMetrics;
    }

    public function toggleCharts(): void
    {
        $this->showCharts = ! $this->showCharts;
        if ($this->showCharts) {
            $this->dispatch('productSelected');
        }
    }

    public function syncProducts(): void
    {
        $this->productAnalyticsService()->invalidateCache();

        SyncProductsJob::dispatch(startedBy: 'dashboard');

        session()->flash('message', 'Product sync dispatched. Data will refresh shortly.');
        $this->dispatch('product-sync-started');
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
    }

    public function clearSelection(): void
    {
        $this->selectedProduct = null;
    }

    public function selectCategory(string $category): void
    {
        $this->selectedCategory = $category;
    }

    public function clearCategoryFilter(): void
    {
        $this->selectedCategory = null;
    }

    public function toggleSalesFilter(): void
    {
        $this->showOnlyWithSales = ! $this->showOnlyWithSales;
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
        $this->loadSearchSuggestions();
    }

    public function updatedSearchType(): void
    {
        $this->resetPage();
        if (! empty($this->search)) {
            $this->loadSearchSuggestions();
        }
    }

    public function loadSearchSuggestions(): void
    {
        if (strlen($this->search) < 2) {
            $this->searchSuggestions = [];

            return;
        }

        $searchService = $this->productSearchService();
        $searchType = SearchType::tryFrom($this->searchType) ?? SearchType::COMBINED;

        $suggestions = $searchService->autocomplete($this->search, $searchType);
        $this->searchSuggestions = $suggestions->take(5)->toArray();
    }

    public function selectSearchSuggestion(string $value): void
    {
        $this->search = $value;
        $this->searchSuggestions = [];
        $this->resetPage();
    }

    public function toggleSearchOptions(): void
    {
        $this->showSearchOptions = ! $this->showSearchOptions;
    }

    public function clearSearch(): void
    {
        $this->search = '';
        $this->searchSuggestions = [];
        $this->resetPage();
    }

    public function updatedPeriod(): void
    {
        $this->resetPage();
    }

    public function updatedFilters(): void
    {
        $this->resetPage();
        $this->activePreset = null; // Clear preset when manual filter changes
    }

    public function toggleFilters(): void
    {
        $this->showFilters = ! $this->showFilters;
    }

    public function clearAllFilters(): void
    {
        $this->initializeFilters();
        $this->activePreset = null;
        $this->resetPage();
    }

    public function applyPreset(string $presetName): void
    {
        $filterService = $this->productFilterService();
        $presets = $filterService->getFilterPresets();

        if (! $presets->has($presetName)) {
            return;
        }

        $this->filters = array_merge($this->filters, $presets[$presetName]['filters']->toArray());
        $this->activePreset = $presetName;
        $this->resetPage();
    }

    public function clearFilter(string $filterType): void
    {
        if (isset($this->filters[$filterType])) {
            $this->filters[$filterType] = null;
            $this->activePreset = null;
            $this->resetPage();
        }
    }

    #[Computed]
    public function availableCategories()
    {
        return $this->productFilterService()->getAvailableCategories();
    }

    #[Computed]
    public function filterPresets()
    {
        return $this->productFilterService()->getFilterPresets();
    }

    /**
     * Applied filters resolved to their display labels, ready for the badges.
     *
     * @return Collection<int, array{type: string, label: string, value: string}>
     */
    #[Computed]
    public function activeFilters(): Collection
    {
        return collect($this->filters)
            ->reject(fn ($value): bool => is_null($value) || $value === '')
            ->map(function ($value, string $type): array {
                $filter = ProductFilterType::tryFrom($type);

                return [
                    'type' => $type,
                    'label' => $filter?->label() ?? $type,
                    'value' => $filter?->getOptions()[$value]['label'] ?? $value,
                ];
            })
            ->values();
    }

    #[Computed]
    public function activeFiltersCount(): int
    {
        return $this->activeFilters->count();
    }

    #[Computed]
    public function searchTypes(): Collection
    {
        return collect(SearchType::cases())->map(fn (SearchType $type) => [
            'value' => $type->value,
            'label' => $type->label(),
            'placeholder' => $type->getPlaceholder(),
            'icon' => $type->getIcon(),
        ]);
    }

    #[Computed]
    public function currentSearchType()
    {
        return SearchType::tryFrom($this->searchType) ?? SearchType::COMBINED;
    }

    public function render(): View
    {
        return view('livewire.dashboard.product-analytics')
            ->title('Product Analytics');
    }
}
