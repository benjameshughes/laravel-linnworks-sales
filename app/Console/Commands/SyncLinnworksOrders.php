<?php

namespace App\Console\Commands;

use App\Models\Order;
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
    public function handle(LinnworksApiService $linnworksService)
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
        $openOrders = $linnworksService->getRecentOpenOrders($days);
        $this->info("Found {$openOrders->count()} open orders");

        // Get processed orders
        $processedOrders = $linnworksService->getProcessedOrders($from, $to);
        $this->info("Found {$processedOrders->count()} processed orders");

        // Combine all orders
        $allOrders = $openOrders->merge($processedOrders);
        $this->info("Total orders to process: {$allOrders->count()}");

        if ($allOrders->isEmpty()) {
            $this->warn('No orders found to sync.');
            return 0;
        }

        $progressBar = $this->output->createProgressBar($allOrders->count());
        $progressBar->start();

        $created = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($allOrders as $linnworksOrder) {
            if (!$linnworksOrder->orderId) {
                $skipped++;
                $progressBar->advance();
                continue;
            }

            $existingOrder = Order::where('linnworks_order_id', $linnworksOrder->orderId)->first();

            if ($existingOrder && !$force) {
                // Update existing order
                $orderModel = Order::fromLinnworksOrder($linnworksOrder);
                $existingOrder->fill($orderModel->getAttributes());
                
                if ($existingOrder->isDirty()) {
                    $existingOrder->save();
                    $updated++;
                } else {
                    $skipped++;
                }
            } else {
                // Create new order
                $orderModel = Order::fromLinnworksOrder($linnworksOrder);
                
                try {
                    $orderModel->save();
                    $created++;
                } catch (\Exception $e) {
                    if ($existingOrder && $force) {
                        // Force update
                        $existingOrder->fill($orderModel->getAttributes());
                        $existingOrder->save();
                        $updated++;
                    } else {
                        $this->error("Failed to save order {$linnworksOrder->orderId}: " . $e->getMessage());
                        $skipped++;
                    }
                }
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine();

        $this->info("Sync completed!");
        $this->table(['Status', 'Count'], [
            ['Created', $created],
            ['Updated', $updated],
            ['Skipped', $skipped],
            ['Total', $allOrders->count()],
        ]);

        return 0;
    }
}
