<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard;

use App\Services\Metrics\Sales\SalesMetrics as SalesMetricsService;
use Carbon\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

final class DailyRevenueChart extends Component
{
    public string $period = '7';

    public string $channel = 'all';

    public string $status = 'all';

    public ?string $customFrom = null;

    public ?string $customTo = null;

    public string $viewMode = 'orders_revenue'; // 'orders_revenue' or 'items'

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
        $data = app(SalesMetricsService::class)->getDailyRevenueData(
            period: $this->period,
            customFrom: $this->customFrom,
            customTo: $this->customTo
        );

        // Return as array for chart consumption
        return $data->toArray();
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
        return "daily-bar-{$this->viewMode}-{$this->period}-{$this->channel}-{$this->status}-{$this->customFrom}-{$this->customTo}";
    }

    public function setViewMode(string $mode): void
    {
        $this->viewMode = $mode;
    }

    public function render()
    {
        return view('livewire.dashboard.daily-revenue-chart');
    }

    /**
     * Skeleton loader shown while lazy loading
     */
    public function placeholder(array $params = [])
    {
        return view('livewire.placeholders.chart', $params);
    }
}
