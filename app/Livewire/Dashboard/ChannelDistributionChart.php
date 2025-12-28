<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard;

use App\Services\Metrics\ChunkedMetricsCalculator;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\On;
use Livewire\Component;

final class ChannelDistributionChart extends Component
{
    public string $period = '7';

    public string $channel = 'all';

    public string $status = 'all';

    public ?string $customFrom = null;

    public ?string $customTo = null;

    public string $viewMode = 'detailed'; // 'detailed' (subsource breakdown) or 'grouped' (channel only)

    // Public property for @entangle - channel distribution data
    public array $channelData = [];

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
            $this->channelData = $data['top_channels']->toArray();
            $this->formatChartData();

            return;
        }

        // Check cache for standard periods
        $cacheKey = $periodEnum->cacheKey($this->channel, $this->status);
        $cached = Cache::get($cacheKey);

        if ($cached && isset($cached['top_channels'])) {
            $this->channelData = is_array($cached['top_channels'])
                ? $cached['top_channels']
                : $cached['top_channels']->toArray();
            $this->formatChartData();
        }

        // Cache miss? Keep existing data - don't clear what we have
    }

    private function formatChartData(): void
    {
        $colors = [
            'rgba(59, 130, 246, 0.8)',   // blue
            'rgba(34, 197, 94, 0.8)',    // green
            'rgba(168, 85, 247, 0.8)',   // purple
            'rgba(251, 146, 60, 0.8)',   // orange
            'rgba(236, 72, 153, 0.8)',   // pink
            'rgba(20, 184, 166, 0.8)',   // teal
        ];

        if (empty($this->channelData)) {
            $this->chartData = ['labels' => [], 'datasets' => []];
            $this->dispatch('channel-distribution-chart-updated', data: $this->chartData);

            return;
        }

        if ($this->viewMode === 'grouped') {
            // Group by channel (parent)
            $grouped = [];
            foreach ($this->channelData as $item) {
                $channel = $item['channel'] ?? 'Unknown';
                if (! isset($grouped[$channel])) {
                    $grouped[$channel] = 0;
                }
                $grouped[$channel] += (float) ($item['revenue'] ?? 0);
            }

            $this->chartData = [
                'labels' => array_keys($grouped),
                'datasets' => [
                    [
                        'data' => array_values($grouped),
                        'backgroundColor' => array_slice($colors, 0, count($grouped)),
                        'borderWidth' => 0,
                    ],
                ],
            ];

            $this->dispatch('channel-distribution-chart-updated', data: $this->chartData);

            return;
        }

        // Detailed view - show subsource
        $labels = array_map(
            fn ($item) => $item['subsource'] ?? $item['channel'] ?? 'Unknown',
            $this->channelData
        );
        $data = array_map(
            fn ($item) => (float) ($item['revenue'] ?? 0),
            $this->channelData
        );

        $this->chartData = [
            'labels' => $labels,
            'datasets' => [
                [
                    'data' => $data,
                    'backgroundColor' => array_slice($colors, 0, count($labels)),
                    'borderWidth' => 0,
                ],
            ],
        ];

        $this->dispatch('channel-distribution-chart-updated', data: $this->chartData);
    }

    public function render()
    {
        return view('livewire.dashboard.channel-distribution-chart');
    }

    /**
     * Skeleton loader shown while lazy loading
     */
    public function placeholder(array $params = [])
    {
        return view('livewire.placeholders.chart', $params);
    }
}
