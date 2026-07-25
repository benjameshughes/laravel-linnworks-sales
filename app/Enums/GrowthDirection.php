<?php

declare(strict_types=1);

namespace App\Enums;

enum GrowthDirection: string
{
    case Up = 'up';
    case Down = 'down';
    case Flat = 'flat';
    case Unmeasurable = 'unmeasurable';

    public function icon(): string
    {
        return match ($this) {
            self::Up => 'arrow-trending-up',
            self::Down => 'arrow-trending-down',
            self::Flat => 'minus-small',
            self::Unmeasurable => 'sparkles',
        };
    }

    public function toneClass(): string
    {
        return match ($this) {
            self::Up => 'text-green-600 dark:text-green-400',
            self::Down => 'text-red-600 dark:text-red-400',
            self::Flat => 'text-zinc-500 dark:text-zinc-400',
            self::Unmeasurable => 'text-blue-600 dark:text-blue-400',
        };
    }

    public function isMeasurable(): bool
    {
        return $this !== self::Unmeasurable;
    }
}
