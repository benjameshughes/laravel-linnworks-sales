<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard;

use App\Services\Dashboard\DashboardDataService;
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

    #[Computed]
    public function chartData(): array
    {
        // CACHE-ONLY MODE: No fallback to prevent OOM on large periods
        $service = app(DashboardDataService::class);
        if ($service->canUseCachedMetrics($this->period, $this->channel, $this->status, $this->customFrom, $this->customTo)) {
            $cached = $service->getCachedMetrics($this->period, $this->channel);
            // Note: Cache only has 'detailed' view (chart_doughnut)
            if ($cached && isset($cached['chart_doughnut'])) {
                return $cached['chart_doughnut'];
            }
        }

        // Return empty chart if cache unavailable (prevents OOM on large datasets)
        return [
            'labels' => [],
            'datasets' => [],
        ];
    }

    #[Computed]
    public function chartKey(): string
    {
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
}
