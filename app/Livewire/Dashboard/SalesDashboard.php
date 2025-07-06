<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard;

use App\Jobs\GetOpenOrderIdsJob;
use App\Models\Order;
use App\Models\SyncLog;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

final class SalesDashboard extends Component
{
    public string $period = '7';
    public string $channel = 'all';
    public string $searchTerm = '';
    public bool $showMetrics = true;
    public bool $showCharts = true;
    
    public function mount()
    {
        //
    }

    #[Computed]
    public function dateRange(): Collection
    {
        $days = (int) $this->period;
        
        return collect([
            'start' => Carbon::now()->subDays($days)->startOfDay(),
            'end' => Carbon::now()->endOfDay(),
        ]);
    }

    #[Computed]
    public function orders(): Collection
    {
        return Order::whereBetween('received_date', [
                $this->dateRange->get('start'),
                $this->dateRange->get('end')
            ])
            ->where('channel_name', '!=', 'DIRECT')
            ->when($this->channel !== 'all', fn($query) => 
                $query->where('channel_name', $this->channel)
            )
            ->when($this->searchTerm, fn($query) => 
                $query->where(function ($q) {
                    $q->where('order_number', 'like', "%{$this->searchTerm}%")
                      ->orWhere('channel_name', 'like', "%{$this->searchTerm}%");
                })
            )
            ->orderByDesc('received_date')
            ->get();
    }

    #[Computed]
    public function metrics(): Collection
    {
        $orders = $this->orders;
        $totalRevenue = $orders->sum('total_charge');
        $totalOrders = $orders->count();
        
        return collect([
            'total_revenue' => $totalRevenue,
            'total_orders' => $totalOrders,
            'average_order_value' => $totalOrders > 0 ? $totalRevenue / $totalOrders : 0,
            'total_items' => $orders->sum(fn($order) => collect($order->items ?? [])->sum('quantity')),
            'growth_rate' => $this->calculateGrowthRate($totalRevenue),
        ]);
    }

    #[Computed]
    public function periodSummary(): Collection
    {
        return collect([
            'period_label' => $this->getPeriodLabel(),
            'date_range' => $this->getFormattedDateRange(),
            'orders_per_day' => $this->metrics->get('total_orders') / (int) $this->period,
        ]);
    }

    #[Computed]
    public function topProducts(): Collection
    {
        return $this->orders
            ->flatMap(fn($order) => collect($order->items ?? []))
            ->groupBy('sku')
            ->map(function (Collection $items, string $sku) {
                $firstItem = $items->first();
                return collect([
                    'sku' => $sku,
                    'title' => $firstItem['item_title'] ?? 'Unknown Product',
                    'quantity' => $items->sum('quantity'),
                    'revenue' => $items->sum('line_total'),
                    'orders' => $items->count(),
                    'avg_price' => $items->avg('price_per_unit'),
                ]);
            })
            ->sortByDesc('revenue')
            ->take(5)
            ->values();
    }

    #[Computed]
    public function topChannels(): Collection
    {
        return $this->orders
            ->groupBy('channel_name')
            ->map(function (Collection $channelOrders, string $channel) {
                return collect([
                    'name' => $channel,
                    'orders' => $channelOrders->count(),
                    'revenue' => $channelOrders->sum('total_charge'),
                    'avg_order_value' => $channelOrders->avg('total_charge'),
                    'percentage' => ($channelOrders->sum('total_charge') / $this->metrics->get('total_revenue')) * 100,
                ]);
            })
            ->sortByDesc('revenue')
            ->take(6)
            ->values();
    }

    #[Computed]
    public function recentOrders(): Collection
    {
        return $this->orders
            ->sortByDesc('received_date')
            ->take(15)
            ->values();
    }

    #[Computed]
    public function availableChannels(): Collection
    {
        return Order::distinct()
            ->pluck('channel_name')
            ->filter()
            ->reject(fn($channel) => $channel === 'DIRECT')
            ->sort()
            ->map(fn($channel) => collect(['name' => $channel, 'label' => ucfirst($channel)]));
    }

    #[Computed]
    public function dailySalesChart(): Collection
    {
        $days = (int) $this->period;
        
        return collect(range($days - 1, 0))
            ->map(function (int $daysAgo) {
                $date = Carbon::now()->subDays($daysAgo);
                $dayOrders = $this->orders->filter(
                    fn($order) => $order->received_date?->isSameDay($date)
                );
                
                return collect([
                    'date' => $date->format('M j'),
                    'day' => $date->format('D'),
                    'revenue' => $dayOrders->sum('total_charge'),
                    'orders' => $dayOrders->count(),
                    'avg_order_value' => $dayOrders->avg('total_charge') ?? 0,
                ]);
            });
    }

    #[Computed]
    public function lastSyncInfo(): Collection
    {
        $lastSync = SyncLog::where('sync_type', SyncLog::TYPE_OPEN_ORDERS)
            ->whereNotNull('completed_at')
            ->latest('completed_at')
            ->first();
        
        if (!$lastSync) {
            return collect([
                'time_human' => 'Never synced',
                'created' => 0,
                'updated' => 0,
                'status' => 'never',
            ]);
        }
        
        return collect([
            'time_human' => $lastSync->completed_at->diffForHumans(),
            'created' => $lastSync->total_created ?? 0,
            'updated' => $lastSync->total_updated ?? 0,
            'failed' => $lastSync->total_failed ?? 0,
            'status' => 'success',
            'success_rate' => $this->calculateSuccessRate($lastSync),
        ]);
    }

    public function syncOrders(): void
    {
        GetOpenOrderIdsJob::dispatch('ui');
        
        $this->dispatch('notification', [
            'message' => 'Order sync started successfully',
            'type' => 'success'
        ]);
    }

    public function toggleMetrics(): void
    {
        $this->showMetrics = !$this->showMetrics;
    }

    public function toggleCharts(): void
    {
        $this->showCharts = !$this->showCharts;
    }

    private function calculateGrowthRate(float $currentRevenue): float
    {
        $previousPeriodRevenue = $this->getPreviousPeriodRevenue();
        
        if ($previousPeriodRevenue === 0.0) {
            return $currentRevenue > 0 ? 100.0 : 0.0;
        }
        
        return (($currentRevenue - $previousPeriodRevenue) / $previousPeriodRevenue) * 100;
    }

    private function getPreviousPeriodRevenue(): float
    {
        $days = (int) $this->period;
        $start = Carbon::now()->subDays($days * 2)->startOfDay();
        $end = Carbon::now()->subDays($days)->endOfDay();
        
        return Order::whereBetween('received_date', [$start, $end])
            ->where('channel_name', '!=', 'DIRECT')
            ->when($this->channel !== 'all', fn($query) => 
                $query->where('channel_name', $this->channel)
            )
            ->sum('total_charge');
    }

    private function getPeriodLabel(): string
    {
        return match ($this->period) {
            '1' => 'Last 24 hours',
            '7' => 'Last 7 days',
            '30' => 'Last 30 days',
            '90' => 'Last 90 days',
            default => "Last {$this->period} days",
        };
    }

    private function getFormattedDateRange(): string
    {
        $start = $this->dateRange->get('start');
        $end = $this->dateRange->get('end');
        
        return $start->format('M j') . ' - ' . $end->format('M j, Y');
    }

    private function calculateSuccessRate(SyncLog $syncLog): float
    {
        $total = ($syncLog->total_created ?? 0) + ($syncLog->total_updated ?? 0) + ($syncLog->total_failed ?? 0);
        
        if ($total === 0) {
            return 100.0;
        }
        
        $successful = ($syncLog->total_created ?? 0) + ($syncLog->total_updated ?? 0);
        
        return ($successful / $total) * 100;
    }

    public function render()
    {
        return view('livewire.dashboard.sales-dashboard')
            ->title('Sales Dashboard');
    }
}
