<?php

namespace App\Livewire\Dashboard;

use App\Models\Order;
use Carbon\Carbon;
use Livewire\Component;
use Livewire\Attributes\Computed;

class ChannelComparison extends Component
{
    public string $period = '30';
    public string $metric = 'revenue';
    public ?string $selectedChannel = null;
    public bool $showSubsources = false;

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
    public function orders()
    {
        return Order::whereBetween('received_date', [
            $this->dateRange['start'],
            $this->dateRange['end']
        ])->get();
    }

    #[Computed]
    public function channelComparison()
    {
        $orders = $this->orders;
        
        $channels = $orders->groupBy(function ($order) {
            if ($this->showSubsources && $order->subsource && $order->subsource !== $order->channel_name) {
                return "{$order->channel_name} ({$order->subsource})";
            }
            return $order->channel_name;
        });

        return $channels->map(function ($channelOrders, $channelKey) {
            $totalRevenue = $channelOrders->sum('total_charge');
            $totalOrders = $channelOrders->count();
            $totalItems = $channelOrders->sum('total_items');
            $avgOrderValue = $totalOrders > 0 ? $totalRevenue / $totalOrders : 0;
            
            // Calculate profit
            $totalProfit = $channelOrders->sum('net_profit');
            $profitMargin = $totalRevenue > 0 ? ($totalProfit / $totalRevenue) * 100 : 0;
            
            // Get conversion rate (assume all orders are conversions for now)
            $conversionRate = 100; // Could be enhanced with visit tracking
            
            return [
                'channel' => $channelKey,
                'total_revenue' => $totalRevenue,
                'total_orders' => $totalOrders,
                'total_items' => $totalItems,
                'avg_order_value' => $avgOrderValue,
                'total_profit' => $totalProfit,
                'profit_margin' => $profitMargin,
                'conversion_rate' => $conversionRate,
                'revenue_share' => 0, // Will be calculated later
                'growth_rate' => $this->getChannelGrowthRate($channelKey),
            ];
        })->sortByDesc($this->metric === 'revenue' ? 'total_revenue' : $this->metric);
    }

    #[Computed]
    public function channelDetails()
    {
        if (!$this->selectedChannel) {
            return null;
        }

        $channelData = $this->channelComparison->firstWhere('channel', $this->selectedChannel);
        
        if (!$channelData) {
            return null;
        }

        // Get daily performance for the selected channel
        $days = (int) $this->period;
        $dailyData = [];
        
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i);
            
            $dayOrders = $this->orders->filter(function ($order) use ($date) {
                if (!$order->received_date || !$order->received_date->isSameDay($date)) {
                    return false;
                }
                
                $orderChannel = $this->showSubsources && $order->subsource && $order->subsource !== $order->channel_name
                    ? "{$order->channel_name} ({$order->subsource})"
                    : $order->channel_name;
                    
                return $orderChannel === $this->selectedChannel;
            });
            
            $dailyData[] = [
                'date' => $date->format('M j'),
                'revenue' => $dayOrders->sum('total_charge'),
                'orders' => $dayOrders->count(),
                'items' => $dayOrders->sum('total_items'),
            ];
        }
        
        $channelData['daily_data'] = $dailyData;
        
        // Get top products for this channel
        $channelOrders = $this->orders->filter(function ($order) {
            $orderChannel = $this->showSubsources && $order->subsource && $order->subsource !== $order->channel_name
                ? "{$order->channel_name} ({$order->subsource})"
                : $order->channel_name;
                
            return $orderChannel === $this->selectedChannel;
        });
        
        // Use proper relationships and eager loading - senior Laravel approach
        $orderIds = $channelOrders->pluck('id');
        
        // Get aggregated data first
        $aggregatedData = OrderItem::whereIn('order_id', $orderIds)
            ->selectRaw('
                sku,
                SUM(quantity) as total_quantity,
                SUM(line_total) as total_revenue,
                COUNT(*) as order_count
            ')
            ->groupBy('sku')
            ->orderByDesc('total_revenue')
            ->limit(5)
            ->get()
            ->keyBy('sku');
            
        // Get product titles for these SKUs in one query
        $products = \App\Models\Product::whereIn('sku', $aggregatedData->keys())
            ->pluck('title', 'sku');
            
        // Combine the data
        $topProducts = $aggregatedData->map(fn ($item) => [
            'sku' => $item->sku,
            'item_title' => $products[$item->sku] ?? 'Unknown Product',
            'total_quantity' => (int) $item->total_quantity,
            'total_revenue' => (float) $item->total_revenue,
            'order_count' => (int) $item->order_count,
        ])->values();
        
        $channelData['top_products'] = $topProducts;
        
        return $channelData;
    }

    #[Computed]
    public function chartData()
    {
        $comparison = $this->channelComparison;
        
        // Calculate revenue share
        $totalRevenue = $comparison->sum('total_revenue');
        $comparison = $comparison->map(function ($channel) use ($totalRevenue) {
            $channel['revenue_share'] = $totalRevenue > 0 ? ($channel['total_revenue'] / $totalRevenue) * 100 : 0;
            return $channel;
        });
        
        return [
            'labels' => $comparison->pluck('channel')->take(10)->toArray(),
            'revenue' => $comparison->pluck('total_revenue')->take(10)->toArray(),
            'orders' => $comparison->pluck('total_orders')->take(10)->toArray(),
            'profit' => $comparison->pluck('total_profit')->take(10)->toArray(),
            'margins' => $comparison->pluck('profit_margin')->take(10)->toArray(),
        ];
    }

    public function selectChannel(string $channel)
    {
        $this->selectedChannel = $channel;
    }

    public function clearSelection()
    {
        $this->selectedChannel = null;
    }

    public function toggleSubsources()
    {
        $this->showSubsources = !$this->showSubsources;
    }

    public function updatedMetric()
    {
        // Re-sort when metric changes
    }

    public function updatedPeriod()
    {
        $this->clearSelection();
    }

    private function getChannelGrowthRate(string $channel): float
    {
        // Get previous period data for growth calculation
        $currentPeriodDays = (int) $this->period;
        $previousStart = Carbon::now()->subDays($currentPeriodDays * 2)->startOfDay();
        $previousEnd = Carbon::now()->subDays($currentPeriodDays)->endOfDay();
        
        $previousOrders = Order::whereBetween('received_date', [$previousStart, $previousEnd])->get();
        
        $previousRevenue = $previousOrders->filter(function ($order) use ($channel) {
            $orderChannel = $this->showSubsources && $order->subsource && $order->subsource !== $order->channel_name
                ? "{$order->channel_name} ({$order->subsource})"
                : $order->channel_name;
                
            return $orderChannel === $channel;
        })->sum('total_charge');
        
        $currentRevenue = $this->channelComparison->firstWhere('channel', $channel)['total_revenue'] ?? 0;
        
        if ($previousRevenue == 0) {
            return $currentRevenue > 0 ? 100 : 0;
        }
        
        return (($currentRevenue - $previousRevenue) / $previousRevenue) * 100;
    }

    public function render()
    {
        return view('livewire.dashboard.channel-comparison')
            ->title('Channel Comparison');
    }
}