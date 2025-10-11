<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event fired when orders have been synced from Linnworks
 *
 * This triggers cache warming for metrics to ensure
 * dashboard data is always fresh and instant for users.
 */
final readonly class OrdersSynced implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    /**
     * Create a new event instance.
     *
     * @param  int  $ordersProcessed  Number of orders that were synced
     * @param  string  $syncType  Type of sync (open_orders, all_orders, etc.)
     */
    public function __construct(
        public int $ordersProcessed,
        public string $syncType = 'open_orders',
    ) {}
}
