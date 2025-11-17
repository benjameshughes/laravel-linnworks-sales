<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard\Concerns;

use App\Factories\Metrics\Sales\SalesFactory;
use App\Repositories\Metrics\Sales\SalesRepository;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Computed;

/**
 * Trait for Livewire components to use pre-warmed cached metrics
 *
 * Components using this trait should have these public properties:
 * - string $period
 * - string $channel
 * - string $status (default: 'all')
 * - ?string $customFrom
 * - ?string $customTo
 *
 * This trait provides:
 * - orders() - Returns orders collection (from cache or DB)
 * - salesFactory() - Returns SalesFactory instance
 * - cachedMetrics() - Returns pre-warmed cache array (if available)
 * - usingCache() - Returns bool indicating if cache is being used
 */
trait UsesCachedMetrics
{
    #[Computed]
    public function orders(): Collection
    {
        $repository = app(SalesRepository::class);

        return $repository->getOrdersForPeriodWithFilters(
            period: $this->period,
            channel: $this->channel,
            status: $this->status,
            customFrom: $this->customFrom,
            customTo: $this->customTo
        );
    }

    #[Computed]
    public function salesFactory(): SalesFactory
    {
        return new SalesFactory($this->orders);
    }

    #[Computed]
    public function cachedMetrics(): ?array
    {
        // Cannot use cache if custom date range
        if ($this->customFrom || $this->customTo) {
            return null;
        }

        // Cannot use cache for non-standard periods
        if (! in_array($this->period, ['0', '1', '7', '30', '90', '180', '365', '730'])) {
            return null;
        }

        // Try to get from cache
        $periodEnum = \App\Enums\Period::tryFrom($this->period);
        $cacheKey = $periodEnum?->cacheKey($this->channel, $this->status) ?? "metrics_{$this->period}d_{$this->channel}_{$this->status}";

        return Cache::get($cacheKey);
    }

    #[Computed]
    public function usingCache(): bool
    {
        return $this->cachedMetrics !== null;
    }
}
