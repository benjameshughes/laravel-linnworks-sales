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
 * Stock Alerts Island
 *
 * Displays products with low stock levels.
 * Listens for 'products-filters-updated' event to refresh data.
 */
final class StockAlerts extends Component
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
        return $this->productAnalyticsService()->getStockAlerts();
    }

    public function render(): View
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
