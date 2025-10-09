<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard;

use App\Actions\Linnworks\Orders\SyncRecentOrders;
use App\Models\Order;
use App\Models\SyncLog;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Throwable;

final class DashboardFilters extends Component
{
    public string $period = '7';
    public string $channel = 'all';
    public string $searchTerm = '';
    public ?string $customFrom = null;
    public ?string $customTo = null;

    public bool $isSyncing = false;
    public string $syncStage = '';
    public string $syncMessage = '';
    public int $syncCount = 0;

    public function mount(): void
    {
        // Initialize custom dates to last 7 days
        $this->customTo = Carbon::now()->format('Y-m-d');
        $this->customFrom = Carbon::now()->subDays(7)->format('Y-m-d');
    }

    public function updated($property): void
    {
        if (in_array($property, ['period', 'channel', 'searchTerm', 'customFrom', 'customTo'])) {
            $this->dispatch('filters-updated',
                period: $this->period,
                channel: $this->channel,
                searchTerm: $this->searchTerm,
                customFrom: $this->customFrom,
                customTo: $this->customTo
            );
        }
    }

    public function syncOrders(): void
    {
        if ($this->isSyncing) {
            return;
        }

        $this->isSyncing = true;
        $this->syncStage = 'queued';
        $this->syncMessage = 'Sync job queued...';

        try {
            if ($this->period === 'custom') {
                $windowDays = Carbon::parse($this->customFrom)->diffInDays(Carbon::parse($this->customTo)) + 1;
            } elseif ($this->period === 'yesterday') {
                $windowDays = 1;
            } else {
                $windowDays = (int) $this->period;
            }

            $processedWindow = max((int) config('linnworks.sync.default_date_range', 30), $windowDays);

            \App\Jobs\SyncRecentOrdersJob::dispatch(
                openWindowDays: $windowDays,
                processedWindowDays: $processedWindow,
                forceUpdate: false,
                userId: auth()->id(),
            );

            $this->dispatch('notification', [
                'message' => 'Sync started in background. Updates will appear automatically.',
                'type' => 'info',
            ]);
        } catch (Throwable $exception) {
            report($exception);

            $this->isSyncing = false;

            $this->dispatch('notification', [
                'message' => 'Failed to queue sync job. See logs for details.',
                'type' => 'error',
            ]);
        }
    }

    public function refreshDashboard(): void
    {
        $this->dispatch('filters-updated',
            period: $this->period,
            channel: $this->channel,
            searchTerm: $this->searchTerm,
            customFrom: $this->customFrom,
            customTo: $this->customTo
        );

        $this->dispatch('notification', [
            'message' => 'Dashboard data refreshed',
            'type' => 'info',
        ]);
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
    public function availableChannels(): Collection
    {
        return Order::select('channel_name')
            ->where('channel_name', '!=', 'DIRECT')
            ->distinct()
            ->get()
            ->map(fn($order) => collect([
                'name' => $order->channel_name,
                'label' => $order->channel_name,
            ]));
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

    #[Computed]
    public function formattedDateRange(): string
    {
        $start = $this->dateRange->get('start');
        $end = $this->dateRange->get('end');

        return $start->format('M j') . ' - ' . $end->format('M j, Y');
    }

    #[Computed]
    public function totalOrders(): int
    {
        return Order::whereBetween('received_date', [
                $this->dateRange->get('start'),
                $this->dateRange->get('end')
            ])
            ->where('channel_name', '!=', 'DIRECT')
            ->when($this->channel !== 'all', fn($query) =>
                $query->where('channel_name', $this->channel)
            )
            ->count();
    }

    #[On('echo:sync-progress,SyncStarted')]
    public function handleSyncStarted(array $data): void
    {
        $this->isSyncing = true;
        $this->syncStage = 'started';
        $this->syncMessage = 'Starting sync...';
        $this->syncCount = 0;
    }

    #[On('echo:sync-progress,SyncProgressUpdated')]
    public function handleSyncProgress(array $data): void
    {
        $this->syncStage = $data['stage'];
        $this->syncMessage = $data['message'];
        $this->syncCount = $data['count'] ?? 0;
    }

    #[On('echo:sync-progress,SyncCompleted')]
    public function handleSyncCompleted(array $data): void
    {
        $this->isSyncing = false;
        $this->syncStage = 'completed';
        $this->syncMessage = $data['success']
            ? "Sync completed: {$data['created']} created, {$data['updated']} updated"
            : 'Sync completed with errors';

        $this->dispatch('filters-updated',
            period: $this->period,
            channel: $this->channel,
            searchTerm: $this->searchTerm,
            customFrom: $this->customFrom,
            customTo: $this->customTo
        );

        $this->dispatch('notification', [
            'message' => $this->syncMessage,
            'type' => $data['success'] ? 'success' : 'warning',
        ]);
    }

    public function render()
    {
        return view('livewire.dashboard.dashboard-filters');
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
}
