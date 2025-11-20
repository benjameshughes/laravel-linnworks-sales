<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard;

use App\Repositories\Metrics\Sales\SalesRepository;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

final class RecentOrders extends Component
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
    public function recentOrders(): Collection
    {
        $periodEnum = \App\Enums\Period::tryFrom($this->period);

        // Can't cache custom periods - use repository for limited query
        if ($this->customFrom || $this->customTo || ! $periodEnum?->isCacheable()) {
            return app(SalesRepository::class)->getRecentOrders(limit: 15);
        }

        // Check cache
        $cacheKey = $periodEnum->cacheKey($this->channel, $this->status);
        $cached = Cache::get($cacheKey);

        if ($cached && isset($cached['recent_orders'])) {
            return $cached['recent_orders'];
        }

        // Cache miss - return empty collection to prevent OOM
        return collect();
    }

    #[Computed]
    public function totalOrders(): int
    {
        $periodEnum = \App\Enums\Period::tryFrom($this->period);

        // Can't cache custom periods
        if ($this->customFrom || $this->customTo || ! $periodEnum?->isCacheable()) {
            return 0;
        }

        // Check cache
        $cacheKey = $periodEnum->cacheKey($this->channel, $this->status);
        $cached = Cache::get($cacheKey);

        if ($cached && isset($cached['orders'])) {
            return (int) $cached['orders'];
        }

        // Cache miss - return zero to prevent OOM
        return 0;
    }

    public function render()
    {
        return view('livewire.dashboard.recent-orders');
    }

    /**
     * Skeleton loader shown while lazy loading
     */
    public function placeholder(array $params = [])
    {
        return view('livewire.placeholders.table', $params);
    }
}
