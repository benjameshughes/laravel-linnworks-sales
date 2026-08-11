<?php

declare(strict_types=1);

namespace App\Livewire\Products;

use Carbon\Carbon;
use Livewire\Component;
use Livewire\Attributes\On;
use App\Queries\ProductQueries;
use Livewire\Attributes\Computed;
use Illuminate\Support\Collection;
use Illuminate\Contracts\View\View;
use App\Queries\ProfitabilityQueries;

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

        $queries = app(ProductQueries::class);
        $product = $queries->findBySku($this->selectedProduct);

        if (! $product) {
            return null;
        }

        $periodInt = (int) $this->period;
        $start = now()->subDays($periodInt);
        $end = now();

        $profitQueries = app(ProfitabilityQueries::class);
        $performance = $queries->productPerformance($this->selectedProduct, $start, $end);
        $profitability = $profitQueries->productProfitability($this->selectedProduct, $start, $end);
        $channelBreakdown = $queries->channelBreakdown($this->selectedProduct, $start, $end);

        $revenue = (float) ($profitability?->revenue ?? $performance?->total_revenue ?? 0);
        $profit = (float) ($profitability?->profit ?? 0);
        $profitMargin = $revenue > 0 ? ($profit / $revenue) * 100 : 0;

        return [
            'product' => $product,
            'profit_analysis' => [
                'total_sold' => (int) ($performance?->total_quantity ?? 0),
                'total_revenue' => $revenue,
                'total_cost' => (float) ($profitability?->total_cost ?? 0),
                'total_profit' => $profit,
                'profit_margin_percent' => $profitMargin,
                'cogs' => (float) ($profitability?->cogs ?? 0),
                'channel_fees' => (float) ($profitability?->channel_fees ?? 0),
                'shipping_cost' => (float) ($profitability?->shipping_cost ?? 0),
                'avg_selling_price' => (float) ($performance?->avg_selling_price ?? 0),
                'purchase_price' => $product->purchase_price,
            ],
            'channel_performance' => $channelBreakdown,
            'stock_info' => [
                'current_stock' => $product->stock_available ?? 0,
                'minimum_stock' => $product->stock_minimum ?? 0,
                'in_orders' => $product->stock_in_orders ?? 0,
                'due_stock' => $product->stock_due ?? 0,
            ],
        ];
    }

    #[Computed]
    public function productSalesChart(): array|Collection
    {
        if (! $this->selectedProduct) {
            return [];
        }

        $queries = app(ProductQueries::class);
        $periodInt = (int) $this->period;
        $start = now()->subDays($periodInt - 1)->startOfDay();
        $end = now();

        $dailySales = $queries->dailySales($this->selectedProduct, $start, $end)->keyBy('date');

        $salesData = [];
        for ($i = $periodInt - 1; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i);
            $dateKey = $date->format('Y-m-d');
            $dayData = $dailySales->get($dateKey);

            $salesData[] = [
                'date' => $date->format('M j'),
                'quantity' => $dayData ? (int) $dayData->quantity : 0,
                'revenue' => $dayData ? (float) $dayData->revenue : 0,
            ];
        }

        return $salesData;
    }

    public function render(): View
    {
        return view('livewire.products.product-quick-view');
    }
}
