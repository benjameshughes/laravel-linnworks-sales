<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard;

use App\Services\Metrics\Sales\SalesMetrics as SalesMetricsService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
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

    /**
     * Handle cache warming completion - refresh dashboard data
     *
     * This is the only event that matters for the dashboard.
     * When cache is warmed, fresh data is available.
     */
    #[On('echo:cache-management,CacheWarmingCompleted')]
    public function handleCacheWarmingCompleted(array $data): void
    {
        // Clear cached computed properties to force fresh data
        unset($this->metrics);
        unset($this->dateRange);
        unset($this->bestDay);
    }

    #[Computed]
    public function metrics(): Collection
    {
        $periodEnum = \App\Enums\Period::tryFrom($this->period);

        // Can't cache custom periods
        if ($this->customFrom || $this->customTo || ! $periodEnum?->isCacheable()) {
            return app(SalesMetricsService::class)->getMetricsSummary(
                period: $this->period,
                channel: $this->channel,
                customFrom: $this->customFrom,
                customTo: $this->customTo
            );
        }

        // Check cache
        $cacheKey = $periodEnum->cacheKey($this->channel, $this->status);
        $cached = Cache::get($cacheKey);

        if ($cached) {
            // Extract needed data from cache array
            return collect([
                'total_revenue' => $cached['revenue'],
                'total_orders' => $cached['orders'],
                'total_items' => $cached['items'],
                'average_order_value' => $cached['avg_order_value'],
            ]);
        }

        // Cache miss - return empty collection to prevent OOM
        return collect([
            'total_revenue' => 0,
            'total_orders' => 0,
            'total_items' => 0,
            'average_order_value' => 0,
        ]);
    }

    #[Computed]
    public function dateRange(): Collection
    {
        return app(SalesMetricsService::class)->getDateRange(
            period: $this->period,
            customFrom: $this->customFrom,
            customTo: $this->customTo
        );
    }

    #[Computed]
    public function bestDay(): Collection|array|null
    {
        $periodEnum = \App\Enums\Period::tryFrom($this->period);

        // Can't cache custom periods
        if ($this->customFrom || $this->customTo || ! $periodEnum?->isCacheable()) {
            return app(SalesMetricsService::class)->getBestPerformingDay(
                period: $this->period,
                channel: $this->channel,
                customFrom: $this->customFrom,
                customTo: $this->customTo
            );
        }

        // Check cache
        $cacheKey = $periodEnum->cacheKey($this->channel, $this->status);
        $cached = Cache::get($cacheKey);

        if ($cached && isset($cached['best_day'])) {
            return $cached['best_day'];
        }

        // Cache miss - return empty array to prevent OOM
        return [];
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
}
