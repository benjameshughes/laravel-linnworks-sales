<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard;

use App\Services\Metrics\Sales\SalesMetrics as SalesMetricsService;
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

    // Public property for @entangle
    public array $chartData = [];

    public function mount(): void
    {
        $this->period = request('period', '7');
        $this->channel = request('channel', 'all');
        $this->status = request('status', 'all');

        $this->calculateChartData();
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

        $this->calculateChartData();
    }

    #[On('echo:cache-management,CacheWarmingCompleted')]
    public function refreshAfterCacheWarming(): void
    {
        // Trigger re-render - recalculate chart data with fresh cache
        $this->calculateChartData();
    }

    public function updatedViewMode(): void
    {
        $this->calculateChartData();
    }

    private function calculateChartData(): void
    {
        $periodEnum = \App\Enums\Period::tryFrom($this->period);

        // Can't cache custom periods
        if ($this->customFrom || $this->customTo || ! $periodEnum?->isCacheable()) {
            $dailyBreakdown = app(SalesMetricsService::class)->getDailyRevenueData(
                period: $this->period,
                customFrom: $this->customFrom,
                customTo: $this->customTo
            );

            // Transform daily breakdown into Chart.js format
            $labels = $dailyBreakdown->pluck('date')->toArray();

            if ($this->viewMode === 'orders_revenue') {
                $this->chartData = [
                    'labels' => $labels,
                    'datasets' => [
                        [
                            'label' => 'Orders',
                            'data' => $dailyBreakdown->pluck('orders')->toArray(),
                            'borderColor' => 'rgb(59, 130, 246)',
                            'backgroundColor' => 'rgba(59, 130, 246, 0.8)',
                            'type' => 'bar',
                        ],
                        [
                            'label' => 'Revenue',
                            'data' => $dailyBreakdown->pluck('revenue')->toArray(),
                            'borderColor' => 'rgb(34, 197, 94)',
                            'backgroundColor' => 'rgba(34, 197, 94, 0.8)',
                            'type' => 'bar',
                        ],
                    ],
                ];
            } else {
                $this->chartData = [
                    'labels' => $labels,
                    'datasets' => [
                        [
                            'label' => 'Items Sold',
                            'data' => $dailyBreakdown->pluck('items')->toArray(),
                            'borderColor' => 'rgb(168, 85, 247)',
                            'backgroundColor' => 'rgba(168, 85, 247, 0.8)',
                        ],
                    ],
                ];
            }

            return;
        }

        // Check cache
        $cacheKey = $periodEnum->cacheKey($this->channel, $this->status);
        $cached = Cache::get($cacheKey);

        if ($cached && isset($cached['chart_orders_revenue']) && isset($cached['chart_items'])) {
            // Return cached chart data based on viewMode
            if ($this->viewMode === 'orders_revenue') {
                $this->chartData = $cached['chart_orders_revenue'];
            } else {
                $this->chartData = $cached['chart_items'];
            }

            return;
        }

        // Cache miss - return empty array to prevent OOM
        $this->chartData = ['labels' => [], 'datasets' => []];
    }

    #[Computed]
    public function chartOptions(): array
    {
        return [
            'responsive' => true,
            'maintainAspectRatio' => false,
            'animation' => [
                'duration' => 3000,
            ],
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'top',
                ],
                'tooltip' => [
                    'enabled' => true,
                    'mode' => 'index',
                    'intersect' => false,
                ],
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'grid' => [
                        'display' => true,
                        'color' => 'rgba(0, 0, 0, 0.05)',
                    ],
                ],
                'x' => [
                    'grid' => [
                        'display' => false,
                    ],
                ],
            ],
            'elements' => [
                'bar' => [
                    'borderRadius' => 4,
                    'borderWidth' => 0,
                ],
            ],
        ];
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
        $this->calculateChartData();
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
