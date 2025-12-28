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
 * Livewire re-renders → Blade renders <x-chart> → Chart.js initializes. Done.
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
    }

    #[Computed]
    public function chartData(): array
    {
        $breakdown = $this->getDailyBreakdown();

        if (empty($breakdown)) {
            return ['labels' => [], 'datasets' => []];
        }

        $labels = array_column($breakdown, 'date');

        return $this->viewMode === 'revenue'
            ? [
                'labels' => $labels,
                'datasets' => [[
                    'label' => 'Revenue',
                    'data' => array_column($breakdown, 'revenue'),
                    'borderColor' => 'rgba(34, 197, 94, 1)',
                    'backgroundColor' => 'rgba(34, 197, 94, 0.1)',
                    'fill' => true,
                ]],
            ]
            : [
                'labels' => $labels,
                'datasets' => [[
                    'label' => 'Orders',
                    'data' => array_column($breakdown, 'orders'),
                    'borderColor' => 'rgba(59, 130, 246, 1)',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                    'fill' => true,
                ]],
            ];
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

    private function getDailyBreakdown(): array
    {
        $periodEnum = Period::tryFrom($this->period);

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

        $cacheKey = $periodEnum->cacheKey($this->channel, $this->status);
        $cached = Cache::get($cacheKey);

        return $cached['daily_breakdown'] ?? [];
    }

    public function render()
    {
        return view('livewire.dashboard.sales-trend-chart');
    }
}
