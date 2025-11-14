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

final class MetricsSummary extends Component
{
    public string $period = '7';

    public string $channel = 'all';

    public string $status = 'all';

    public ?string $customFrom = null;

    public ?string $customTo = null;

    public function mount(): void
    {
        // Initialize from query params if available
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
        // Use shared singleton service - loads data ONCE per request
        // All islands share same orders collection = massive memory savings
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
    public function metrics(): Collection
    {
        // Try to use pre-warmed cache first (instant response)
        $service = app(DashboardDataService::class);
        if ($service->canUseCachedMetrics($this->period, $this->channel, $this->status, $this->customFrom, $this->customTo)) {
            $cached = $service->getCachedMetrics($this->period, $this->channel, $this->status);
            if ($cached) {
                if ($this->period === 'custom') {
                    $periodDays = Carbon::parse($this->customFrom)->diffInDays(Carbon::parse($this->customTo)) + 1;
                } elseif ($this->period === '0' || $this->period === '1') {
                    // Today (0) and Yesterday (1) are both single-day periods
                    $periodDays = 1;
                } else {
                    $periodDays = (int) $this->period;
                }

                // Build metrics from cache (without growth rate, since we need previous period for that)
                return collect([
                    'total_revenue' => $cached['revenue'],
                    'total_orders' => $cached['orders'],
                    'average_order_value' => $cached['avg_order_value'],
                    'total_items' => $cached['items'],
                    'orders_per_day' => $periodDays > 0 ? $cached['orders'] / $periodDays : 0,
                    // Growth rate omitted - would need previous period cache too
                ]);
            }
        }

        // Fallback to live calculation with growth rate
        if ($this->period === 'custom') {
            $periodDays = Carbon::parse($this->customFrom)->diffInDays(Carbon::parse($this->customTo)) + 1;
        } elseif ($this->period === '0' || $this->period === '1') {
            // Today (0) and Yesterday (1) are both single-day periods
            $periodDays = 1;
        } else {
            $periodDays = max(1, (int) $this->period);
        }

        $previousPeriodData = $this->getPreviousPeriodOrders();

        return $this->salesMetrics->getMetricsSummary($periodDays, $previousPeriodData);
    }

    #[Computed]
    public function dateRange(): Collection
    {
        return app(DashboardDataService::class)->getDateRange(
            period: $this->period,
            customFrom: $this->customFrom,
            customTo: $this->customTo
        );
    }

    #[Computed]
    public function bestDay(): Collection|array|null
    {
        // Try cache first
        $service = app(DashboardDataService::class);
        if ($service->canUseCachedMetrics($this->period, $this->channel, $this->status, $this->customFrom, $this->customTo)) {
            $cached = $service->getCachedMetrics($this->period, $this->channel, $this->status);
            if ($cached && isset($cached['best_day'])) {
                return $cached['best_day']; // This will be a Collection from cache
            }
        }

        // Fallback to live calculation (returns Collection)
        $startDate = $this->dateRange->get('start')?->format('Y-m-d');
        $endDate = $this->dateRange->get('end')?->format('Y-m-d');

        return $this->salesMetrics->bestPerformingDay($startDate, $endDate);
    }

    public function render()
    {
        return view('livewire.dashboard.metrics-summary');
    }

    /**
     * Skeleton loader shown while lazy loading
     */
    public function placeholder(array $params = [])
    {
        return view('livewire.placeholders.metrics-summary', $params);
    }

    private function getPreviousPeriodOrders(): Collection
    {
        // Also uses shared service for previous period data
        return app(DashboardDataService::class)->getPreviousPeriodOrders(
            period: $this->period,
            channel: $this->channel,
            customFrom: $this->customFrom,
            customTo: $this->customTo
        );
    }
}
