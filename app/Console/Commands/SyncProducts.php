<?php

namespace App\Console\Commands;

use App\Jobs\GetAllProductsJob;
use Illuminate\Console\Command;

class SyncProducts extends Command
{
    protected $signature = 'sync:products 
                            {--force : Force sync all products}
                            {--debug : Show detailed output}';

    protected $description = 'Sync all products from Linnworks';

    public function handle()
    {
        $this->info('Dispatching product sync jobs...');

        try {
            GetAllProductsJob::dispatch('command');
            
            $this->info('Master product sync job dispatched successfully!');
            $this->info('Individual product processing jobs will be processed by the queue.');
            $this->info('Use "php artisan queue:work" to process the jobs.');
            
            return 0;
        } catch (\Exception $e) {
            $this->error('Failed to dispatch product sync job: ' . $e->getMessage());
            return 1;
        }
    }
}
