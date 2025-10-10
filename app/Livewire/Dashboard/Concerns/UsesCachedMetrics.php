<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard\Concerns;

use App\Services\Dashboard\DashboardDataService;
use App\Services\Metrics\SalesMetrics;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;

/**
 * Trait for Livewire components to use pre-warmed cached metrics
 *
 * Components using this trait should have these public properties:
 * - string $period
 * - string $channel
 * - string $searchTerm
 * - ?string $customFrom
 * - ?string $customTo
 *
 * This trait provides:
 * - orders() - Returns orders collection (from cache or DB)
 * - salesMetrics() - Returns SalesMetrics instance
 * - cachedMetrics() - Returns pre-warmed cache array (if available)
 * - usingCache() - Returns bool indicating if cache is being used
 */
trait UsesCachedMetrics
{
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
    public function cachedMetrics(): ?array
    {
        $service = app(DashboardDataService::class);

        if (!$service->canUseCachedMetrics(
            $this->period,
            $this->channel,
            $this->searchTerm,
            $this->customFrom,
            $this->customTo
        )) {
            return null;
        }

        return $service->getCachedMetrics($this->period, $this->channel);
    }

    #[Computed]
    public function usingCache(): bool
    {
        return $this->cachedMetrics !== null;
    }
}
