<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard;

use App\Enums\Period;
use App\Services\Metrics\ChunkedMetricsCalculator;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Dead simple chart component.
 * Livewire re-renders → Blade renders <x-chart> → Chart.js initializes. Done.
 */
final class ChannelDistributionChart extends Component
{
    public string $period = '7';

    public string $channel = 'all';

    public string $status = 'all';

    public ?string $customFrom = null;

    public ?string $customTo = null;

    public string $viewMode = 'detailed';

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
        $channelData = $this->getChannelData();

        $colors = [
            'rgba(59, 130, 246, 0.8)',
            'rgba(34, 197, 94, 0.8)',
            'rgba(168, 85, 247, 0.8)',
            'rgba(251, 146, 60, 0.8)',
            'rgba(236, 72, 153, 0.8)',
            'rgba(20, 184, 166, 0.8)',
        ];

        if (empty($channelData)) {
            return ['labels' => [], 'datasets' => []];
        }

        if ($this->viewMode === 'grouped') {
            $grouped = [];
            foreach ($channelData as $item) {
                $channel = $item['channel'] ?? 'Unknown';
                if (! isset($grouped[$channel])) {
                    $grouped[$channel] = 0;
                }
                $grouped[$channel] += (float) ($item['revenue'] ?? 0);
            }

            return [
                'labels' => array_keys($grouped),
                'datasets' => [
                    [
                        'data' => array_values($grouped),
                        'backgroundColor' => array_slice($colors, 0, count($grouped)),
                        'borderWidth' => 0,
                    ],
                ],
            ];
        }

        $labels = array_map(
            fn ($item) => $item['subsource'] ?? $item['channel'] ?? 'Unknown',
            $channelData
        );
        $data = array_map(
            fn ($item) => (float) ($item['revenue'] ?? 0),
            $channelData
        );

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'data' => $data,
                    'backgroundColor' => array_slice($colors, 0, count($labels)),
                    'borderWidth' => 0,
                ],
            ],
        ];
    }

    private function getChannelData(): array
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

            return $calculator->calculate()['top_channels']->toArray();
        }

        $cacheKey = $periodEnum->cacheKey($this->channel, $this->status);
        $cached = Cache::get($cacheKey);

        if ($cached && isset($cached['top_channels'])) {
            return is_array($cached['top_channels'])
                ? $cached['top_channels']
                : $cached['top_channels']->toArray();
        }

        return [];
    }

    public function render()
    {
        return view('livewire.dashboard.channel-distribution-chart');
    }
}
