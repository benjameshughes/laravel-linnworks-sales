<?php

declare(strict_types=1);

namespace App\Livewire\Products;

use Livewire\Component;

/**
 * Products Index - Island Architecture Shell
 *
 * This component acts as a layout container for independent product islands.
 * All data fetching and logic has been extracted to child components.
 *
 * Islands (lazy-loaded for parallel execution):
 * - ProductFilters: Filter controls, search, and period selection
 * - ProductMetrics: 4 KPI cards (products, units, revenue, margin)
 * - ProductsTable: Main product listing with sorting and pagination
 * - TopCategories: Categories sidebar widget
 * - StockAlerts: Stock alerts widget
 * - ProductQuickView: Quick view panel for selected product
 *
 * Communication: Islands listen for 'products-filters-updated' event from ProductFilters
 */
final class ProductsIndex extends Component
{
    public function render()
    {
        return view('livewire.products.products-index')
            ->title('Product Analytics');
    }
}
