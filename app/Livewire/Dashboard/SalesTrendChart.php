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
 * Dead simple chart component.
 * - Fetches data from cache
 * - Passes to blade for Chart.js rendering
 * - Dispatches event when data changes
 */
final class SalesTrendChart extends Component
{
    public string $period = '7';

    public string $channel = 'all';

    public string $status = 'all';

    public ?string $customFrom = null;

    public ?string $customTo = null;

    public string $viewMode = 'revenue';

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

        $this->dispatchChartUpdate();
    }

    #[On('echo:cache-management,CacheWarmingCompleted')]
    public function handleCacheWarmed(): void
    {
        $this->dispatchChartUpdate();
    }

    private function dispatchChartUpdate(): void
    {
        $this->dispatch('sales-trend-updated', data: $this->chartData());
    }

    /**
     * Cache key for wire:key - forces remount when filters change
     */
    #[Computed]
    public function cacheKey(): string
    {
        $periodEnum = Period::tryFrom($this->period);

        if ($this->customFrom || $this->customTo) {
            return "chart_{$this->period}_{$this->channel}_{$this->status}_{$this->customFrom}_{$this->customTo}";
        }

        return $periodEnum?->cacheKey($this->channel, $this->status) ?? "chart_{$this->period}_{$this->channel}_{$this->status}";
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

    /**
     * Get chart data from cache or calculate fresh
     */
    public function chartData(): array
    {
        $breakdown = $this->getDailyBreakdown();

        if (empty($breakdown)) {
            return ['labels' => [], 'datasets' => []];
        }

        $labels = array_column($breakdown, 'date');

        if ($this->viewMode === 'revenue') {
            return [
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
        }

        return [
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

    public function render()
    {
        return view('livewire.dashboard.sales-trend-chart');
    }

    public function placeholder(array $params = [])
    {
        return view('livewire.placeholders.chart', $params);
    }
}
