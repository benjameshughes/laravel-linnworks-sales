<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Jobs\SyncRecentOrdersJob;

/**
 * Scheduler doorway to SyncRecentOrdersJob. The scheduler cannot dispatch a
 * job directly, so this exists purely to knock on the queue.
 */
final class SyncOpenOrders extends Command
{
    protected $signature = 'sync:orders';

    protected $description = 'Queue a sync of open orders, updating any that have since been processed';

    public function handle(): int
    {
        SyncRecentOrdersJob::dispatch(startedBy: 'command');

        $this->info('Queued. Run a worker on the high queue to process it.');

        return self::SUCCESS;
    }
}
