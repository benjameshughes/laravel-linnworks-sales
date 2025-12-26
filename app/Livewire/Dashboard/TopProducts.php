<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard;

use App\Services\Metrics\ChunkedMetricsCalculator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

final class TopProducts extends Component
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

    #[Computed]
    public function topProducts(): Collection
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

            return $data['top_products'];
        }

        // Check cache for standard periods
        $cacheKey = $periodEnum->cacheKey($this->channel, $this->status);
        $cached = Cache::get($cacheKey);

        if ($cached && isset($cached['top_products'])) {
            return $cached['top_products'];
        }

        // Cache miss - return empty collection to prevent OOM
        return collect();
    }

    public function render()
    {
        return view('livewire.dashboard.top-products');
    }

    /**
     * Skeleton loader shown while lazy loading
     */
    public function placeholder(array $params = [])
    {
        return view('livewire.placeholders.top-list', $params);
    }
}
