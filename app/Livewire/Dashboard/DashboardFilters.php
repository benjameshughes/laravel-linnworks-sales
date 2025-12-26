<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard;

use App\Jobs\SyncRecentOrdersJob;
use App\Models\SyncLog;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Dashboard Filters Component
 *
 * Simple sync flow:
 * 1. User clicks Sync → isSyncing=true, message="Starting..."
 * 2. SyncStarted event → message="Starting sync..."
 * 3. SyncProgressUpdated events → message updates with progress
 * 4. SyncCompleted event → message="Sync complete: X created, Y updated"
 * 5. CacheWarmingStarted event → message="Crunching the numbers..."
 * 6. CacheWarmingCompleted event → isSyncing=false, show last sync time
 */
final class DashboardFilters extends Component
{
    public string $period;

    public string $channel = 'all';

    public string $status = 'all';

    public ?string $customFrom = null;

    public ?string $customTo = null;

    // Simple sync state - no caching needed
    public bool $isSyncing = false;

    public string $syncMessage = '';

    public int $rateLimitSeconds = 0;

    public function mount(): void
    {
        $defaultPeriod = config('dashboard.default_period', \App\Enums\Period::SEVEN_DAYS);
        $this->period = $defaultPeriod instanceof \App\Enums\Period ? $defaultPeriod->value : $defaultPeriod;

        $this->customTo = Carbon::now()->format('Y-m-d');
        $this->customFrom = Carbon::now()->subDays(7)->format('Y-m-d');

        $this->checkRateLimit();
    }

    public function checkRateLimit(): void
    {
        $key = 'sync-orders:'.auth()->id();

        if (RateLimiter::tooManyAttempts($key, 1)) {
            $this->rateLimitSeconds = RateLimiter::availableIn($key);
        } else {
            $this->rateLimitSeconds = 0;
        }
    }

    public function updated($property): void
    {
        if ($property === 'period' && $this->period !== 'custom') {
            $this->customFrom = null;
            $this->customTo = null;
        }

        // Don't auto-dispatch for custom date changes - wait for Apply button
        if (in_array($property, ['period', 'channel', 'status']) && $this->period !== 'custom') {
            $this->dispatch('filters-updated',
                period: $this->period,
                channel: $this->channel,
                status: $this->status,
                customFrom: null,
                customTo: null
            );
        }

        // For non-custom periods, dispatch immediately on change
        if (in_array($property, ['channel', 'status']) && $this->period === 'custom' && $this->customFrom && $this->customTo) {
            $this->dispatch('filters-updated',
                period: $this->period,
                channel: $this->channel,
                status: $this->status,
                customFrom: $this->customFrom,
                customTo: $this->customTo
            );
        }
    }

    /**
     * Apply the custom date range and dispatch filters.
     */
    public function applyCustomRange(): void
    {
        if (! $this->customFrom || ! $this->customTo) {
            return;
        }

        // Validate dates
        $from = Carbon::parse($this->customFrom);
        $to = Carbon::parse($this->customTo);

        if ($from->isAfter($to)) {
            // Swap if reversed
            $this->customFrom = $to->format('Y-m-d');
            $this->customTo = $from->format('Y-m-d');
        }

        // Switch to custom period mode
        $this->period = 'custom';

        $this->dispatch('filters-updated',
            period: 'custom',
            channel: $this->channel,
            status: $this->status,
            customFrom: $this->customFrom,
            customTo: $this->customTo
        );
    }

    /**
     * Set quick date range presets.
     */
    public function setQuickRange(string $range): void
    {
        $now = Carbon::now();

        [$from, $to] = match ($range) {
            'this_month' => [
                $now->copy()->startOfMonth(),
                $now->copy()->endOfMonth(),
            ],
            'last_month' => [
                $now->copy()->subMonth()->startOfMonth(),
                $now->copy()->subMonth()->endOfMonth(),
            ],
            'this_quarter' => [
                $now->copy()->startOfQuarter(),
                $now->copy()->endOfQuarter(),
            ],
            'this_year' => [
                $now->copy()->startOfYear(),
                $now->copy(),
            ],
            default => [
                $now->copy()->subDays(7),
                $now->copy(),
            ],
        };

        $this->customFrom = $from->format('Y-m-d');
        $this->customTo = $to->format('Y-m-d');
    }

    public function syncOrders(): void
    {
        if ($this->isSyncing) {
            return;
        }

        $key = 'sync-orders:'.auth()->id();

        if (RateLimiter::tooManyAttempts($key, 1)) {
            $this->rateLimitSeconds = RateLimiter::availableIn($key);

            $this->dispatch('notification', [
                'message' => "Please wait {$this->rateLimitSeconds} seconds before syncing again.",
                'type' => 'warning',
            ]);

            return;
        }

        $this->rateLimitSeconds = 0;
        RateLimiter::hit($key, 120);

        $this->isSyncing = true;
        $this->syncMessage = 'Starting sync...';

        SyncRecentOrdersJob::dispatch(startedBy: 'user-'.auth()->id());

        $this->dispatch('notification', [
            'message' => 'Sync started. Updates will appear automatically.',
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
        $channels = Cache::get('analytics:available_channels', collect());

        return $channels->map(fn ($channel) => collect([
            'name' => $channel,
            'label' => $channel,
        ]));
    }

    #[Computed]
    public function lastSyncInfo(): Collection
    {
        $lastSync = SyncLog::where('sync_type', SyncLog::TYPE_OPEN_ORDERS)
            ->whereNotNull('completed_at')
            ->latest('completed_at')
            ->first();

        if (! $lastSync) {
            return collect([
                'time_human' => 'Never synced',
                'timestamp' => null,
                'created' => 0,
                'updated' => 0,
                'status' => 'never',
            ]);
        }

        return collect([
            'time_human' => $lastSync->completed_at->diffForHumans(),
            'timestamp' => $lastSync->completed_at->toIso8601String(),
            'elapsed_seconds' => (int) $lastSync->completed_at->diffInSeconds(now()),
            'created' => $lastSync->total_created ?? 0,
            'updated' => $lastSync->total_updated ?? 0,
            'failed' => $lastSync->total_failed ?? 0,
            'status' => 'success',
        ]);
    }

    #[Computed]
    public function formattedDateRange(): string
    {
        $start = $this->dateRange->get('start');
        $end = $this->dateRange->get('end');

        return $start->format('M j').' - '.$end->format('M j, Y');
    }

    #[Computed]
    public function totalOrders(): int
    {
        if ($this->period === 'custom') {
            return 0;
        }

        $periodEnum = \App\Enums\Period::tryFrom($this->period);
        if (! $periodEnum || ! $periodEnum->isCacheable()) {
            return 0;
        }

        $cacheKey = $periodEnum->cacheKey($this->channel, $this->status);
        $cached = Cache::get($cacheKey);

        if (! $cached) {
            return 0;
        }

        return match ($this->status) {
            'open' => (int) ($cached['open_orders'] ?? 0),
            'processed' => (int) ($cached['processed_orders'] ?? 0),
            'open_paid' => (int) ($cached['orders'] ?? 0),
            default => (int) ($cached['orders'] ?? 0),
        };
    }

    // ========================================
    // Event Handlers - Simple state updates
    // ========================================

    #[On('echo:sync-progress,SyncStarted')]
    public function handleSyncStarted(array $data): void
    {
        $this->isSyncing = true;
        $this->syncMessage = 'Starting sync...';
    }

    #[On('echo:sync-progress,SyncProgressUpdated')]
    public function handleSyncProgress(array $data): void
    {
        $this->syncMessage = $data['message'] ?? 'Syncing...';
    }

    #[On('echo:sync-progress,SyncCompleted')]
    public function handleSyncCompleted(array $data): void
    {
        $this->syncMessage = $data['success']
            ? "Synced: {$data['created']} new, {$data['updated']} updated"
            : 'Sync completed with errors';

        $this->dispatch('notification', [
            'message' => $this->syncMessage,
            'type' => $data['success'] ? 'success' : 'warning',
        ]);
    }

    #[On('echo:cache-management,CacheWarmingStarted')]
    public function handleCacheWarmingStarted(array $data): void
    {
        $this->syncMessage = 'Crunching the numbers...';
    }

    #[On('echo:cache-management,CacheWarmingCompleted')]
    public function handleCacheWarmingCompleted(array $data): void
    {
        $this->isSyncing = false;
        $this->syncMessage = '';

        // Refresh computed properties
        unset($this->lastSyncInfo);
        unset($this->totalOrders);

        // Notify other components
        $this->dispatch('filters-updated',
            period: $this->period,
            channel: $this->channel,
            status: $this->status,
            customFrom: $this->customFrom,
            customTo: $this->customTo
        );
    }

    public function render()
    {
        return view('livewire.dashboard.dashboard-filters');
    }
}
