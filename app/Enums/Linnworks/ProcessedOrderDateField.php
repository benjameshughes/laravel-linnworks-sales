<?php

declare(strict_types=1);

namespace App\Enums\Linnworks;

/**
 * Date field options for Linnworks processed order searches
 *
 * The Linnworks API allows filtering processed orders by different date fields.
 * This enum provides type safety and clear documentation of available options.
 *
 * @see https://apidocs.linnworks.net/#/ProcessedOrders/SearchProcessedOrders
 */
enum ProcessedOrderDateField: string
{
    /**
     * Filter by the date the order was received from the channel
     * This is the default date field used for most queries
     */
    case RECEIVED = 'received';

    /**
     * Filter by the date the order was processed/fulfilled
     * Use this for historical imports to get already-processed orders
     */
    case PROCESSED = 'processed';

    /**
     * Filter by the date payment was received
     */
    case PAYMENT = 'payment';

    /**
     * Filter by the date the order was cancelled
     */
    case CANCELLED = 'cancelled';

    /**
     * Get human-readable label for the date field
     */
    public function label(): string
    {
        return match ($this) {
            self::RECEIVED => 'Received Date',
            self::PROCESSED => 'Processed Date',
            self::PAYMENT => 'Payment Date',
            self::CANCELLED => 'Cancelled Date',
        };
    }

    /**
     * Get description of what this date field represents
     */
    public function description(): string
    {
        return match ($this) {
            self::RECEIVED => 'Date the order was received from the sales channel',
            self::PROCESSED => 'Date the order was marked as processed/fulfilled',
            self::PAYMENT => 'Date the payment was received',
            self::CANCELLED => 'Date the order was cancelled',
        };
    }

    /**
     * Check if this is the default date field
     */
    public function isDefault(): bool
    {
        return $this === self::RECEIVED;
    }

    /**
     * Get the default date field for queries
     */
    public static function default(): self
    {
        return self::RECEIVED;
    }

    /**
     * Get the date field to use for historical imports
     */
    public static function forHistoricalImport(): self
    {
        return self::PROCESSED;
    }
}
