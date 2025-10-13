<?php

declare(strict_types=1);

namespace App\Exceptions\Linnworks;

/**
 * Thrown when order sync operations fail
 */
final class SyncException extends LinnworksException
{
    public static function noOrdersFound(string $dateRange): self
    {
        return new self(
            'No orders found for sync',
            ['date_range' => $dateRange]
        );
    }

    public static function importFailed(int $orderCount, string $reason): self
    {
        return new self(
            "Failed to import {$orderCount} orders",
            [
                'order_count' => $orderCount,
                'reason' => $reason,
            ]
        );
    }

    public static function partialImport(int $attempted, int $successful, int $failed): self
    {
        return new self(
            'Order import partially completed with errors',
            [
                'attempted' => $attempted,
                'successful' => $successful,
                'failed' => $failed,
            ]
        );
    }
}
