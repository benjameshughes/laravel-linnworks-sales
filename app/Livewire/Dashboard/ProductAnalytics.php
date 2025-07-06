<?php

namespace App\Livewire\Dashboard;

use App\Services\ProductAnalyticsService;
use Livewire\Component;
use Livewire\Attributes\Computed;
use Livewire\WithPagination;

class ProductAnalytics extends Component
{
    use WithPagination;

    public string $period = '30';
    public string $search = '';
    public string $sortBy = 'revenue';
    public string $sortDirection = 'desc';
    public ?string $selectedProduct = null;
    public ?string $selectedCategory = null;
    public bool $showMetrics = true;
    public bool $showCharts = false;
    public bool $showOnlyWithSales = true;

    public function mount()
    {
        //
    }

    #[Computed]
    public function periodSummary()
    {
        $days = (int) $this->period;
        return collect([
            'period_label' => match($days) {
                1 => 'Last 24 hours',
                7 => 'Last 7 days', 
                30 => 'Last 30 days',
                90 => 'Last 90 days',
                365 => 'Last year',
                default => "Last {$days} days"
            },
            'days' => $days
        ]);
    }

    #[Computed] 
    public function metrics()
    {
        return collect(app(ProductAnalyticsService::class)->getMetrics(
            period: (int) $this->period,
            search: $this->search ?: null,
            category: $this->selectedCategory
        ));
    }

    #[Computed]
    public function topSellingProducts()
    {
        $products = app(ProductAnalyticsService::class)->getTopSellingProducts(
            period: (int) $this->period,
            search: $this->search ?: null,
            category: $this->selectedCategory,
            limit: 50
        );
        
        // Filter products based on sales if needed
        if ($this->showOnlyWithSales) {
            $products = $products->filter(fn($item) => $item['total_sold'] > 0);
        }
        
        // Apply sorting based on UI selection
        return $products->sortBy(function($item) {
            return match($this->sortBy) {
                'quantity' => $item['total_sold'],
                'revenue' => $item['total_revenue'], 
                'profit' => $item['total_profit'],
                'margin' => $item['profit_margin_percent'],
                'price' => $item['avg_selling_price'],
                'name' => $item['product']->title,
                default => $item['total_revenue'],
            };
        }, SORT_REGULAR, $this->sortDirection === 'desc')
        ->values();
    }


    #[Computed]
    public function products()
    {
        // Return paginated top selling products for the main table
        return $this->topSellingProducts->take(20);
    }

    #[Computed]
    public function topCategories()
    {
        return app(ProductAnalyticsService::class)->getTopCategories((int) $this->period);
    }

    #[Computed]
    public function stockAlerts()
    {
        return app(ProductAnalyticsService::class)->getStockAlerts();
    }

    #[Computed]
    public function productDetails()
    {
        if (!$this->selectedProduct) {
            return null;
        }

        return app(ProductAnalyticsService::class)->getProductDetails($this->selectedProduct);
    }

    #[Computed]
    public function productSalesChart()
    {
        if (!$this->selectedProduct) {
            return [];
        }

        return app(ProductAnalyticsService::class)->getProductSalesChart(
            $this->selectedProduct,
            (int) $this->period
        );
    }

    public function toggleMetrics()
    {
        $this->showMetrics = !$this->showMetrics;
    }

    public function toggleCharts()
    {
        $this->showCharts = !$this->showCharts;
        if ($this->showCharts) {
            $this->dispatch('productSelected');
        }
    }

    public function syncProducts()
    {
        // Invalidate all product analytics caches
        app(ProductAnalyticsService::class)->invalidateCache();
        
        // Dispatch a product sync job
        \App\Jobs\GetAllProductsJob::dispatch('ui');
        session()->flash('message', 'Product sync initiated! Check back in a few minutes.');
        $this->dispatch('product-sync-started');
    }

    public function sortBy(string $column)
    {
        if ($this->sortBy === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDirection = 'desc';
        }

        $this->resetPage();
    }

    public function selectProduct(string $sku)
    {
        $this->selectedProduct = $sku;
    }

    public function clearSelection()
    {
        $this->selectedProduct = null;
    }

    public function selectCategory(string $category)
    {
        $this->selectedCategory = $category;
    }

    public function clearCategoryFilter()
    {
        $this->selectedCategory = null;
    }

    public function toggleSalesFilter()
    {
        $this->showOnlyWithSales = !$this->showOnlyWithSales;
        $this->resetPage();
    }

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function updatedPeriod()
    {
        $this->resetPage();
    }

    public function render()
    {
        return view('livewire.dashboard.product-analytics')
            ->title('Product Analytics');
    }
}
