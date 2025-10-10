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

final class SalesTrendChart extends Component
{
    public string $period = '7';
    public string $channel = 'all';
    public string $searchTerm = '';
    public ?string $customFrom = null;
    public ?string $customTo = null;
    public string $viewMode = 'revenue'; // 'revenue' or 'orders'

    public function mount(): void
    {
        $this->period = request('period', '7');
        $this->channel = request('channel', 'all');
        $this->searchTerm = request('search', '');
    }

    #[On('filters-updated')]
    public function updateFilters(
        string $period,
        string $channel,
        string $searchTerm = '',
        ?string $customFrom = null,
        ?string $customTo = null
    ): void {
        $this->period = $period;
        $this->channel = $channel;
        $this->searchTerm = $searchTerm;
        $this->customFrom = $customFrom;
        $this->customTo = $customTo;
    }

    #[Computed]
    public function orders(): Collection
    {
        return app(DashboardDataService::class)->getOrders(
            period: $this->period,
            channel: $this->channel,
            searchTerm: $this->searchTerm,
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
        if ($service->canUseCachedMetrics($this->period, $this->channel, $this->searchTerm, $this->customFrom, $this->customTo)) {
            $cached = $service->getCachedMetrics($this->period, $this->channel);
            if ($cached) {
                if ($this->viewMode === 'orders' && isset($cached['chart_orders'])) {
                    return $cached['chart_orders'];
                }
                if ($this->viewMode === 'revenue' && isset($cached['chart_line'])) {
                    return $cached['chart_line'];
                }
            }
        }

        // Fallback to live calculation
        if ($this->viewMode === 'orders') {
            return $this->salesMetrics->getOrderCountChartData($this->period, $this->customFrom, $this->customTo);
        }

        return $this->salesMetrics->getLineChartData($this->period, $this->customFrom, $this->customTo);
    }

    #[Computed]
    public function periodLabel(): string
    {
        if ($this->period === 'custom') {
            return 'Custom: ' . Carbon::parse($this->customFrom)->format('M j') . ' - ' . Carbon::parse($this->customTo)->format('M j, Y');
        }

        return match ($this->period) {
            '1' => 'Last 24 hours',
            'yesterday' => 'Yesterday',
            '7' => 'Last 7 days',
            '30' => 'Last 30 days',
            '90' => 'Last 90 days',
            default => "Last {$this->period} days",
        };
    }

    #[Computed]
    public function chartKey(): string
    {
        return "sales-trend-{$this->viewMode}-{$this->period}-{$this->channel}-{$this->searchTerm}-{$this->customFrom}-{$this->customTo}";
    }

    public function setViewMode(string $mode): void
    {
        $this->viewMode = $mode;
    }

    public function render()
    {
        return view('livewire.dashboard.sales-trend-chart');
    }
}
