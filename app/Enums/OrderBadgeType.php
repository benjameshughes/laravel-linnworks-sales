<?php

namespace App\Enums;

enum OrderBadgeType: string
{
    case HIGH_VALUE = 'high-value';
    case BULK_ORDER = 'bulk-order';
    case REPEAT_CUSTOMER = 'repeat-customer';
    case FAST_PROCESSING = 'fast-processing';
    case HIGH_MARGIN = 'high-margin';
    case SAME_DAY = 'same-day';
    case MULTI_ITEM = 'multi-item';
    case LOW_MARGIN = 'low-margin';

    public function label(): string
    {
        return match ($this) {
            self::HIGH_VALUE => 'High Value',
            self::BULK_ORDER => 'Bulk Order',
            self::REPEAT_CUSTOMER => 'Repeat Customer',
            self::FAST_PROCESSING => 'Fast Processing',
            self::HIGH_MARGIN => 'High Margin',
            self::SAME_DAY => 'Same Day',
            self::MULTI_ITEM => 'Multi-Item',
            self::LOW_MARGIN => 'Low Margin',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::HIGH_VALUE => 'amber',
            self::BULK_ORDER => 'indigo',
            self::REPEAT_CUSTOMER => 'green',
            self::FAST_PROCESSING => 'blue',
            self::HIGH_MARGIN => 'purple',
            self::SAME_DAY => 'emerald',
            self::MULTI_ITEM => 'cyan',
            self::LOW_MARGIN => 'orange',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::HIGH_VALUE => 'currency-pound',
            self::BULK_ORDER => 'cube',
            self::REPEAT_CUSTOMER => 'arrow-path',
            self::FAST_PROCESSING => 'bolt',
            self::HIGH_MARGIN => 'star',
            self::SAME_DAY => 'clock',
            self::MULTI_ITEM => 'squares-2x2',
            self::LOW_MARGIN => 'exclamation-triangle',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::HIGH_VALUE => 'Order total exceeds £100',
            self::BULK_ORDER => 'Contains 10+ items',
            self::REPEAT_CUSTOMER => 'Customer has multiple orders',
            self::FAST_PROCESSING => 'Processed within 24 hours',
            self::HIGH_MARGIN => 'Profit margin exceeds 30%',
            self::SAME_DAY => 'Received and processed same day',
            self::MULTI_ITEM => 'Contains 3+ different products',
            self::LOW_MARGIN => 'Profit margin below 10%',
        };
    }

    public function priority(): int
    {
        return match ($this) {
            self::HIGH_VALUE => 1,
            self::HIGH_MARGIN => 2,
            self::REPEAT_CUSTOMER => 3,
            self::BULK_ORDER => 4,
            self::FAST_PROCESSING => 5,
            self::SAME_DAY => 6,
            self::MULTI_ITEM => 7,
            self::LOW_MARGIN => 8,
        };
    }
}
