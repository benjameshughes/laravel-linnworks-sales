<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard;

use App\Services\Metrics\ChunkedMetricsCalculator;
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

    // Public property for @entangle - raw daily breakdown data
    public array $dailyBreakdown = [];

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

    private function calculateChartData(): void
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

            // Store raw data - Alpine will format for Chart.js
            $this->dailyBreakdown = $data['daily_breakdown'];

            return;
        }

        // Check cache
        $cacheKey = $periodEnum->cacheKey($this->channel, $this->status);
        $cached = Cache::get($cacheKey);

        if ($cached && isset($cached['daily_breakdown'])) {
            $this->dailyBreakdown = $cached['daily_breakdown'];
        }

        // Cache miss? Keep existing data - don't clear what we have
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

    /**
     * Format data for Chart.js
     */
    public function chartData(): array
    {
        $labels = array_column($this->dailyBreakdown, 'date');

        if ($this->viewMode === 'revenue') {
            return [
                'labels' => $labels,
                'datasets' => [
                    [
                        'label' => 'Revenue',
                        'data' => array_column($this->dailyBreakdown, 'revenue'),
                        'borderColor' => 'rgba(34, 197, 94, 1)',
                        'backgroundColor' => 'rgba(34, 197, 94, 0.1)',
                        'fill' => true,
                    ],
                ],
            ];
        }

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Orders',
                    'data' => array_column($this->dailyBreakdown, 'orders'),
                    'borderColor' => 'rgba(59, 130, 246, 1)',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                    'fill' => true,
                ],
            ],
        ];
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
