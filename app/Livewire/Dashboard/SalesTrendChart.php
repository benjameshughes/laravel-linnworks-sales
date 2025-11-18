<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard;

use App\Services\Metrics\Sales\SalesMetrics as SalesMetricsService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
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
            $dataValues = $dailyBreakdown->pluck($this->viewMode === 'revenue' ? 'revenue' : 'orders')->toArray();

            $this->chartData = [
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

            return;
        }

        // Check cache
        $cacheKey = $periodEnum->cacheKey($this->channel, $this->status);
        $cached = Cache::get($cacheKey);

        if ($cached && isset($cached['chart_line']) && isset($cached['chart_orders'])) {
            // Return cached chart data based on viewMode
            if ($this->viewMode === 'revenue') {
                $this->chartData = $cached['chart_line'];
            } else {
                $this->chartData = $cached['chart_orders'];
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
                    'grace' => '10%',
                    'grid' => [
                        'display' => true,
                        'color' => 'rgba(0, 0, 0, 0.05)',
                    ],
                    'ticks' => [
                        'padding' => 10,
                    ],
                ],
                'x' => [
                    'grid' => [
                        'display' => false,
                    ],
                    'offset' => true,
                    'ticks' => [
                        'padding' => 10,
                        'autoSkip' => true,
                        'maxRotation' => 0,
                    ],
                ],
            ],
            'elements' => [
                'line' => [
                    'tension' => 0.4,
                    'borderWidth' => 2,
                ],
                'point' => [
                    'radius' => 4,
                    'hoverRadius' => 6,
                    'hitRadius' => 10,
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
        return "sales-trend-{$this->viewMode}-{$this->period}-{$this->channel}-{$this->status}-{$this->customFrom}-{$this->customTo}";
    }

    public function setViewMode(string $mode): void
    {
        $this->viewMode = $mode;
        $this->calculateChartData();
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
