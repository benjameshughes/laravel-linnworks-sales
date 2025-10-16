<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard;

use App\Services\Dashboard\DashboardDataService;
use Carbon\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

final class SalesTrendChart extends Component
{
    public string $period = '7';

    public string $channel = 'all';

    public string $status = 'all';

    public ?string $customFrom = null;

    public ?string $customTo = null;

    public string $viewMode = 'revenue'; // 'revenue' or 'orders'

    public function mount(): void
    {
        $this->period = request('period', '7');
        $this->channel = request('channel', 'all');
        $this->status = request('status', 'all');
    }

    #[On('filters-updated')]
    public function updateFilters(
        string $period,
        string $channel,
        string $status = 'all',
        ?string $customFrom = null,
        ?string $customTo = null
    ): void {
        $this->period = $period;
        $this->channel = $channel;
        $this->status = $status;
        $this->customFrom = $customFrom;
        $this->customTo = $customTo;
    }

    #[On('echo:cache-management,CacheWarmingCompleted')]
    public function refreshAfterCacheWarming(): void
    {
        // Trigger re-render - computed properties will fetch fresh cache
        // No manual cache clearing needed - service always reads fresh from cache store
    }

    #[Computed]
    public function chartData(): array
    {
        // CACHE-ONLY MODE: No fallback to prevent OOM on large periods
        $service = app(DashboardDataService::class);
        if ($service->canUseCachedMetrics($this->period, $this->channel, $this->status, $this->customFrom, $this->customTo)) {
            $cached = $service->getCachedMetrics($this->period, $this->channel, $this->status);

            \Illuminate\Support\Facades\Log::debug('[SalesTrendChart] chartData() called', [
                'period' => $this->period,
                'channel' => $this->channel,
                'status' => $this->status,
                'viewMode' => $this->viewMode,
                'hasCached' => !!$cached,
                'cachedKeys' => $cached ? array_keys($cached) : null,
            ]);

            if ($cached) {
                if ($this->viewMode === 'orders' && isset($cached['chart_orders'])) {
                    return $cached['chart_orders'];
                }
                if ($this->viewMode === 'revenue' && isset($cached['chart_line'])) {
                    return $cached['chart_line'];
                }
            }
        }

        \Illuminate\Support\Facades\Log::warning('[SalesTrendChart] Returning empty chart data', [
            'period' => $this->period,
            'channel' => $this->channel,
            'status' => $this->status,
        ]);

        // Return empty chart if cache unavailable (prevents OOM on large datasets)
        return [
            'labels' => [],
            'datasets' => [],
        ];
    }

    #[Computed]
    public function chartOptions(): array
    {
        // Extract options from cached chart data (for single-day padding)
        $data = $this->chartData;
        return $data['options'] ?? [];
    }

    #[Computed]
    public function periodLabel(): string
    {
        if ($this->period === 'custom') {
            return 'Custom: '.Carbon::parse($this->customFrom)->format('M j').' - '.Carbon::parse($this->customTo)->format('M j, Y');
        }

        $periodEnum = \App\Enums\Period::tryFrom($this->period);

        return $periodEnum?->label() ?? "Last {$this->period} days";
    }

    #[Computed]
    public function chartKey(): string
    {
        // Include viewMode so changing tabs recreates the component
        return "sales-trend-{$this->viewMode}-{$this->period}-{$this->channel}-{$this->status}-{$this->customFrom}-{$this->customTo}";
    }

    public function setViewMode(string $mode): void
    {
        $this->viewMode = $mode;
    }

    public function render()
    {
        \Illuminate\Support\Facades\Log::debug('[SalesTrendChart] render() called', [
            'viewMode' => $this->viewMode,
            'period' => $this->period,
        ]);

        return view('livewire.dashboard.sales-trend-chart');
    }
}
