<?php

declare(strict_types=1);

namespace App\Livewire\Products;

use App\Services\ProductAnalyticsService;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Product Quick View Island
 *
 * Displays detailed information about a selected product.
 * Listens for 'product-selected' and 'product-selection-cleared' events.
 */
final class ProductQuickView extends Component
{
    public ?string $selectedProduct = null;

    public string $period = '30';

    public bool $showChart = false;

    public function mount(): void
    {
        $this->period = request('period', '30');
    }

    #[On('product-selected')]
    public function handleProductSelected(string $sku): void
    {
        $this->selectedProduct = $sku;

        // Clear cached computed properties
        unset($this->productDetails);
        unset($this->productSalesChart);
    }

    #[On('product-selection-cleared')]
    public function handleSelectionCleared(): void
    {
        $this->selectedProduct = null;
        $this->showChart = false;
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

        // Clear cached computed properties if product is selected
        if ($this->selectedProduct) {
            unset($this->productDetails);
            unset($this->productSalesChart);
        }
    }

    public function clearSelection(): void
    {
        $this->selectedProduct = null;
        $this->showChart = false;
        $this->dispatch('product-selection-cleared');
    }

    public function toggleChart(): void
    {
        $this->showChart = ! $this->showChart;
    }

    #[Computed]
    public function productDetails(): ?array
    {
        if (! $this->selectedProduct) {
            return null;
        }

        return app(ProductAnalyticsService::class)->getProductDetails($this->selectedProduct);
    }

    #[Computed]
    public function productSalesChart(): array|Collection
    {
        if (! $this->selectedProduct) {
            return [];
        }

        return app(ProductAnalyticsService::class)->getProductSalesChart(
            $this->selectedProduct,
            (int) $this->period
        );
    }

    public function render()
    {
        return view('livewire.products.product-quick-view');
    }
}
