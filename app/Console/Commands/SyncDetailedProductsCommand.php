<?php

namespace App\Console\Commands;

use App\Jobs\GetDetailedProductsJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncDetailedProductsCommand extends Command
{
    protected $signature = 'linnworks:sync-detailed-products 
                           {--existing-only : Only sync detailed info for existing products}
                           {--force : Force sync even if another sync is running}';

    protected $description = 'Sync detailed product information from Linnworks using GetStockItemsFull endpoint';

    public function handle()
    {
        $existingOnly = $this->option('existing-only');
        $force = $this->option('force');

        if ($existingOnly) {
            $this->info('Starting detailed sync for existing products only...');
            $this->line('This will update existing products with detailed information from Linnworks.');
        } else {
            $this->info('Starting full detailed product sync...');
            $this->line('This will fetch all products with detailed information from Linnworks.');
            $this->warn('Note: This uses the GetStockItemsFull endpoint with a 150/minute rate limit.');
        }

        if (!$force) {
            if (!$this->confirm('Do you want to continue?')) {
                $this->info('Sync cancelled.');
                return 0;
            }
        }

        try {
            // Dispatch the detailed product sync job
            GetDetailedProductsJob::dispatch('cli', $existingOnly);
            
            $syncType = $existingOnly ? 'existing products' : 'all products';
            $this->info("Detailed product sync job dispatched for {$syncType}!");
            $this->line('Check the logs for progress updates.');
            
            Log::info('Detailed product sync initiated via CLI', [
                'existing_only' => $existingOnly,
                'started_by' => 'cli'
            ]);

            return 0;

        } catch (\Exception $e) {
            $this->error('Failed to dispatch detailed product sync job: ' . $e->getMessage());
            Log::error('CLI detailed product sync failed', [
                'error' => $e->getMessage(),
                'existing_only' => $existingOnly
            ]);
            return 1;
        }
    }
}