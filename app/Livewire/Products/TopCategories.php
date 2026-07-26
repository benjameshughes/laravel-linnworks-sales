<?php

declare(strict_types=1);

namespace App\Livewire\Products;

use Livewire\Component;
use Livewire\Attributes\On;
use Livewire\Attributes\Computed;
use Illuminate\Support\Collection;
use Illuminate\Contracts\View\View;
use App\Services\ProductAnalyticsService;

/**
 * Top Categories Island
 *
 * Displays top performing categories sidebar widget.
 * Listens for 'products-filters-updated' event to refresh data.
 */
final class TopCategories extends Component
{
    private ?ProductAnalyticsService $productAnalyticsService = null;

    /**
     * Livewire cannot inject through the constructor, so dependencies are
     * resolved here - boot() runs on every request, mount and hydrate alike.
     */
    public function boot(ProductAnalyticsService $productAnalyticsService): void
    {
        $this->productAnalyticsService = $productAnalyticsService;
    }

    /**
     * Livewire skips boot() on the lazy-load request, so always reach the
     * dependency through here rather than the property directly.
     */
    private function productAnalyticsService(): ProductAnalyticsService
    {
        return $this->productAnalyticsService ??= app(ProductAnalyticsService::class);
    }

    public string $period = '30';

    public ?string $selectedCategory = null;

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
        $this->selectedCategory = $selectedCategory;

        // Clear cached computed properties
        unset($this->topCategories);
    }

    public function selectCategory(string $category): void
    {
        $this->dispatch('category-selected', category: $category);
    }

    #[Computed]
    public function topCategories(): Collection
    {
        return $this->productAnalyticsService()->getTopCategories((int) $this->period);
    }

    public function render(): View
    {
        return view('livewire.products.top-categories');
    }

    /**
     * Skeleton loader shown while lazy loading
     */
    public function placeholder(array $params = []): \Illuminate\Contracts\View\View
    {
        return view('livewire.placeholders.top-list', $params);
    }
}
