<?php

declare(strict_types=1);

namespace App\Livewire\Products;

use App\Services\ProductAnalyticsService;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Stock Alerts Island
 *
 * Displays products with low stock levels.
 * Listens for 'products-filters-updated' event to refresh data.
 */
final class StockAlerts extends Component
{
    public function mount(): void
    {
        // Stock alerts don't depend on period/filters, but we still listen for cache invalidation
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
        // Clear cached computed properties to pick up any cache invalidation
        unset($this->stockAlerts);
    }

    #[On('product-sync-started')]
    public function handleSyncStarted(): void
    {
        // Clear cache when sync starts
        unset($this->stockAlerts);
    }

    #[Computed]
    public function stockAlerts(): Collection
    {
        return app(ProductAnalyticsService::class)->getStockAlerts();
    }

    public function render()
    {
        return view('livewire.products.stock-alerts');
    }

    /**
     * Skeleton loader shown while lazy loading
     */
    public function placeholder(array $params = []): \Illuminate\Contracts\View\View
    {
        return view('livewire.placeholders.top-list', $params);
    }
}
