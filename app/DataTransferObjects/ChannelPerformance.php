<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

use JsonSerializable;
use App\ValueObjects\GrowthIndicator;
use Illuminate\Contracts\Support\Arrayable;

/**
 * A single channel's performance across two comparable periods.
 *
 * Growth figures return null when the baseline period has no volume - a
 * percentage against zero is meaningless, so the UI renders "New" instead
 * of a fabricated infinity.
 */
final class ChannelPerformance implements Arrayable, JsonSerializable
{
    public function __construct(
        public readonly string $name,
        public readonly string $source,
        public readonly ?string $subsource,
        public readonly float $currentRevenue,
        public readonly float $baselineRevenue,
        public readonly int $currentOrders,
        public readonly int $baselineOrders,
        public readonly int $currentItems,
        public readonly int $baselineItems,
        public readonly float $revenueShare,
    ) {}

    public function revenueDelta(): float
    {
        return $this->currentRevenue - $this->baselineRevenue;
    }

    public function revenueGrowth(): ?float
    {
        return self::growth($this->currentRevenue, $this->baselineRevenue);
    }

    public function ordersDelta(): int
    {
        return $this->currentOrders - $this->baselineOrders;
    }

    public function ordersGrowth(): ?float
    {
        return self::growth((float) $this->currentOrders, (float) $this->baselineOrders);
    }

    public function itemsDelta(): int
    {
        return $this->currentItems - $this->baselineItems;
    }

    public function currentAov(): float
    {
        return $this->currentOrders > 0 ? $this->currentRevenue / $this->currentOrders : 0.0;
    }

    public function baselineAov(): float
    {
        return $this->baselineOrders > 0 ? $this->baselineRevenue / $this->baselineOrders : 0.0;
    }

    public function aovGrowth(): ?float
    {
        return self::growth($this->currentAov(), $this->baselineAov());
    }

    public function revenueIndicator(): GrowthIndicator
    {
        return GrowthIndicator::fromPercentage($this->revenueGrowth());
    }

    public function ordersIndicator(): GrowthIndicator
    {
        return GrowthIndicator::fromPercentage($this->ordersGrowth());
    }

    public function aovIndicator(): GrowthIndicator
    {
        return GrowthIndicator::fromPercentage($this->aovGrowth());
    }

    public function isNew(): bool
    {
        return $this->baselineOrders === 0 && $this->currentOrders > 0;
    }

    public function isLost(): bool
    {
        return $this->currentOrders === 0 && $this->baselineOrders > 0;
    }

    public function isImproving(): bool
    {
        return $this->revenueDelta() > 0;
    }

    /**
     * @return array{
     *     name: string,
     *     source: string,
     *     subsource: string|null,
     *     current_revenue: float,
     *     baseline_revenue: float,
     *     revenue_delta: float,
     *     revenue_growth: float|null,
     *     current_orders: int,
     *     baseline_orders: int,
     *     orders_delta: int,
     *     orders_growth: float|null,
     *     current_items: int,
     *     baseline_items: int,
     *     items_delta: int,
     *     current_aov: float,
     *     baseline_aov: float,
     *     aov_growth: float|null,
     *     revenue_share: float
     * }
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'source' => $this->source,
            'subsource' => $this->subsource,
            'current_revenue' => $this->currentRevenue,
            'baseline_revenue' => $this->baselineRevenue,
            'revenue_delta' => $this->revenueDelta(),
            'revenue_growth' => $this->revenueGrowth(),
            'current_orders' => $this->currentOrders,
            'baseline_orders' => $this->baselineOrders,
            'orders_delta' => $this->ordersDelta(),
            'orders_growth' => $this->ordersGrowth(),
            'current_items' => $this->currentItems,
            'baseline_items' => $this->baselineItems,
            'items_delta' => $this->itemsDelta(),
            'current_aov' => $this->currentAov(),
            'baseline_aov' => $this->baselineAov(),
            'aov_growth' => $this->aovGrowth(),
            'revenue_share' => $this->revenueShare,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    private static function growth(float $current, float $baseline): ?float
    {
        if ($baseline <= 0.0) {
            return null;
        }

        return (($current - $baseline) / $baseline) * 100;
    }
}
