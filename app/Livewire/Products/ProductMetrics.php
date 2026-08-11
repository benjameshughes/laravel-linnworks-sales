<?php

declare(strict_types=1);

namespace App\Livewire\Products;

use Livewire\Component;
use Livewire\Attributes\On;
use App\Queries\ProductQueries;
use Livewire\Attributes\Computed;
use Illuminate\Support\Collection;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Cache;

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

        unset($this->metrics);
    }

    #[Computed]
    public function metrics(): Collection
    {
        if ($this->search !== null || $this->selectedCategory !== null) {
            return collect($this->filteredMetrics());
        }

        $cached = Cache::get("product_metrics_{$this->period}d");

        if ($cached && isset($cached['metrics'])) {
            return collect($cached['metrics']);
        }

        return collect([
            'total_products' => 0,
            'total_units_sold' => 0,
            'total_revenue' => 0,
            'avg_profit_margin' => 0,
            'top_performing_sku' => null,
            'categories_count' => 0,
            'low_stock_count' => 0,
        ]);
    }

    private function filteredMetrics(): array
    {
        $queries = app(ProductQueries::class);
        $periodInt = (int) $this->period;

        $products = $queries->activeProducts($this->search, $this->selectedCategory, 5000);
        $skus = $products->pluck('sku')->toArray();
        $salesData = $queries->bulkSalesData($skus, $periodInt);

        $withSales = collect($skus)->filter(fn (string $sku) => ($salesData->get($sku)['total_sold'] ?? 0) > 0);

        return [
            'total_products' => $queries->countWithSales($periodInt, $this->search, $this->selectedCategory),
            'total_units_sold' => $withSales->sum(fn (string $sku) => $salesData->get($sku)['total_sold']),
            'total_revenue' => $withSales->sum(fn (string $sku) => $salesData->get($sku)['total_revenue']),
            'avg_profit_margin' => 0,
            'top_performing_sku' => null,
            'categories_count' => 0,
            'low_stock_count' => 0,
        ];
    }

    public function render(): View
    {
        return view('livewire.products.product-metrics');
    }

    public function placeholder(array $params = []): \Illuminate\Contracts\View\View
    {
        return view('livewire.placeholders.product-metrics', $params);
    }
}
