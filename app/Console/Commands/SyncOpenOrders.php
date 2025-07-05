<?php

namespace App\Console\Commands;

use App\Jobs\GetOpenOrderIdsJob;
use Illuminate\Console\Command;

class SyncOpenOrders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:open-orders 
                            {--force : Force sync all open orders}
                            {--debug : Show detailed output}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync all open orders from Linnworks';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Dispatching open orders sync jobs...');

        try {
            // Dispatch the master job that will handle everything
            GetOpenOrderIdsJob::dispatch('command');
            
            $this->info('Master job dispatched successfully!');
            $this->info('Individual order detail jobs will be processed by the queue.');
            $this->info('Use "php artisan queue:work" to process the jobs.');
            
            return 0;
        } catch (\Exception $e) {
            $this->error('Failed to dispatch sync job: ' . $e->getMessage());
            return 1;
        }
    }
}