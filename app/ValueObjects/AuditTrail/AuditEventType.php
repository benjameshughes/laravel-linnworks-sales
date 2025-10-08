<?php

declare(strict_types=1);

namespace App\ValueObjects\AuditTrail;

/**
 * Audit event type enum with severity classification.
 *
 * Uses PHP 8.1+ backed enums with rich behavior.
 */
enum AuditEventType: string
{
    // Inventory events
    case STOCK_LEVEL_CHANGED = 'StockLevelChanged';
    case ITEM_CREATED = 'ItemCreated';
    case ITEM_UPDATED = 'ItemUpdated';
    case ITEM_DELETED = 'ItemDeleted';
    case PRICE_CHANGED = 'PriceChanged';
    case BATCH_CREATED = 'BatchCreated';
    case BATCH_UPDATED = 'BatchUpdated';

    // Order events
    case ORDER_CREATED = 'OrderCreated';
    case ORDER_UPDATED = 'OrderUpdated';
    case ORDER_PROCESSED = 'OrderProcessed';
    case ORDER_CANCELLED = 'OrderCancelled';
    case ORDER_REFUNDED = 'OrderRefunded';
    case ORDER_SHIPPED = 'OrderShipped';
    case ORDER_NOTE_ADDED = 'OrderNoteAdded';

    // System events
    case SYNC_COMPLETED = 'SyncCompleted';
    case SYNC_FAILED = 'SyncFailed';
    case VALIDATION_ERROR = 'ValidationError';
    case API_ERROR = 'APIError';

    // Unknown/fallback
    case UNKNOWN = 'Unknown';
    case CUSTOM = 'Custom';

    /**
     * Create from string (case-insensitive, fuzzy matching).
     */
    public static function fromString(string $value): self
    {
        // Try exact match first
        foreach (self::cases() as $case) {
            if (strcasecmp($case->value, $value) === 0) {
                return $case;
            }
        }

        // Try fuzzy matching
        $normalized = strtolower(str_replace([' ', '_', '-'], '', $value));

        return match (true) {
            str_contains($normalized, 'stock') && str_contains($normalized, 'level') => self::STOCK_LEVEL_CHANGED,
            str_contains($normalized, 'create') && str_contains($normalized, 'item') => self::ITEM_CREATED,
            str_contains($normalized, 'update') && str_contains($normalized, 'item') => self::ITEM_UPDATED,
            str_contains($normalized, 'delete') && str_contains($normalized, 'item') => self::ITEM_DELETED,
            str_contains($normalized, 'price') => self::PRICE_CHANGED,
            str_contains($normalized, 'batch') && str_contains($normalized, 'create') => self::BATCH_CREATED,
            str_contains($normalized, 'batch') && str_contains($normalized, 'update') => self::BATCH_UPDATED,
            str_contains($normalized, 'order') && str_contains($normalized, 'create') => self::ORDER_CREATED,
            str_contains($normalized, 'order') && str_contains($normalized, 'update') => self::ORDER_UPDATED,
            str_contains($normalized, 'process') => self::ORDER_PROCESSED,
            str_contains($normalized, 'cancel') => self::ORDER_CANCELLED,
            str_contains($normalized, 'refund') => self::ORDER_REFUNDED,
            str_contains($normalized, 'ship') => self::ORDER_SHIPPED,
            str_contains($normalized, 'note') => self::ORDER_NOTE_ADDED,
            str_contains($normalized, 'sync') && str_contains($normalized, 'complet') => self::SYNC_COMPLETED,
            str_contains($normalized, 'sync') && str_contains($normalized, 'fail') => self::SYNC_FAILED,
            str_contains($normalized, 'validation') => self::VALIDATION_ERROR,
            str_contains($normalized, 'api') && str_contains($normalized, 'error') => self::API_ERROR,
            default => self::UNKNOWN,
        };
    }

    /**
     * Get severity level (critical, warning, info).
     */
    public function severity(): string
    {
        return match ($this) {
            self::ITEM_DELETED,
            self::ORDER_CANCELLED,
            self::ORDER_REFUNDED,
            self::SYNC_FAILED,
            self::VALIDATION_ERROR,
            self::API_ERROR => 'critical',

            self::STOCK_LEVEL_CHANGED,
            self::PRICE_CHANGED,
            self::ORDER_UPDATED,
            self::BATCH_UPDATED,
            self::ITEM_UPDATED => 'warning',

            default => 'info',
        };
    }

    /**
     * Check if event is critical.
     */
    public function isCritical(): bool
    {
        return $this->severity() === 'critical';
    }

    /**
     * Check if event is a warning.
     */
    public function isWarning(): bool
    {
        return $this->severity() === 'warning';
    }

    /**
     * Check if event is informational.
     */
    public function isInformational(): bool
    {
        return $this->severity() === 'info';
    }

    /**
     * Get icon for UI display.
     */
    public function icon(): string
    {
        return match ($this) {
            self::ITEM_CREATED, self::ORDER_CREATED, self::BATCH_CREATED => '➕',
            self::ITEM_UPDATED, self::ORDER_UPDATED, self::BATCH_UPDATED => '✏️',
            self::ITEM_DELETED => '🗑️',
            self::STOCK_LEVEL_CHANGED => '📦',
            self::PRICE_CHANGED => '💰',
            self::ORDER_PROCESSED => '✅',
            self::ORDER_CANCELLED => '❌',
            self::ORDER_REFUNDED => '💸',
            self::ORDER_SHIPPED => '🚚',
            self::ORDER_NOTE_ADDED => '📝',
            self::SYNC_COMPLETED => '🔄',
            self::SYNC_FAILED, self::API_ERROR => '⚠️',
            self::VALIDATION_ERROR => '🚫',
            default => 'ℹ️',
        };
    }

    /**
     * Get color for UI display.
     */
    public function color(): string
    {
        return match ($this->severity()) {
            'critical' => 'red',
            'warning' => 'yellow',
            default => 'blue',
        };
    }

    /**
     * Get human-readable description.
     */
    public function description(): string
    {
        return match ($this) {
            self::STOCK_LEVEL_CHANGED => 'Stock level was changed',
            self::ITEM_CREATED => 'Inventory item was created',
            self::ITEM_UPDATED => 'Inventory item was updated',
            self::ITEM_DELETED => 'Inventory item was deleted',
            self::PRICE_CHANGED => 'Price was changed',
            self::BATCH_CREATED => 'Batch was created',
            self::BATCH_UPDATED => 'Batch was updated',
            self::ORDER_CREATED => 'Order was created',
            self::ORDER_UPDATED => 'Order was updated',
            self::ORDER_PROCESSED => 'Order was processed',
            self::ORDER_CANCELLED => 'Order was cancelled',
            self::ORDER_REFUNDED => 'Order was refunded',
            self::ORDER_SHIPPED => 'Order was shipped',
            self::ORDER_NOTE_ADDED => 'Note was added to order',
            self::SYNC_COMPLETED => 'Data sync completed successfully',
            self::SYNC_FAILED => 'Data sync failed',
            self::VALIDATION_ERROR => 'Validation error occurred',
            self::API_ERROR => 'API error occurred',
            self::UNKNOWN => 'Unknown event type',
            self::CUSTOM => 'Custom event',
        };
    }

    /**
     * Check if event type relates to orders.
     */
    public function isOrderEvent(): bool
    {
        return match ($this) {
            self::ORDER_CREATED,
            self::ORDER_UPDATED,
            self::ORDER_PROCESSED,
            self::ORDER_CANCELLED,
            self::ORDER_REFUNDED,
            self::ORDER_SHIPPED,
            self::ORDER_NOTE_ADDED => true,
            default => false,
        };
    }

    /**
     * Check if event type relates to inventory.
     */
    public function isInventoryEvent(): bool
    {
        return match ($this) {
            self::STOCK_LEVEL_CHANGED,
            self::ITEM_CREATED,
            self::ITEM_UPDATED,
            self::ITEM_DELETED,
            self::PRICE_CHANGED,
            self::BATCH_CREATED,
            self::BATCH_UPDATED => true,
            default => false,
        };
    }
}
