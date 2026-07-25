<?php

declare(strict_types=1);

namespace App\Enums;

use Carbon\CarbonImmutable;

enum ComparisonMode: string
{
    case MonthOverMonth = 'mom';
    case YearOverYear = 'yoy';

    public function label(): string
    {
        return match ($this) {
            self::MonthOverMonth => 'Month over Month',
            self::YearOverYear => 'Year over Year',
        };
    }

    public function abbreviation(): string
    {
        return match ($this) {
            self::MonthOverMonth => 'MoM',
            self::YearOverYear => 'YoY',
        };
    }

    /**
     * Resolve the baseline month this mode compares the anchor month against.
     */
    public function baseline(CarbonImmutable $anchor): CarbonImmutable
    {
        return match ($this) {
            self::MonthOverMonth => $anchor->startOfMonth()->subMonthNoOverflow(),
            self::YearOverYear => $anchor->startOfMonth()->subYearNoOverflow(),
        };
    }

    /**
     * @return array<self>
     */
    public static function all(): array
    {
        return self::cases();
    }
}
