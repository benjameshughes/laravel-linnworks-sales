<?php

namespace App\Enums;

enum ProductBadgeType: string
{
    case HOT_SELLER = 'hot-seller';
    case GROWING = 'growing';
    case DECLINING = 'declining';
    case TOP_MARGIN = 'top-margin';
    case NEW_PRODUCT = 'new-product';
    case HIGH_VOLUME = 'high-volume';
    case CONSISTENT = 'consistent';
    case NO_SALES = 'no-sales';

    public function label(): string
    {
        return match ($this) {
            self::HOT_SELLER => 'Hot Seller',
            self::GROWING => 'Growing',
            self::DECLINING => 'Declining',
            self::TOP_MARGIN => 'Top Margin',
            self::NEW_PRODUCT => 'New Product',
            self::HIGH_VOLUME => 'High Volume',
            self::CONSISTENT => 'Consistent',
            self::NO_SALES => 'No Sales',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::HOT_SELLER => 'red',
            self::GROWING => 'green',
            self::DECLINING => 'orange',
            self::TOP_MARGIN => 'purple',
            self::NEW_PRODUCT => 'blue',
            self::HIGH_VOLUME => 'indigo',
            self::CONSISTENT => 'emerald',
            self::NO_SALES => 'zinc',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::HOT_SELLER => 'fire',
            self::GROWING => 'trending-up',
            self::DECLINING => 'trending-down',
            self::TOP_MARGIN => 'star',
            self::NEW_PRODUCT => 'sparkles',
            self::HIGH_VOLUME => 'cube',
            self::CONSISTENT => 'check-circle',
            self::NO_SALES => 'clock',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::HOT_SELLER => 'High sales velocity (2+ units per day)',
            self::GROWING => 'Positive growth trend (>20% increase)',
            self::DECLINING => 'Negative growth trend (>20% decrease)',
            self::TOP_MARGIN => 'High profit margin (>30%)',
            self::NEW_PRODUCT => 'Added in the last 30 days',
            self::HIGH_VOLUME => 'Top 20% by quantity sold',
            self::CONSISTENT => 'Regular sales (75%+ of weeks)',
            self::NO_SALES => 'No sales in the selected period',
        };
    }

    public function priority(): int
    {
        return match ($this) {
            self::HOT_SELLER => 1,
            self::GROWING => 2,
            self::TOP_MARGIN => 3,
            self::HIGH_VOLUME => 4,
            self::CONSISTENT => 5,
            self::NEW_PRODUCT => 6,
            self::DECLINING => 7,
            self::NO_SALES => 8,
        };
    }
}
