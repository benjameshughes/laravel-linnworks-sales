<?php

declare(strict_types=1);

namespace App\Livewire\Products;

use App\Services\ProductAnalyticsService;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Top Categories Island
 *
 * Displays top performing categories sidebar widget.
 * Listens for 'products-filters-updated' event to refresh data.
 */
final class TopCategories extends Component
{
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
        return app(ProductAnalyticsService::class)->getTopCategories((int) $this->period);
    }

    public function render()
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
