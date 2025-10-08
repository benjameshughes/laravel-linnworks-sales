<?php

declare(strict_types=1);

namespace App\Services\Analytics;

readonly class ComparisonResult
{
    public function __construct(
        public float $currentRevenue,
        public float $previousRevenue,
        public int $currentOrders,
        public int $previousOrders,
        public float $currentAvgOrderValue,
        public float $previousAvgOrderValue,
    ) {}

    public function revenueChange(): float
    {
        if ($this->previousRevenue === 0.0) {
            return $this->currentRevenue > 0 ? 100.0 : 0.0;
        }

        return (($this->currentRevenue - $this->previousRevenue) / $this->previousRevenue) * 100;
    }

    public function ordersChange(): float
    {
        if ($this->previousOrders === 0) {
            return $this->currentOrders > 0 ? 100.0 : 0.0;
        }

        return (($this->currentOrders - $this->previousOrders) / $this->previousOrders) * 100;
    }

    public function avgOrderValueChange(): float
    {
        if ($this->previousAvgOrderValue === 0.0) {
            return $this->currentAvgOrderValue > 0 ? 100.0 : 0.0;
        }

        return (($this->currentAvgOrderValue - $this->previousAvgOrderValue) / $this->previousAvgOrderValue) * 100;
    }

    public function isRevenueUp(): bool
    {
        return $this->revenueChange() > 0;
    }

    public function isOrdersUp(): bool
    {
        return $this->ordersChange() > 0;
    }

    public function isAvgOrderValueUp(): bool
    {
        return $this->avgOrderValueChange() > 0;
    }

    public function toArray(): array
    {
        return [
            'current' => [
                'revenue' => $this->currentRevenue,
                'orders' => $this->currentOrders,
                'avg_order_value' => $this->currentAvgOrderValue,
            ],
            'previous' => [
                'revenue' => $this->previousRevenue,
                'orders' => $this->previousOrders,
                'avg_order_value' => $this->previousAvgOrderValue,
            ],
            'changes' => [
                'revenue' => [
                    'percentage' => $this->revenueChange(),
                    'is_up' => $this->isRevenueUp(),
                    'absolute' => $this->currentRevenue - $this->previousRevenue,
                ],
                'orders' => [
                    'percentage' => $this->ordersChange(),
                    'is_up' => $this->isOrdersUp(),
                    'absolute' => $this->currentOrders - $this->previousOrders,
                ],
                'avg_order_value' => [
                    'percentage' => $this->avgOrderValueChange(),
                    'is_up' => $this->isAvgOrderValueUp(),
                    'absolute' => $this->currentAvgOrderValue - $this->previousAvgOrderValue,
                ],
            ],
        ];
    }
}
