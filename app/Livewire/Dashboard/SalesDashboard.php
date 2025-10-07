<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard;

use Livewire\Component;

/**
 * Sales Dashboard - Island Architecture Shell
 *
 * This component acts as a layout container for independent dashboard islands.
 * All data fetching and logic has been extracted to child components.
 *
 * Islands (all lazy-loaded for parallel execution):
 * - DashboardFilters: Filter controls and sync functionality
 * - MetricsSummary: 4 KPI cards (revenue, orders, avg, items)
 * - SalesTrendChart: Area chart
 * - ChannelDistributionChart: Doughnut chart
 * - DailyRevenueChart: Bar chart
 * - TopProducts: Top 5 products widget
 * - TopChannels: Top channels widget
 * - RecentOrders: Orders table
 *
 * Communication: Islands listen for 'filters-updated' event from DashboardFilters
 */
final class SalesDashboard extends Component
{
    public function render()
    {
        return view('livewire.dashboard.sales-dashboard')
            ->title('Sales Dashboard');
    }
}
