<?php

namespace App\Livewire\Dashboard;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Channel;
use Carbon\Carbon;
use Livewire\Component;
use Livewire\Attributes\Computed;

class SalesDashboard extends Component
{
    public string $period = '30';
    public string $currency = 'all';
    public string $channel = 'all';
    
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
    public function totalRevenue()
    {
        return $this->getBaseQuery()
            ->sum('total_paid');
    }

    #[Computed]
    public function totalOrders()
    {
        return $this->getBaseQuery()
            ->count();
    }

    #[Computed]
    public function averageOrderValue()
    {
        $totalOrders = $this->totalOrders;
        return $totalOrders > 0 ? $this->totalRevenue / $totalOrders : 0;
    }

    #[Computed]
    public function totalItems()
    {
        return OrderItem::whereHas('order', function ($query) {
            $query->whereBetween('received_date', [
                $this->dateRange['start'],
                $this->dateRange['end']
            ]);
            if ($this->channel !== 'all') {
                $query->where('channel_name', $this->channel);
            }
        })->sum('quantity');
    }

    #[Computed]
    public function revenueGrowth()
    {
        $currentPeriod = $this->totalRevenue;
        $previousPeriod = $this->getPreviousPeriodRevenue();
        
        if ($previousPeriod == 0) {
            return $currentPeriod > 0 ? 100 : 0;
        }
        
        return (($currentPeriod - $previousPeriod) / $previousPeriod) * 100;
    }

    #[Computed]
    public function topProducts()
    {
        return OrderItem::select('sku', 'title')
            ->selectRaw('SUM(quantity) as total_quantity')
            ->selectRaw('SUM(total_price) as total_revenue')
            ->selectRaw('COUNT(DISTINCT order_id) as total_orders')
            ->whereHas('order', function ($query) {
                $query->whereBetween('received_date', [
                    $this->dateRange['start'],
                    $this->dateRange['end']
                ]);
                if ($this->channel !== 'all') {
                    $query->where('channel_name', $this->channel);
                }
            })
            ->groupBy('sku', 'title')
            ->orderByDesc('total_revenue')
            ->limit(10)
            ->get();
    }

    #[Computed]
    public function topChannels()
    {
        return Order::select('channel_name')
            ->selectRaw('COUNT(*) as total_orders')
            ->selectRaw('SUM(total_paid) as total_revenue')
            ->selectRaw('AVG(total_paid) as avg_order_value')
            ->whereBetween('received_date', [
                $this->dateRange['start'],
                $this->dateRange['end']
            ])
            ->groupBy('channel_name')
            ->orderByDesc('total_revenue')
            ->limit(10)
            ->get();
    }

    #[Computed]
    public function recentOrders()
    {
        return $this->getBaseQuery()
            ->with(['items' => function ($query) {
                $query->select('order_id', 'sku', 'title', 'quantity', 'total_price');
            }])
            ->orderByDesc('received_date')
            ->limit(10)
            ->get();
    }

    #[Computed]
    public function dailySales()
    {
        $days = (int) $this->period;
        $salesData = [];
        
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i);
            $revenue = Order::whereDate('received_date', $date)
                ->when($this->channel !== 'all', function ($query) {
                    $query->where('channel_name', $this->channel);
                })
                ->sum('total_paid');
            
            $salesData[] = [
                'date' => $date->format('M j'),
                'revenue' => $revenue,
                'orders' => Order::whereDate('received_date', $date)
                    ->when($this->channel !== 'all', function ($query) {
                        $query->where('channel_name', $this->channel);
                    })
                    ->count(),
            ];
        }
        
        return $salesData;
    }

    #[Computed]
    public function availableChannels()
    {
        return Channel::active()->get();
    }

    public function updatedPeriod()
    {
        $this->dispatch('period-updated', $this->period);
    }

    public function updatedChannel()
    {
        $this->dispatch('channel-updated', $this->channel);
    }

    private function getBaseQuery()
    {
        return Order::whereBetween('received_date', [
            $this->dateRange['start'],
            $this->dateRange['end']
        ])
        ->when($this->channel !== 'all', function ($query) {
            $query->where('channel_name', $this->channel);
        });
    }

    private function getPreviousPeriodRevenue()
    {
        $days = (int) $this->period;
        $start = Carbon::now()->subDays($days * 2)->startOfDay();
        $end = Carbon::now()->subDays($days)->endOfDay();
        
        return Order::whereBetween('received_date', [$start, $end])
            ->when($this->channel !== 'all', function ($query) {
                $query->where('channel_name', $this->channel);
            })
            ->sum('total_paid');
    }

    public function render()
    {
        return view('livewire.dashboard.sales-dashboard')
            ->title('Sales Dashboard');
    }
}
