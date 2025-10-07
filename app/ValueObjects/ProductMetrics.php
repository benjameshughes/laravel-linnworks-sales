<?php

namespace App\ValueObjects;

use JsonSerializable;

readonly class ProductMetrics implements JsonSerializable
{
    public function __construct(
        public float $totalRevenue,
        public int $totalQuantity,
        public float $totalCost,
        public int $period,
        public int $orderCount = 0,
    ) {}

    public function profitMargin(): float
    {
        return $this->totalRevenue > 0 
            ? (($this->totalRevenue - $this->totalCost) / $this->totalRevenue) * 100 
            : 0.0;
    }

    public function avgDailySales(): float
    {
        return $this->totalQuantity / max($this->period, 1);
    }

    public function avgOrderValue(): float
    {
        return $this->orderCount > 0 
            ? $this->totalRevenue / $this->orderCount 
            : 0.0;
    }

    public function avgQuantityPerOrder(): float
    {
        return $this->orderCount > 0 
            ? $this->totalQuantity / $this->orderCount 
            : 0.0;
    }

    public function avgSellingPrice(): float
    {
        return $this->totalQuantity > 0 
            ? $this->totalRevenue / $this->totalQuantity 
            : 0.0;
    }

    public function avgUnitCost(): float
    {
        return $this->totalQuantity > 0 
            ? $this->totalCost / $this->totalQuantity 
            : 0.0;
    }

    public function hasActivity(): bool
    {
        return $this->totalQuantity > 0;
    }

    public function toArray(): array
    {
        return [
            'total_revenue' => $this->totalRevenue,
            'total_quantity' => $this->totalQuantity,
            'total_cost' => $this->totalCost,
            'period' => $this->period,
            'order_count' => $this->orderCount,
            'profit_margin' => $this->profitMargin(),
            'avg_daily_sales' => $this->avgDailySales(),
            'avg_order_value' => $this->avgOrderValue(),
            'avg_quantity_per_order' => $this->avgQuantityPerOrder(),
            'avg_selling_price' => $this->avgSellingPrice(),
            'avg_unit_cost' => $this->avgUnitCost(),
            'has_activity' => $this->hasActivity(),
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}