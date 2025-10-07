<?php

namespace App\Console\Commands;

use App\Actions\Linnworks\Orders\ImportOrders;
use App\DataTransferObjects\ImportOrdersResult;
use App\Services\LinnworksApiService;
use Illuminate\Console\Command;
use Carbon\Carbon;

class SyncLinnworksOrders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:linnworks-orders 
                            {--days=7 : Number of days to sync from}
                            {--force : Force sync even if orders exist}
                            {--use-jobs : Use background jobs for processing}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync orders from Linnworks API to local database (use --use-jobs for background processing)';

    /**
     * Execute the console command.
     */
    public function handle(LinnworksApiService $linnworksService, ImportOrders $importOrders)
    {
        $days = (int) $this->option('days');
        $force = $this->option('force');
        $useJobs = $this->option('use-jobs');
        
        if ($useJobs) {
            $this->info("Dispatching Linnworks sync to background jobs...");
            
            // Call the queue command instead
            $exitCode = $this->call('queue:linnworks-sync', [
                '--days' => $days,
                '--force' => $force,
                '--type' => 'both',
            ]);
            
            if ($exitCode === 0) {
                $this->info('✅ Sync jobs have been queued successfully!');
                $this->info('Run "php artisan queue:work --queue=linnworks,linnworks-processing" to process them.');
            }
            
            return $exitCode;
        }

        $this->info("Starting Linnworks orders sync for last {$days} days...");

        if (!$linnworksService->isConfigured()) {
            $this->error('Linnworks API is not configured. Please check your credentials in .env file.');
            return 1;
        }

        $from = Carbon::now()->subDays($days);
        $to = Carbon::now();

        $this->info("Fetching orders from {$from->format('Y-m-d')} to {$to->format('Y-m-d')}");

        // Get recent open orders
        $openOrders = $linnworksService->getRecentOpenOrders(null, $days);
        $this->info("Found {$openOrders->count()} open orders");

        // Page through processed orders
        $processedOrders = collect();
        $pageNumber = 1;

        do {
            $processedResult = $linnworksService->getProcessedOrders($from, $to, $pageNumber);

            $processedOrders = $processedOrders->merge($processedResult->orders);
            $pageNumber++;
        } while ($processedResult->hasMorePages);

        $this->info("Collected {$processedOrders->count()} processed orders");

        // Combine all orders
        $allOrders = $openOrders->merge($processedOrders);
        $this->info("Total orders to import: {$allOrders->count()}");

        if ($allOrders->isEmpty()) {
            $this->warn('No orders found to sync.');
            return 0;
        }

        /** @var ImportOrdersResult $result */
        $result = $importOrders->handle($allOrders, $force);

        $this->info('Sync completed!');
        $this->table(['Metric', 'Count'], [
            ['Processed', $result->processed],
            ['Created', $result->created],
            ['Updated', $result->updated],
            ['Skipped', $result->skipped],
            ['Failed', $result->failed],
        ]);

        if ($result->failed > 0) {
            $this->warn('Some orders failed to import. Check logs tagged with "Failed to persist Linnworks order".');
        }

        return 0;
    }
}
