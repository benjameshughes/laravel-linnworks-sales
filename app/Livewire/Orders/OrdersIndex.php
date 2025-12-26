<?php

declare(strict_types=1);

namespace App\Livewire\Orders;

use Livewire\Component;

/**
 * Orders Index - Island Architecture Shell
 *
 * This component acts as a layout container for independent order islands.
 * All data fetching and logic has been extracted to child components.
 *
 * Islands (lazy-loaded for parallel execution):
 * - OrderFilters: Filter controls, search, and period selection
 * - OrderMetrics: KPI cards with comparison indicators
 * - OrdersChart: Daily trend chart
 * - OrdersTable: Main order listing with sorting and pagination
 * - TopCustomers: Repeat customers sidebar widget
 *
 * Communication: Islands listen for 'orders-filters-updated' event from OrderFilters
 */
final class OrdersIndex extends Component
{
    public function render()
    {
        return view('livewire.orders.orders-index')
            ->title('Order Analytics');
    }
}
