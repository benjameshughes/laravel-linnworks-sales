<?php

declare(strict_types=1);

namespace App\ValueObjects\Inventory;

/**
 * Batch operation result status enum.
 *
 * Uses PHP 8.1+ backed enums for type safety.
 */
enum BatchResultStatus: string
{
    case NOTSET = 'NOTSET';
    case SUCCESSFUL = 'SUCCESSFUL';
    case PARTIALLY_SUCCESSFUL = 'PARTIALLY_SUCCESSFUL';
    case FAILED = 'FAILED';

    /**
     * Check if status indicates success.
     */
    public function isSuccess(): bool
    {
        return match ($this) {
            self::SUCCESSFUL => true,
            self::PARTIALLY_SUCCESSFUL => true,
            default => false,
        };
    }

    /**
     * Check if status indicates failure.
     */
    public function isFailure(): bool
    {
        return $this === self::FAILED;
    }

    /**
     * Get human-readable description.
     */
    public function description(): string
    {
        return match ($this) {
            self::NOTSET => 'Status not set',
            self::SUCCESSFUL => 'All operations succeeded',
            self::PARTIALLY_SUCCESSFUL => 'Some operations succeeded',
            self::FAILED => 'All operations failed',
        };
    }

    /**
     * Get color for UI display.
     */
    public function color(): string
    {
        return match ($this) {
            self::SUCCESSFUL => 'green',
            self::PARTIALLY_SUCCESSFUL => 'yellow',
            self::FAILED => 'red',
            self::NOTSET => 'gray',
        };
    }
}
