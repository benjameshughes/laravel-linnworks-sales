<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard;

use App\Actions\Linnworks\Orders\SyncRecentOrders;
use App\Models\Order;
use App\Models\SyncLog;
use App\Services\Metrics\SalesMetrics;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Throwable;

final class SalesDashboard extends Component
{
    public string $period = '7';
    public string $channel = 'all';
    public string $searchTerm = '';
    public bool $showMetrics = true;
    public bool $showCharts = true;
    public bool $isSyncing = false;
    public string $syncStage = '';
    public string $syncMessage = '';
    public int $syncCount = 0;
    public ?string $customFrom = null;
    public ?string $customTo = null;
    
    public function mount()
    {
        // Initialize custom dates to last 7 days
        $this->customTo = Carbon::now()->format('Y-m-d');
        $this->customFrom = Carbon::now()->subDays(7)->format('Y-m-d');
    }

    #[Computed]
    public function dateRange(): Collection
    {
        if ($this->period === 'custom') {
            return collect([
                'start' => Carbon::parse($this->customFrom)->startOfDay(),
                'end' => Carbon::parse($this->customTo)->endOfDay(),
            ]);
        }

        if ($this->period === 'yesterday') {
            return collect([
                'start' => Carbon::yesterday()->startOfDay(),
                'end' => Carbon::yesterday()->endOfDay(),
            ]);
        }

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
    public function salesMetrics(): SalesMetrics
    {
        return new SalesMetrics($this->orders);
    }
    
    #[Computed]
    public function metrics(): Collection
    {
        if ($this->period === 'custom') {
            $periodDays = Carbon::parse($this->customFrom)->diffInDays(Carbon::parse($this->customTo)) + 1;
        } elseif ($this->period === 'yesterday') {
            $periodDays = 1;
        } else {
            $periodDays = (int) $this->period;
        }

        $previousPeriodData = $this->getPreviousPeriodOrders();

        return $this->salesMetrics->getMetricsSummary($periodDays, $previousPeriodData);
    }

    #[Computed]
    public function periodSummary(): Collection
    {
        if ($this->period === 'custom') {
            $days = Carbon::parse($this->customFrom)->diffInDays(Carbon::parse($this->customTo)) + 1;
        } elseif ($this->period === 'yesterday') {
            $days = 1;
        } else {
            $days = (int) $this->period;
        }

        $totalOrders = $this->metrics->get('total_orders');
        $processedOrders = $this->orders->where('is_processed', true)->count();
        $openOrders = $this->orders->where('is_open', true)->count();

        return collect([
            'period_label' => $this->getPeriodLabel(),
            'date_range' => $this->getFormattedDateRange(),
            'orders_per_day' => $days > 0 ? $totalOrders / $days : 0,
            'processed_count' => $processedOrders,
            'open_count' => $openOrders,
            'processed_percentage' => $totalOrders > 0 ? ($processedOrders / $totalOrders) * 100 : 0,
        ]);
    }

    #[Computed]
    public function topProducts(): Collection
    {
        return $this->salesMetrics->topProducts();
    }

    #[Computed]
    public function topChannels(): Collection
    {
        return $this->salesMetrics->topChannels();
    }

    #[Computed]
    public function recentOrders(): Collection
    {
        return $this->salesMetrics->recentOrders();
    }

    #[Computed]
    public function availableChannels(): Collection
    {
        return $this->salesMetrics->availableChannels();
    }

    #[Computed]
    public function salesLineChartData(): array
    {
        return $this->salesMetrics->getLineChartData($this->period);
    }
    
    #[Computed]
    public function salesBarChartData(): array
    {
        return $this->salesMetrics->getBarChartData($this->period);
    }
    
    #[Computed]
    public function channelDoughnutChartData(): array
    {
        return $this->salesMetrics->getDoughnutChartData();
    }
    
    #[Computed]
    public function dailySalesChart(): Collection
    {
        return $this->salesMetrics->dailySalesData($this->period);
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
        if ($this->isSyncing) {
            return;
        }

        $this->isSyncing = true;

        try {
            if ($this->period === 'custom') {
                $windowDays = Carbon::parse($this->customFrom)->diffInDays(Carbon::parse($this->customTo)) + 1;
            } elseif ($this->period === 'yesterday') {
                $windowDays = 1;
            } else {
                $windowDays = (int) $this->period;
            }

            $processedWindow = max((int) config('linnworks.sync.default_date_range', 30), $windowDays);

            app(SyncRecentOrders::class)->handle(
                openWindowDays: $windowDays,
                processedWindowDays: $processedWindow,
                forceUpdate: false,
                userId: auth()->id(),
            );

            // Real-time updates are handled via broadcast events
            // The final notification will be sent by handleSyncCompleted()
        } catch (Throwable $exception) {
            report($exception);

            $this->isSyncing = false;

            $this->dispatch('notification', [
                'message' => 'Failed to sync orders. See logs for details.',
                'type' => 'error',
            ]);
        }
    }

    public function refreshDashboard(): void
    {
        $this->dispatch('$refresh');

        $this->dispatch('notification', [
            'message' => 'Dashboard data refreshed',
            'type' => 'info',
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

    #[On('open-orders-synced')]
    public function handleOpenOrdersSynced(array $summary): void
    {
        $this->dispatch('$refresh');
    }

    #[On('echo:sync-progress,sync.started')]
    public function handleSyncStarted(array $data): void
    {
        $this->isSyncing = true;
        $this->syncStage = 'started';
        $this->syncMessage = 'Starting sync...';
        $this->syncCount = 0;
    }

    #[On('echo:sync-progress,sync.progress')]
    public function handleSyncProgress(array $data): void
    {
        $this->syncStage = $data['stage'];
        $this->syncMessage = $data['message'];
        $this->syncCount = $data['count'] ?? 0;
    }

    #[On('echo:sync-progress,sync.completed')]
    public function handleSyncCompleted(array $data): void
    {
        $this->isSyncing = false;
        $this->syncStage = 'completed';
        $this->syncMessage = $data['success']
            ? "Sync completed: {$data['created']} created, {$data['updated']} updated"
            : 'Sync completed with errors';

        $this->dispatch('$refresh');

        $this->dispatch('notification', [
            'message' => $this->syncMessage,
            'type' => $data['success'] ? 'success' : 'warning',
        ]);
    }


    private function getPreviousPeriodOrders(): Collection
    {
        if ($this->period === 'custom') {
            $days = Carbon::parse($this->customFrom)->diffInDays(Carbon::parse($this->customTo)) + 1;
            $start = Carbon::parse($this->customFrom)->subDays($days)->startOfDay();
            $end = Carbon::parse($this->customFrom)->subDay()->endOfDay();
        } elseif ($this->period === 'yesterday') {
            $start = Carbon::now()->subDays(2)->startOfDay();
            $end = Carbon::now()->subDays(2)->endOfDay();
        } else {
            $days = (int) $this->period;
            $start = Carbon::now()->subDays($days * 2)->startOfDay();
            $end = Carbon::now()->subDays($days)->endOfDay();
        }

        return Order::whereBetween('received_date', [$start, $end])
            ->where('channel_name', '!=', 'DIRECT')
            ->when($this->channel !== 'all', fn($query) =>
                $query->where('channel_name', $this->channel)
            )
            ->get();
    }

    private function getPeriodLabel(): string
    {
        if ($this->period === 'custom') {
            return 'Custom: ' . Carbon::parse($this->customFrom)->format('M j') . ' - ' . Carbon::parse($this->customTo)->format('M j, Y');
        }

        return match ($this->period) {
            '1' => 'Last 24 hours',
            'yesterday' => 'Yesterday',
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
