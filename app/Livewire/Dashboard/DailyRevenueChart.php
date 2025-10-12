<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard;

use App\Services\Dashboard\DashboardDataService;
use App\Services\Metrics\SalesMetrics;
use Carbon\Carbon;
use Illuminate\Support\Collection;
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
    public function orders(): Collection
    {
        return app(DashboardDataService::class)->getOrders(
            period: $this->period,
            channel: $this->channel,
            status: $this->status,
            customFrom: $this->customFrom,
            customTo: $this->customTo
        );
    }

    #[Computed]
    public function salesMetrics(): SalesMetrics
    {
        return new SalesMetrics($this->orders);
    }

    #[Computed]
    public function chartData(): array
    {
        // Try to use pre-warmed cache first (instant response)
        $service = app(DashboardDataService::class);
        if ($service->canUseCachedMetrics($this->period, $this->channel, $this->status, $this->customFrom, $this->customTo)) {
            $cached = $service->getCachedMetrics($this->period, $this->channel);
            if ($cached) {
                if ($this->viewMode === 'items' && isset($cached['chart_items'])) {
                    return $cached['chart_items'];
                }
                if ($this->viewMode === 'orders_revenue' && isset($cached['chart_orders_revenue'])) {
                    return $cached['chart_orders_revenue'];
                }
            }
        }

        // Fallback to live calculation
        if ($this->viewMode === 'items') {
            return $this->salesMetrics->getItemsSoldChartData($this->period, $this->customFrom, $this->customTo);
        }

        return $this->salesMetrics->getOrdersVsRevenueChartData($this->period, $this->customFrom, $this->customTo);
    }

    #[Computed]
    public function periodLabel(): string
    {
        if ($this->period === 'custom') {
            return 'Custom: ' . Carbon::parse($this->customFrom)->format('M j') . ' - ' . Carbon::parse($this->customTo)->format('M j, Y');
        }

        $periodEnum = \App\Enums\Period::tryFrom($this->period);
        return $periodEnum?->label() ?? "Last {$this->period} days";
    }

    #[Computed]
    public function chartKey(): string
    {
        return "daily-bar-{$this->viewMode}-{$this->period}-{$this->channel}-{$this->status}-{$this->customFrom}-{$this->customTo}";
    }

    public function setViewMode(string $mode): void
    {
        $this->viewMode = $mode;
    }

    public function render()
    {
        return view('livewire.dashboard.daily-revenue-chart');
    }
}
