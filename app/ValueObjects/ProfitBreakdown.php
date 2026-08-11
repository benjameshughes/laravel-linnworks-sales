<?php

declare(strict_types=1);

namespace App\ValueObjects;

use JsonSerializable;

final readonly class ProfitBreakdown implements JsonSerializable
{
    public function __construct(
        public float $revenue,
        public float $cogs,
        public float $shippingCost,
        public float $channelFee,
        public float $totalCost,
        public float $profit,
        public float $marginPercent,
    ) {}

    public static function fromValues(
        float $revenue,
        float $cogs,
        float $shippingCost,
        float $channelFee,
    ): self {
        $totalCost = $cogs + $shippingCost + $channelFee;
        $profit = $revenue - $totalCost;
        $marginPercent = $revenue > 0 ? ($profit / $revenue) * 100 : 0;

        return new self(
            revenue: $revenue,
            cogs: $cogs,
            shippingCost: $shippingCost,
            channelFee: $channelFee,
            totalCost: $totalCost,
            profit: $profit,
            marginPercent: round($marginPercent, 2),
        );
    }

    public function jsonSerialize(): array
    {
        return [
            'revenue' => $this->revenue,
            'cogs' => $this->cogs,
            'shipping_cost' => $this->shippingCost,
            'channel_fee' => $this->channelFee,
            'total_cost' => $this->totalCost,
            'profit' => $this->profit,
            'margin_percent' => $this->marginPercent,
        ];
    }
}
