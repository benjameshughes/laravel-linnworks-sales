<?php

declare(strict_types=1);

namespace App\Livewire\Products;

use App\Services\ProductAnalyticsService;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Product Metrics Island
 *
 * Displays 4 KPI cards: Products Analyzed, Units Sold, Revenue, Avg Profit Margin.
 * Listens for 'products-filters-updated' event to refresh data.
 */
final class ProductMetrics extends Component
{
    public string $period = '30';

    public ?string $search = null;

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
        $this->search = $search ?: null;
        $this->selectedCategory = $selectedCategory;

        // Clear cached computed properties
        unset($this->metrics);
    }

    #[Computed]
    public function metrics(): Collection
    {
        return collect(app(ProductAnalyticsService::class)->getMetrics(
            period: (int) $this->period,
            search: $this->search,
            category: $this->selectedCategory
        ));
    }

    public function render()
    {
        return view('livewire.products.product-metrics');
    }

    /**
     * Skeleton loader shown while lazy loading
     */
    public function placeholder(array $params = []): \Illuminate\Contracts\View\View
    {
        return view('livewire.placeholders.product-metrics', $params);
    }
}
