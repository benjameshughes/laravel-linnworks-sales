<?php

declare(strict_types=1);

namespace App\Enums\Linnworks;

/**
 * Linnworks payment status codes
 *
 * These are the integer codes returned by Linnworks API for order payment status.
 * This is separate from whether an order is processed or cancelled (which are boolean flags).
 *
 * @see https://apidocs.linnworks.net/#/Orders
 */
enum LinnworksPaymentStatus: int
{
    /**
     * Payment not received
     */
    case UNPAID = 0;

    /**
     * Payment received
     */
    case PAID = 1;

    /**
     * Order cancelled
     */
    case CANCELLED = 2;

    /**
     * Get human-readable label
     */
    public function label(): string
    {
        return match ($this) {
            self::UNPAID => 'Unpaid',
            self::PAID => 'Paid',
            self::CANCELLED => 'Cancelled',
        };
    }

    /**
     * Get badge color for UI display
     */
    public function badgeColor(): string
    {
        return match ($this) {
            self::PAID => 'green',
            self::UNPAID => 'yellow',
            self::CANCELLED => 'red',
        };
    }

    /**
     * Check if payment has been received
     */
    public function isPaid(): bool
    {
        return $this === self::PAID;
    }

    /**
     * Check if order is cancelled
     */
    public function isCancelled(): bool
    {
        return $this === self::CANCELLED;
    }
}
