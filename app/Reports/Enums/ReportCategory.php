<?php

namespace App\Reports\Enums;

enum ReportCategory: string
{
    case Sales = 'sales';
    case Products = 'products';
    case Channels = 'channels';
    case Inventory = 'inventory';
    case Financial = 'financial';

    public function label(): string
    {
        return match ($this) {
            self::Sales => 'Sales Reports',
            self::Products => 'Product Reports',
            self::Channels => 'Channel Reports',
            self::Inventory => 'Inventory Reports',
            self::Financial => 'Financial Reports',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Sales => 'chart-bar',
            self::Products => 'cube',
            self::Channels => 'globe-alt',
            self::Inventory => 'archive-box',
            self::Financial => 'banknotes',
        };
    }
}
