<?php

namespace App\Livewire\Dashboard;

use App\Enums\SearchType;
use App\Services\ProductAnalyticsService;
use App\Services\ProductFilterService;
use App\Services\ProductSearchService;
use App\ValueObjects\FilterCriteria;
use App\ValueObjects\SearchCriteria;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

class ProductAnalytics extends Component
{
    use WithPagination;

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

    public function mount()
    {
        $this->initializeFilters();
    }

    private function initializeFilters(): void
    {
        $filterService = app(ProductFilterService::class);
        $defaultFilters = $filterService->createDefaultFilters();

        $this->filters = $defaultFilters->mapWithKeys(fn (FilterCriteria $filter) => [
            $filter->type->value => $filter->value,
        ])->toArray();
    }

    #[Computed]
    public function periodSummary()
    {
        $days = (int) $this->period;
        $periodEnum = \App\Enums\Period::tryFrom((string) $days);

        return collect([
            'period_label' => $periodEnum?->label() ?? "Last {$days} days",
            'days' => $days,
        ]);
    }

    #[Computed]
    public function metrics()
    {
        return collect(app(ProductAnalyticsService::class)->getMetrics(
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

        $products = app(ProductAnalyticsService::class)->getTopSellingProducts(
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
            $badges = app(\App\Services\ProductBadgeService::class)->getProductBadges($item['product'], (int) $this->period);
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
        $searchService = app(ProductSearchService::class);
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
            $badges = app(\App\Services\ProductBadgeService::class)->getProductBadges($product, (int) $this->period);

            return array_merge($analytics, [
                'product' => $product,
                'badges' => $badges->map(fn ($badge) => $badge->toArray()),
            ]);
        });
    }

    private function applyFilters(Collection $products): Collection
    {
        $filterService = app(ProductFilterService::class);
        $filterCriteria = $filterService->createFiltersFromArray($this->filters);

        return $filterService->applyFilters($products, $filterCriteria, (int) $this->period);
    }

    #[Computed]
    public function products()
    {
        // Return paginated top selling products for the main table
        return $this->topSellingProducts->take(20);
    }

    #[Computed]
    public function topCategories()
    {
        return app(ProductAnalyticsService::class)->getTopCategories((int) $this->period);
    }

    #[Computed]
    public function stockAlerts()
    {
        return app(ProductAnalyticsService::class)->getStockAlerts();
    }

    #[Computed]
    public function productDetails()
    {
        if (! $this->selectedProduct) {
            return null;
        }

        return app(ProductAnalyticsService::class)->getProductDetails($this->selectedProduct);
    }

    #[Computed]
    public function productSalesChart()
    {
        if (! $this->selectedProduct) {
            return [];
        }

        return app(ProductAnalyticsService::class)->getProductSalesChart(
            $this->selectedProduct,
            (int) $this->period
        );
    }

    public function toggleMetrics()
    {
        $this->showMetrics = ! $this->showMetrics;
    }

    public function toggleCharts()
    {
        $this->showCharts = ! $this->showCharts;
        if ($this->showCharts) {
            $this->dispatch('productSelected');
        }
    }

    public function syncProducts()
    {
        // Invalidate all product analytics caches
        app(ProductAnalyticsService::class)->invalidateCache();

        // TODO: Product sync jobs removed during refactoring
        // \App\Jobs\GetAllProductsJob::dispatch('ui');
        session()->flash('message', 'Product analytics cache cleared. (Product sync not yet implemented)');
        $this->dispatch('product-sync-started');
    }

    public function sortBy(string $column)
    {
        if ($this->sortBy === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDirection = 'desc';
        }

        $this->resetPage();
    }

    public function selectProduct(string $sku)
    {
        $this->selectedProduct = $sku;
    }

    public function clearSelection()
    {
        $this->selectedProduct = null;
    }

    public function selectCategory(string $category)
    {
        $this->selectedCategory = $category;
    }

    public function clearCategoryFilter()
    {
        $this->selectedCategory = null;
    }

    public function toggleSalesFilter()
    {
        $this->showOnlyWithSales = ! $this->showOnlyWithSales;
        $this->resetPage();
    }

    public function updatedSearch()
    {
        $this->resetPage();
        $this->loadSearchSuggestions();
    }

    public function updatedSearchType()
    {
        $this->resetPage();
        if (! empty($this->search)) {
            $this->loadSearchSuggestions();
        }
    }

    public function loadSearchSuggestions()
    {
        if (strlen($this->search) < 2) {
            $this->searchSuggestions = [];

            return;
        }

        $searchService = app(ProductSearchService::class);
        $searchType = SearchType::tryFrom($this->searchType) ?? SearchType::COMBINED;

        $suggestions = $searchService->autocomplete($this->search, $searchType);
        $this->searchSuggestions = $suggestions->take(5)->toArray();
    }

    public function selectSearchSuggestion(string $value)
    {
        $this->search = $value;
        $this->searchSuggestions = [];
        $this->resetPage();
    }

    public function toggleSearchOptions()
    {
        $this->showSearchOptions = ! $this->showSearchOptions;
    }

    public function clearSearch()
    {
        $this->search = '';
        $this->searchSuggestions = [];
        $this->resetPage();
    }

    public function updatedPeriod()
    {
        $this->resetPage();
    }

    public function updatedFilters()
    {
        $this->resetPage();
        $this->activePreset = null; // Clear preset when manual filter changes
    }

    public function toggleFilters()
    {
        $this->showFilters = ! $this->showFilters;
    }

    public function clearAllFilters()
    {
        $this->initializeFilters();
        $this->activePreset = null;
        $this->resetPage();
    }

    public function applyPreset(string $presetName)
    {
        $filterService = app(ProductFilterService::class);
        $presets = $filterService->getFilterPresets();

        if (! $presets->has($presetName)) {
            return;
        }

        $this->filters = array_merge($this->filters, $presets[$presetName]['filters']->toArray());
        $this->activePreset = $presetName;
        $this->resetPage();
    }

    public function clearFilter(string $filterType)
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
        return app(ProductFilterService::class)->getAvailableCategories();
    }

    #[Computed]
    public function filterPresets()
    {
        return app(ProductFilterService::class)->getFilterPresets();
    }

    #[Computed]
    public function activeFiltersCount()
    {
        return collect($this->filters)->filter(fn ($value) => ! is_null($value) && $value !== '')->count();
    }

    #[Computed]
    public function searchTypes()
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

    public function render()
    {
        return view('livewire.dashboard.product-analytics')
            ->title('Product Analytics');
    }
}
