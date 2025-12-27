<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard;

use App\Enums\Period;
use App\Services\Metrics\ChunkedMetricsCalculator;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Sales Trend Chart - Livewire fetches data, Alpine renders chart.
 *
 * Pattern:
 * - Livewire: data fetching only
 * - Alpine: chart rendering via @entangle
 * - wire:ignore: prevents DOM thrashing
 */
final class SalesTrendChart extends Component
{
    public string $period = '7';

    public string $channel = 'all';

    public string $status = 'all';

    public ?string $customFrom = null;

    public ?string $customTo = null;

    public string $viewMode = 'revenue';

    // Public property for @entangle - Alpine watches this
    public array $chartData = [];

    public function mount(): void
    {
        $this->period = request('period', '7');
        $this->channel = request('channel', 'all');
        $this->status = request('status', 'all');

        $this->refreshChartData();
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

        $this->refreshChartData();
    }

    #[On('echo:cache-management,CacheWarmingCompleted')]
    public function handleCacheWarmed(): void
    {
        $this->refreshChartData();
    }

    /**
     * Refresh chart data from cache or calculate fresh
     */
    private function refreshChartData(): void
    {
        $breakdown = $this->getDailyBreakdown();

        if (empty($breakdown)) {
            $this->chartData = ['labels' => [], 'datasets' => []];

            return;
        }

        $labels = array_column($breakdown, 'date');

        if ($this->viewMode === 'revenue') {
            $this->chartData = [
                'labels' => $labels,
                'datasets' => [
                    [
                        'label' => 'Revenue',
                        'data' => array_column($breakdown, 'revenue'),
                        'borderColor' => 'rgba(34, 197, 94, 1)',
                        'backgroundColor' => 'rgba(34, 197, 94, 0.1)',
                        'fill' => true,
                    ],
                ],
            ];
        } else {
            $this->chartData = [
                'labels' => $labels,
                'datasets' => [
                    [
                        'label' => 'Orders',
                        'data' => array_column($breakdown, 'orders'),
                        'borderColor' => 'rgba(59, 130, 246, 1)',
                        'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                        'fill' => true,
                    ],
                ],
            ];
        }
    }

    private function getDailyBreakdown(): array
    {
        $periodEnum = Period::tryFrom($this->period);

        // Custom periods: calculate fresh
        if ($this->customFrom || $this->customTo || ! $periodEnum?->isCacheable()) {
            $calculator = new ChunkedMetricsCalculator(
                period: $this->period,
                channel: $this->channel,
                status: $this->status,
                customFrom: $this->customFrom,
                customTo: $this->customTo
            );

            return $calculator->calculate()['daily_breakdown'];
        }

        // Standard periods: get from cache
        $cacheKey = $periodEnum->cacheKey($this->channel, $this->status);
        $cached = Cache::get($cacheKey);

        return $cached['daily_breakdown'] ?? [];
    }

    #[Computed]
    public function periodLabel(): string
    {
        if ($this->period === 'custom') {
            return 'Custom: '.Carbon::parse($this->customFrom)->format('M j').' - '.Carbon::parse($this->customTo)->format('M j, Y');
        }

        $periodEnum = Period::tryFrom($this->period);

        return $periodEnum?->label() ?? "Last {$this->period} days";
    }

    public function render()
    {
        return view('livewire.dashboard.sales-trend-chart');
    }

    public function placeholder(array $params = [])
    {
        return view('livewire.placeholders.chart', $params);
    }
}
