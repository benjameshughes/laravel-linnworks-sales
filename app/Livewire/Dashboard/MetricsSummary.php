<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard;

use Livewire\Component;
use Livewire\Attributes\On;
use Livewire\Attributes\Computed;
use Illuminate\Support\Collection;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Cache;
use App\Services\Metrics\ChunkedMetricsCalculator;
use App\Services\Metrics\Sales\SalesMetrics as SalesMetricsService;

final class MetricsSummary extends Component
{
    private ?SalesMetricsService $salesMetrics = null;

    /**
     * Livewire cannot inject through the constructor, so dependencies are
     * resolved here - boot() runs on every request, mount and hydrate alike.
     */
    public function boot(SalesMetricsService $salesMetrics): void
    {
        $this->salesMetrics = $salesMetrics;
    }

    /**
     * Livewire skips boot() on the lazy-load request, so always reach the
     * dependency through here rather than the property directly.
     */
    private function salesMetrics(): SalesMetricsService
    {
        return $this->salesMetrics ??= app(SalesMetricsService::class);
    }

    public string $period = '7';

    public string $channel = 'all';

    public string $subsource = 'all';

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
        string $subsource = 'all',
        string $status = 'all',
        ?string $customFrom = null,
        ?string $customTo = null
    ): void {
        $this->period = $period;
        $this->channel = $channel;
        $this->subsource = $subsource;
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

        if ($this->shouldQueryLive($periodEnum)) {
            $calculator = new ChunkedMetricsCalculator(
                period: $this->period,
                channel: $this->channel,
                status: $this->status,
                customFrom: $this->customFrom,
                customTo: $this->customTo,
                subsource: $this->subsource,
            );

            $data = $calculator->calculate();

            return collect([
                'total_revenue' => $data['revenue'],
                'total_orders' => $data['orders'],
                'total_items' => $data['items'],
                'average_order_value' => $data['avg_order_value'],
            ]);
        }

        $cacheKey = $periodEnum->cacheKey($this->channel, $this->subsource, $this->status);
        $cached = Cache::get($cacheKey);

        if ($cached) {
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
        return $this->salesMetrics()->getDateRange(
            period: $this->period,
            customFrom: $this->customFrom,
            customTo: $this->customTo
        );
    }

    #[Computed]
    public function bestDay(): Collection|array|null
    {
        $periodEnum = \App\Enums\Period::tryFrom($this->period);

        if ($this->shouldQueryLive($periodEnum)) {
            $calculator = new ChunkedMetricsCalculator(
                period: $this->period,
                channel: $this->channel,
                status: $this->status,
                customFrom: $this->customFrom,
                customTo: $this->customTo,
                subsource: $this->subsource,
            );

            $data = $calculator->calculate();

            return $data['best_day'];
        }

        $cacheKey = $periodEnum->cacheKey($this->channel, $this->subsource, $this->status);
        $cached = Cache::get($cacheKey);

        if ($cached && isset($cached['best_day'])) {
            return $cached['best_day'];
        }

        // Cache miss - return empty array to prevent OOM
        return [];
    }

    private function shouldQueryLive(?\App\Enums\Period $periodEnum): bool
    {
        return $this->customFrom
            || $this->customTo
            || ! $periodEnum?->isCacheable();
    }

    public function render(): View
    {
        return view('livewire.dashboard.metrics-summary');
    }

    /**
     * Skeleton loader shown while lazy loading
     */
    public function placeholder(array $params = []): View
    {
        return view('livewire.placeholders.metrics-summary', $params);
    }
}
