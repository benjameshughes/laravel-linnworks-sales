<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard;

use App\Services\Metrics\Sales\SalesMetrics as SalesMetricsService;
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
        $dailyBreakdown = app(SalesMetricsService::class)->getDailyRevenueData(
            period: $this->period,
            customFrom: $this->customFrom,
            customTo: $this->customTo
        );

        // Transform daily breakdown into Chart.js format
        $labels = $dailyBreakdown->pluck('date')->toArray();
        $dataValues = $dailyBreakdown->pluck($this->viewMode === 'revenue' ? 'revenue' : 'orders')->toArray();

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => $this->viewMode === 'revenue' ? 'Revenue' : 'Orders',
                    'data' => $dataValues,
                    'borderColor' => 'rgb(59, 130, 246)',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                    'fill' => true,
                ],
            ],
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
        return view('livewire.dashboard.sales-trend-chart');
    }

    /**
     * Skeleton loader shown while lazy loading
     */
    public function placeholder(array $params = [])
    {
        return view('livewire.placeholders.chart', $params);
    }
}
