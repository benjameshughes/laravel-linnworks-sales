<?php

namespace App\Console\Commands;

use App\Services\SalesDataSyncService;
use App\Services\LinnworksApiService;
use Illuminate\Console\Command;
use Carbon\Carbon;

class SyncSalesData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sales:sync 
                            {--from= : Start date for sync (Y-m-d format)}
                            {--to= : End date for sync (Y-m-d format)}
                            {--days= : Number of days to sync from today (default: 30)}
                            {--force : Force update existing orders}
                            {--test : Test API connection only}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync sales data from Linnworks API';

    /**
     * Execute the console command.
     */
    public function handle(
        SalesDataSyncService $syncService,
        LinnworksApiService $linnworksApi
    ): int {
        $this->info('🔄 Starting Linnworks sales data synchronization...');

        // Test connection first
        if (!$linnworksApi->testConnection()) {
            $this->error('❌ Failed to connect to Linnworks API. Please check your credentials.');
            return self::FAILURE;
        }

        $this->info('✅ Successfully connected to Linnworks API');

        // If test mode, just return success
        if ($this->option('test')) {
            $this->info('🧪 Test mode - API connection successful');
            return self::SUCCESS;
        }

        // Determine date range
        $from = $this->getFromDate();
        $to = $this->getToDate();
        $forceUpdate = $this->option('force');

        $this->info("📅 Syncing data from {$from->toDateString()} to {$to->toDateString()}");

        if ($forceUpdate) {
            $this->warn('⚠️  Force update enabled - existing orders will be updated');
        }

        // Confirm before proceeding
        if (!$this->confirm('Do you want to proceed with the synchronization?')) {
            $this->info('❌ Synchronization cancelled');
            return self::SUCCESS;
        }

        // Start sync with progress bar
        $progressBar = $this->output->createProgressBar();
        $progressBar->setFormat('verbose');
        $progressBar->start();

        try {
            $stats = $syncService->syncSalesData($from, $to, $forceUpdate);
            $progressBar->finish();

            $this->newLine(2);
            $this->displayStats($stats);

            if ($stats['errors'] > 0) {
                $this->warn("⚠️  Synchronization completed with {$stats['errors']} errors. Check logs for details.");
                return self::FAILURE;
            }

            $this->info('✅ Synchronization completed successfully!');
            return self::SUCCESS;

        } catch (\Exception $e) {
            $progressBar->finish();
            $this->newLine(2);
            $this->error("❌ Synchronization failed: {$e->getMessage()}");
            return self::FAILURE;
        }
    }

    /**
     * Get the start date for synchronization
     */
    private function getFromDate(): Carbon
    {
        if ($from = $this->option('from')) {
            return Carbon::createFromFormat('Y-m-d', $from);
        }

        $days = $this->option('days') ?: config('linnworks.sync.default_date_range', 30);
        
        return Carbon::now()->subDays($days);
    }

    /**
     * Get the end date for synchronization
     */
    private function getToDate(): Carbon
    {
        if ($to = $this->option('to')) {
            return Carbon::createFromFormat('Y-m-d', $to);
        }

        return Carbon::now();
    }

    /**
     * Display synchronization statistics
     */
    private function displayStats(array $stats): void
    {
        $this->info('📊 Synchronization Statistics:');
        $this->table(
            ['Metric', 'Value'],
            [
                ['Orders Processed', $stats['orders_processed']],
                ['Orders Created', $stats['orders_created']],
                ['Orders Updated', $stats['orders_updated']],
                ['Items Processed', $stats['items_processed']],
                ['Channels Created', $stats['channels_created']],
                ['Errors', $stats['errors']],
                ['Duration', $stats['duration'] . ' seconds'],
            ]
        );
    }
}
