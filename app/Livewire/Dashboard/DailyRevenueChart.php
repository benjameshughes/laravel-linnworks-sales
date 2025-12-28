<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard;

use App\Services\Metrics\ChunkedMetricsCalculator;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
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

    // Public property for @entangle - raw daily breakdown data
    public array $dailyBreakdown = [];

    // Formatted chart data for Chart.js - Alpine watches this
    public array $chartData = [];

    public function mount(): void
    {
        $this->period = request('period', '7');
        $this->channel = request('channel', 'all');
        $this->status = request('status', 'all');

        $this->loadData();
    }

    public function updatedViewMode(): void
    {
        $this->formatChartData();
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

        $this->loadData();
    }

    private function loadData(): void
    {
        $periodEnum = \App\Enums\Period::tryFrom($this->period);

        // Custom periods: Use ChunkedMetricsCalculator (memory-safe DB aggregation)
        if ($this->customFrom || $this->customTo || ! $periodEnum?->isCacheable()) {
            $calculator = new ChunkedMetricsCalculator(
                period: $this->period,
                channel: $this->channel,
                status: $this->status,
                customFrom: $this->customFrom,
                customTo: $this->customTo
            );

            $data = $calculator->calculate();
            $this->dailyBreakdown = $data['daily_breakdown'];
            $this->formatChartData();

            return;
        }

        // Check cache
        $cacheKey = $periodEnum->cacheKey($this->channel, $this->status);
        $cached = Cache::get($cacheKey);

        if ($cached && isset($cached['daily_breakdown'])) {
            $this->dailyBreakdown = $cached['daily_breakdown'];
            $this->formatChartData();
        }

        // Cache miss? Keep existing data - don't clear what we have
    }

    private function formatChartData(): void
    {
        if (empty($this->dailyBreakdown)) {
            $this->chartData = ['labels' => [], 'datasets' => []];
            $this->dispatch('daily-revenue-chart-updated', data: $this->chartData);

            return;
        }

        $labels = array_column($this->dailyBreakdown, 'date');

        $this->chartData = $this->viewMode === 'orders_revenue'
            ? [
                'labels' => $labels,
                'datasets' => [
                    [
                        'label' => 'Orders',
                        'data' => array_column($this->dailyBreakdown, 'orders'),
                        'backgroundColor' => 'rgba(59, 130, 246, 0.8)',
                        'borderRadius' => 4,
                    ],
                    [
                        'label' => 'Revenue',
                        'data' => array_column($this->dailyBreakdown, 'revenue'),
                        'backgroundColor' => 'rgba(34, 197, 94, 0.8)',
                        'borderRadius' => 4,
                    ],
                ],
            ]
            : [
                'labels' => $labels,
                'datasets' => [
                    [
                        'label' => 'Items Sold',
                        'data' => array_column($this->dailyBreakdown, 'items'),
                        'backgroundColor' => 'rgba(168, 85, 247, 0.8)',
                        'borderRadius' => 4,
                    ],
                ],
            ];

        $this->dispatch('daily-revenue-chart-updated', data: $this->chartData);
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
