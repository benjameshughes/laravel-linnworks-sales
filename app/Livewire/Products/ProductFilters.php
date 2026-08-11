<?php

declare(strict_types=1);

namespace App\Livewire\Products;

use Livewire\Component;
use App\Enums\SearchType;
use Livewire\Attributes\On;
use App\Jobs\SyncProductsJob;
use Livewire\Attributes\Computed;
use Illuminate\Support\Collection;
use Illuminate\Contracts\View\View;
use App\ValueObjects\FilterCriteria;
use Illuminate\Support\Facades\Cache;
use App\Services\ProductFilterService;
use App\Services\ProductSearchService;

/**
 * Product Filters Component
 *
 * Handles search, period selection, category filtering, and advanced filters.
 * Dispatches 'products-filters-updated' event when filters change.
 */
final class ProductFilters extends Component
{
    private ?ProductFilterService $productFilterService = null;

    private ?ProductSearchService $productSearchService = null;

    public function boot(ProductFilterService $productFilterService, ProductSearchService $productSearchService): void
    {
        $this->productFilterService = $productFilterService;
        $this->productSearchService = $productSearchService;
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

    public bool $showFilters = false;

    public bool $showSearchOptions = false;

    public bool $exactMatch = false;

    public bool $fuzzySearch = true;

    /** @var array<string, mixed> */
    public array $filters = [];

    public ?string $activePreset = null;

    /** @var array<int, array{value: string, label: string, highlight: string|null, context: string|null, type: string}> */
    public array $searchSuggestions = [];

    public function mount(): void
    {
        $this->initializeFilters();
        $this->dispatchFiltersUpdated();
    }

    private function initializeFilters(): void
    {
        $filterService = $this->productFilterService();
        $defaultFilters = $filterService->createDefaultFilters();

        $this->filters = $defaultFilters->mapWithKeys(fn (FilterCriteria $filter) => [
            $filter->type->value => $filter->value,
        ])->toArray();
    }

    public function updated(string $property): void
    {
        // Dispatch filter updates for relevant property changes
        if (in_array($property, ['period', 'selectedCategory', 'showOnlyWithSales'])) {
            $this->dispatchFiltersUpdated();
        }

        // Handle search-related updates
        if ($property === 'search') {
            $this->loadSearchSuggestions();
            $this->dispatchFiltersUpdated();
        }

        if ($property === 'searchType') {
            if (! empty($this->search)) {
                $this->loadSearchSuggestions();
            }
            $this->dispatchFiltersUpdated();
        }

        // Handle filter property changes
        if (str_starts_with($property, 'filters.')) {
            $this->activePreset = null;
            $this->dispatchFiltersUpdated();
        }
    }

    private function dispatchFiltersUpdated(): void
    {
        $this->dispatch('products-filters-updated',
            period: $this->period,
            search: $this->search,
            searchType: $this->searchType,
            selectedCategory: $this->selectedCategory,
            showOnlyWithSales: $this->showOnlyWithSales,
            filters: $this->filters,
            exactMatch: $this->exactMatch,
            fuzzySearch: $this->fuzzySearch
        );
    }

    public function syncProducts(): void
    {
        SyncProductsJob::dispatch(startedBy: 'products-page');

        session()->flash('message', 'Product sync dispatched. Data will refresh shortly.');
        $this->dispatch('product-sync-started');
        $this->dispatchFiltersUpdated();
    }

    public function refresh(): void
    {
        $this->dispatchFiltersUpdated();
    }

    public function toggleFilters(): void
    {
        $this->showFilters = ! $this->showFilters;
    }

    public function toggleSearchOptions(): void
    {
        $this->showSearchOptions = ! $this->showSearchOptions;
    }

    public function toggleSalesFilter(): void
    {
        $this->showOnlyWithSales = ! $this->showOnlyWithSales;
    }

    #[On('category-selected')]
    public function handleCategorySelected(string $category): void
    {
        $this->selectedCategory = $category;
        $this->dispatchFiltersUpdated();
    }

    public function clearSearch(): void
    {
        $this->search = '';
        $this->searchSuggestions = [];
        $this->dispatchFiltersUpdated();
    }

    public function selectCategory(string $category): void
    {
        $this->selectedCategory = $category;
    }

    public function clearCategoryFilter(): void
    {
        $this->selectedCategory = null;
    }

    public function clearAllFilters(): void
    {
        $this->initializeFilters();
        $this->activePreset = null;
        $this->dispatchFiltersUpdated();
    }

    public function clearFilter(string $filterType): void
    {
        if (isset($this->filters[$filterType])) {
            $this->filters[$filterType] = null;
            $this->activePreset = null;
            $this->dispatchFiltersUpdated();
        }
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
        $this->dispatchFiltersUpdated();
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

    public function selectSearchSuggestion(string $value, string $type = 'sku'): void
    {
        $this->searchSuggestions = [];

        // If it's a SKU, navigate to the product detail page
        if ($type === 'sku') {
            $this->redirect(route('products.detail', $value), navigate: true);

            return;
        }

        // For other types (title, category, brand), filter the list
        $this->search = $value;
        $this->dispatchFiltersUpdated();
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
    public function availableCategories(): Collection
    {
        return $this->productFilterService()->getAvailableCategories();
    }

    #[Computed]
    public function filterPresets(): Collection
    {
        return $this->productFilterService()->getFilterPresets();
    }

    #[Computed]
    public function activeFiltersCount(): int
    {
        return collect($this->filters)->filter(fn ($value) => ! is_null($value) && $value !== '')->count();
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
    public function currentSearchType(): SearchType
    {
        return SearchType::tryFrom($this->searchType) ?? SearchType::COMBINED;
    }

    #[Computed]
    public function lastWarmedAt(): ?string
    {
        $cached = Cache::get("product_metrics_{$this->period}d");

        return $cached['warmed_at'] ?? null;
    }

    public function render(): View
    {
        return view('livewire.products.product-filters');
    }
}
