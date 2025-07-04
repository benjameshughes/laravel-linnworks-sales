<?php

namespace App\Livewire\Dashboard;

use App\Models\OrderItem;
use App\Models\Order;
use Carbon\Carbon;
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

    public function mount()
    {
        //
    }

    #[Computed]
    public function dateRange()
    {
        $days = (int) $this->period;
        return [
            'start' => Carbon::now()->subDays($days)->startOfDay(),
            'end' => Carbon::now()->endOfDay(),
        ];
    }

    #[Computed]
    public function products()
    {
        return OrderItem::select('sku', 'title', 'category')
            ->selectRaw('SUM(quantity) as total_quantity')
            ->selectRaw('SUM(total_price) as total_revenue')
            ->selectRaw('AVG(unit_price) as avg_price')
            ->selectRaw('COUNT(DISTINCT order_id) as total_orders')
            ->selectRaw('SUM(cost_price * quantity) as total_cost')
            ->selectRaw('SUM(total_price) - SUM(cost_price * quantity) as profit')
            ->whereHas('order', function ($query) {
                $query->whereBetween('received_date', [
                    $this->dateRange['start'],
                    $this->dateRange['end']
                ]);
            })
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('title', 'like', '%' . $this->search . '%')
                      ->orWhere('sku', 'like', '%' . $this->search . '%');
                });
            })
            ->groupBy('sku', 'title', 'category')
            ->orderBy($this->getSortColumn(), $this->sortDirection)
            ->paginate(15);
    }

    #[Computed]
    public function productDetails()
    {
        if (!$this->selectedProduct) {
            return null;
        }

        return OrderItem::select('sku', 'title', 'category')
            ->selectRaw('SUM(quantity) as total_quantity')
            ->selectRaw('SUM(total_price) as total_revenue')
            ->selectRaw('AVG(unit_price) as avg_price')
            ->selectRaw('COUNT(DISTINCT order_id) as total_orders')
            ->selectRaw('SUM(cost_price * quantity) as total_cost')
            ->selectRaw('SUM(total_price) - SUM(cost_price * quantity) as profit')
            ->where('sku', $this->selectedProduct)
            ->whereHas('order', function ($query) {
                $query->whereBetween('received_date', [
                    $this->dateRange['start'],
                    $this->dateRange['end']
                ]);
            })
            ->groupBy('sku', 'title', 'category')
            ->first();
    }

    #[Computed]
    public function productSalesChart()
    {
        if (!$this->selectedProduct) {
            return [];
        }

        $days = (int) $this->period;
        $salesData = [];
        
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i);
            $quantity = OrderItem::where('sku', $this->selectedProduct)
                ->whereHas('order', function ($query) use ($date) {
                    $query->whereDate('received_date', $date);
                })
                ->sum('quantity');
            
            $revenue = OrderItem::where('sku', $this->selectedProduct)
                ->whereHas('order', function ($query) use ($date) {
                    $query->whereDate('received_date', $date);
                })
                ->sum('total_price');
            
            $salesData[] = [
                'date' => $date->format('M j'),
                'quantity' => $quantity,
                'revenue' => $revenue,
            ];
        }
        
        return $salesData;
    }

    #[Computed]
    public function topCategories()
    {
        return OrderItem::select('category')
            ->selectRaw('COUNT(DISTINCT sku) as product_count')
            ->selectRaw('SUM(quantity) as total_quantity')
            ->selectRaw('SUM(total_price) as total_revenue')
            ->whereHas('order', function ($query) {
                $query->whereBetween('received_date', [
                    $this->dateRange['start'],
                    $this->dateRange['end']
                ]);
            })
            ->whereNotNull('category')
            ->groupBy('category')
            ->orderByDesc('total_revenue')
            ->limit(10)
            ->get();
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

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function updatedPeriod()
    {
        $this->resetPage();
    }

    private function getSortColumn(): string
    {
        return match ($this->sortBy) {
            'quantity' => 'total_quantity',
            'revenue' => 'total_revenue',
            'orders' => 'total_orders',
            'profit' => 'profit',
            'name' => 'title',
            default => 'total_revenue',
        };
    }

    public function render()
    {
        return view('livewire.dashboard.product-analytics')
            ->title('Product Analytics');
    }
}
