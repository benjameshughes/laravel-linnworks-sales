<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard;

use App\Services\Metrics\Sales\SalesMetrics as SalesMetricsService;
use Livewire\Attributes\Computed;
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
        $data = app(SalesMetricsService::class)->getChannelDistributionData(
            period: $this->period,
            channel: $this->channel,
            customFrom: $this->customFrom,
            customTo: $this->customTo
        );

        // If grouped view requested, transform detailed data (memory efficient)
        if ($this->viewMode === 'grouped') {
            return $this->transformToGroupedView($data);
        }

        return $data;
    }

    /**
     * Transform detailed view (with subsources) into grouped view (channel-only)
     *
     * Aggregates subsources into their parent channels
     * Example: "FBA (AMAZON)" + "FBM (AMAZON)" → "AMAZON"
     */
    private function transformToGroupedView(array $detailedData): array
    {
        if (empty($detailedData['labels']) || empty($detailedData['datasets'][0]['data'])) {
            return $detailedData;
        }

        $labels = $detailedData['labels'];
        $data = $detailedData['datasets'][0]['data'];
        $colors = $detailedData['datasets'][0]['backgroundColor'] ?? [];

        // Group by extracting channel from "Subsource (CHANNEL)" format
        $grouped = [];
        foreach ($labels as $index => $label) {
            // Extract channel from parentheses, or use full label if no parentheses
            if (preg_match('/\(([^)]+)\)$/', $label, $matches)) {
                $channel = $matches[1]; // Extract "AMAZON" from "FBA (AMAZON)"
            } else {
                $channel = $label; // No parentheses, use as-is
            }

            if (! isset($grouped[$channel])) {
                $grouped[$channel] = [
                    'value' => 0,
                    'color' => $colors[$index] ?? '#3B82F6',
                ];
            }

            $grouped[$channel]['value'] += $data[$index];
        }

        // Rebuild chart data structure
        return [
            'labels' => array_keys($grouped),
            'datasets' => [[
                'label' => 'Revenue by Channel',
                'data' => array_column($grouped, 'value'),
                'backgroundColor' => array_column($grouped, 'color'),
                'borderWidth' => 2,
            ]],
        ];
    }

    #[Computed]
    public function chartKey(): string
    {
        // Include viewMode so changing tabs recreates the component
        return "channel-doughnut-{$this->viewMode}-{$this->period}-{$this->channel}-{$this->status}-{$this->customFrom}-{$this->customTo}";
    }

    public function setViewMode(string $mode): void
    {
        $this->viewMode = $mode;
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
