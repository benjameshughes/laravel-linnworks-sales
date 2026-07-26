<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard;

use Livewire\Component;
use Livewire\Attributes\On;
use Livewire\Attributes\Computed;
use Illuminate\Support\Collection;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Cache;
use App\Repositories\Metrics\Sales\SalesRepository;

final class RecentOrders extends Component
{
    private const RECENT_ORDER_LIMIT = 15;

    private ?SalesRepository $salesRepository = null;

    /**
     * Livewire cannot inject through the constructor, so dependencies arrive
     * here instead.
     */
    public function boot(SalesRepository $salesRepository): void
    {
        $this->salesRepository = $salesRepository;
    }

    /**
     * Livewire skips boot() on the lazy-load request, so always reach the
     * dependency through here rather than the property directly.
     */
    private function salesRepository(): SalesRepository
    {
        return $this->salesRepository ??= app(SalesRepository::class);
    }

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
            return $this->salesRepository()->getRecentOrders(limit: self::RECENT_ORDER_LIMIT);
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

    public function render(): View
    {
        return view('livewire.dashboard.recent-orders');
    }

    /**
     * Skeleton loader shown while lazy loading
     */
    public function placeholder(array $params = []): View
    {
        return view('livewire.placeholders.table', $params);
    }
}
