<?php

namespace App\Console\Commands;

use App\Jobs\FetchLinnworksOrders;
use App\Services\LinnworksApiService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Queue;

class QueueLinnworksSync extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'queue:linnworks-sync 
                            {--days=7 : Number of days to sync from}
                            {--type=both : Type of orders to sync (open, processed, both)}
                            {--batch-size=100 : Batch size for API requests}
                            {--force : Force update existing orders}
                            {--delay=0 : Delay in seconds before starting the job}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Queue jobs to sync orders from Linnworks API';

    /**
     * Execute the console command.
     */
    public function handle(LinnworksApiService $linnworksService)
    {
        $days = (int) $this->option('days');
        $type = $this->option('type');
        $batchSize = (int) $this->option('batch-size');
        $force = $this->option('force');
        $delay = (int) $this->option('delay');

        // Validate inputs
        if (!in_array($type, ['open', 'processed', 'both'])) {
            $this->error('Invalid order type. Must be: open, processed, or both');
            return 1;
        }

        if ($days < 1 || $days > 365) {
            $this->error('Days must be between 1 and 365');
            return 1;
        }

        if ($batchSize < 10 || $batchSize > 500) {
            $this->error('Batch size must be between 10 and 500');
            return 1;
        }

        // Check API configuration
        if (!$linnworksService->isConfigured()) {
            $this->error('Linnworks API is not configured. Please check your credentials in .env file.');
            return 1;
        }

        $fromDate = Carbon::now()->subDays($days)->startOfDay();
        $toDate = Carbon::now()->endOfDay();

        $this->info("Queuing Linnworks sync job...");
        $this->table(['Parameter', 'Value'], [
            ['Date Range', "{$fromDate->format('Y-m-d')} to {$toDate->format('Y-m-d')}"],
            ['Order Type', $type],
            ['Batch Size', $batchSize],
            ['Force Update', $force ? 'Yes' : 'No'],
            ['Delay', $delay > 0 ? "{$delay} seconds" : 'None'],
        ]);

        if (!$this->confirm('Do you want to proceed with queuing this sync job?')) {
            $this->info('Sync job cancelled.');
            return 0;
        }

        try {
            // Check queue connection
            $queueConnection = config('queue.default');
            $this->info("Using queue connection: {$queueConnection}");

            // Dispatch the fetch job
            $job = FetchLinnworksOrders::dispatch(
                $fromDate,
                $toDate,
                $type,
                $batchSize
            );

            // Apply delay if specified
            if ($delay > 0) {
                $job->delay(now()->addSeconds($delay));
                $this->info("Job will start in {$delay} seconds");
            }

            $this->info('✅ Linnworks sync job has been queued successfully!');
            $this->newLine();

            $this->info('Monitor progress with:');
            $this->line('  • php artisan queue:work --queue=linnworks,linnworks-processing');
            $this->line('  • php artisan pail --filter=linnworks');
            $this->newLine();

            $this->info('Check job status with:');
            $this->line('  • php artisan queue:monitor');
            $this->line('  • Check your queue dashboard or logs');

            // Show current queue stats
            $this->newLine();
            $this->info('Current queue status:');
            
            try {
                $linnworksJobs = Queue::size('linnworks');
                $processingJobs = Queue::size('linnworks-processing');
                $defaultJobs = Queue::size('default');
                
                $this->table(['Queue', 'Pending Jobs'], [
                    ['linnworks', $linnworksJobs],
                    ['linnworks-processing', $processingJobs],
                    ['default', $defaultJobs],
                ]);
            } catch (\Exception $e) {
                $this->warn('Could not retrieve queue stats: ' . $e->getMessage());
            }

            return 0;

        } catch (\Exception $e) {
            $this->error('Failed to queue sync job: ' . $e->getMessage());
            return 1;
        }
    }
}
