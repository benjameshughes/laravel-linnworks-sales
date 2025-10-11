<?php

namespace App\Console\Commands;

use App\Jobs\SyncOrdersJob;
use Illuminate\Console\Command;

class SyncOpenOrders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:orders
                            {--force : Force sync all orders}
                            {--debug : Show detailed output}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync all orders (open and processed) from Linnworks';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Dispatching unified orders sync job...');

        try {
            // Dispatch the unified sync job
            SyncOrdersJob::dispatch(startedBy: 'command');

            $this->info('Sync job dispatched successfully!');
            $this->info('The job will sync all open and processed orders.');
            $this->info('Use "php artisan queue:work" to process the job.');

            return 0;
        } catch (\Exception $e) {
            $this->error('Failed to dispatch sync job: ' . $e->getMessage());
            return 1;
        }
    }
}