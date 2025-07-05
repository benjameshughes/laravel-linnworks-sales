<?php

namespace App\Livewire\Dashboard;

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
    public ?string $selectedCategory = null;

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
        $orders = Order::whereBetween('received_date', [
            $this->dateRange['start'],
            $this->dateRange['end']
        ])->get();

        $products = collect();
        
        foreach ($orders as $order) {
            if (!$order->items) continue;
            
            foreach ($order->items as $item) {
                if ($this->search && !str_contains(strtolower($item['item_title'] ?? ''), strtolower($this->search)) 
                    && !str_contains(strtolower($item['sku'] ?? ''), strtolower($this->search))) {
                    continue;
                }
                
                if ($this->selectedCategory && ($item['category_name'] ?? '') !== $this->selectedCategory) {
                    continue;
                }
                
                $products->push([
                    'sku' => $item['sku'] ?? '',
                    'item_title' => $item['item_title'] ?? 'Unknown',
                    'category_name' => $item['category_name'] ?? 'Uncategorized',
                    'quantity' => $item['quantity'] ?? 0,
                    'unit_cost' => $item['unit_cost'] ?? 0,
                    'price_per_unit' => $item['price_per_unit'] ?? 0,
                    'line_total' => $item['line_total'] ?? 0,
                    'order_id' => $order->id,
                    'received_date' => $order->received_date,
                    'channel_name' => $order->channel_name,
                ]);
            }
        }
        
        // Group by SKU and calculate metrics
        $productStats = $products->groupBy('sku')->map(function ($items, $sku) {
            $firstItem = $items->first();
            $totalQuantity = $items->sum('quantity');
            $totalRevenue = $items->sum('line_total');
            $totalCost = $items->sum(function ($item) {
                return $item['unit_cost'] * $item['quantity'];
            });
            $totalOrders = $items->pluck('order_id')->unique()->count();
            
            return [
                'sku' => $sku,
                'item_title' => $firstItem['item_title'],
                'category_name' => $firstItem['category_name'],
                'total_quantity' => $totalQuantity,
                'total_revenue' => $totalRevenue,
                'total_cost' => $totalCost,
                'profit' => $totalRevenue - $totalCost,
                'profit_margin' => $totalRevenue > 0 ? (($totalRevenue - $totalCost) / $totalRevenue) * 100 : 0,
                'avg_price' => $totalQuantity > 0 ? $totalRevenue / $totalQuantity : 0,
                'total_orders' => $totalOrders,
            ];
        });
        
        // Sort the results
        $sortedProducts = $productStats->sortBy(function ($product) {
            return match ($this->sortBy) {
                'quantity' => $product['total_quantity'],
                'revenue' => $product['total_revenue'],
                'profit' => $product['profit'],
                'margin' => $product['profit_margin'],
                'orders' => $product['total_orders'],
                'name' => $product['item_title'],
                default => $product['total_revenue'],
            };
        }, SORT_REGULAR, $this->sortDirection === 'desc');
        
        return $sortedProducts->values();
    }

    #[Computed]
    public function productDetails()
    {
        if (!$this->selectedProduct) {
            return null;
        }

        $product = $this->products->firstWhere('sku', $this->selectedProduct);
        
        if (!$product) {
            return null;
        }
        
        // Get channel breakdown for this product
        $orders = Order::whereBetween('received_date', [
            $this->dateRange['start'],
            $this->dateRange['end']
        ])->get();
        
        $channelBreakdown = collect();
        
        foreach ($orders as $order) {
            if (!$order->items) continue;
            
            foreach ($order->items as $item) {
                if (($item['sku'] ?? '') === $this->selectedProduct) {
                    $channelBreakdown->push([
                        'channel' => $order->channel_name,
                        'subsource' => $order->subsource,
                        'quantity' => $item['quantity'] ?? 0,
                        'revenue' => $item['line_total'] ?? 0,
                        'date' => $order->received_date,
                    ]);
                }
            }
        }
        
        $product['channel_breakdown'] = $channelBreakdown->groupBy('channel')
            ->map(function ($items, $channel) {
                return [
                    'channel' => $channel,
                    'total_quantity' => $items->sum('quantity'),
                    'total_revenue' => $items->sum('revenue'),
                    'orders' => $items->count(),
                ];
            })
            ->values();
        
        return $product;
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
            
            $dayOrders = Order::whereDate('received_date', $date)->get();
            $quantity = 0;
            $revenue = 0;
            
            foreach ($dayOrders as $order) {
                if (!$order->items) continue;
                
                foreach ($order->items as $item) {
                    if (($item['sku'] ?? '') === $this->selectedProduct) {
                        $quantity += $item['quantity'] ?? 0;
                        $revenue += $item['line_total'] ?? 0;
                    }
                }
            }
            
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
        $orders = Order::whereBetween('received_date', [
            $this->dateRange['start'],
            $this->dateRange['end']
        ])->get();

        $categories = collect();
        
        foreach ($orders as $order) {
            if (!$order->items) continue;
            
            foreach ($order->items as $item) {
                $categoryName = $item['category_name'] ?? 'Uncategorized';
                
                $categories->push([
                    'category' => $categoryName,
                    'sku' => $item['sku'] ?? '',
                    'quantity' => $item['quantity'] ?? 0,
                    'revenue' => $item['line_total'] ?? 0,
                ]);
            }
        }
        
        return $categories->groupBy('category')
            ->map(function ($items, $category) {
                return [
                    'category' => $category,
                    'product_count' => $items->pluck('sku')->unique()->count(),
                    'total_quantity' => $items->sum('quantity'),
                    'total_revenue' => $items->sum('revenue'),
                ];
            })
            ->sortByDesc('total_revenue')
            ->take(10)
            ->values();
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
