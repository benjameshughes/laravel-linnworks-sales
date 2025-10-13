<?php

declare(strict_types=1);

namespace App\Enums;

enum Period: string
{
    case TODAY = '1';
    case YESTERDAY = 'yesterday';
    case SEVEN_DAYS = '7';
    case THIRTY_DAYS = '30';
    case NINETY_DAYS = '90';
    case ONE_EIGHTY_DAYS = '180';
    case THREE_SIXTY_FIVE_DAYS = '365';
    case SEVEN_THIRTY_DAYS = '730';
    case CUSTOM = 'custom';

    /**
     * Get the human-readable label for this period
     */
    public function label(): string
    {
        return match ($this) {
            self::TODAY => 'Last 24 hours',
            self::YESTERDAY => 'Yesterday',
            self::SEVEN_DAYS => 'Last 7 days',
            self::THIRTY_DAYS => 'Last 30 days',
            self::NINETY_DAYS => 'Last 90 days',
            self::ONE_EIGHTY_DAYS => 'Last 180 days',
            self::THREE_SIXTY_FIVE_DAYS => 'Last 365 days',
            self::SEVEN_THIRTY_DAYS => 'Last 730 days',
            self::CUSTOM => 'Custom Range...',
        };
    }

    /**
     * Check if this period is cacheable
     */
    public function isCacheable(): bool
    {
        return $this !== self::CUSTOM;
    }

    /**
     * Get all cacheable periods
     *
     * @return array<Period>
     */
    public static function cacheable(): array
    {
        return array_filter(
            self::cases(),
            fn (Period $period) => $period->isCacheable()
        );
    }

    /**
     * Get all periods for dropdown UI
     *
     * @return array<Period>
     */
    public static function all(): array
    {
        return self::cases();
    }

    /**
     * Get the cache key for this period
     */
    public function cacheKey(string $channel = 'all'): string
    {
        return "metrics_{$this->value}d_{$channel}";
    }

    /**
     * Try to create Period from string value
     */
    public static function tryFromString(?string $value): ?self
    {
        if ($value === null) {
            return null;
        }

        return self::tryFrom($value);
    }
}
