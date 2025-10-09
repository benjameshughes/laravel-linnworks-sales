<?php

namespace App\Jobs;

use App\Services\LinnworksApiService;
use App\Jobs\ProcessLinnworksOrders;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class FetchLinnworksOrders implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    public int $tries = 3;
    public int $maxExceptions = 2;
    public int $timeout = 300; // 5 minutes

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Carbon $fromDate,
        public Carbon $toDate,
        public string $orderType = 'both', // 'open', 'processed', 'both'
        public int $batchSize = 100
    ) {
        $this->onQueue('low');
    }

    /**
     * Execute the job.
     */
    public function handle(LinnworksApiService $linnworksService): void
    {
        Log::info('Starting Linnworks orders fetch job', [
            'from' => $this->fromDate->toDateString(),
            'to' => $this->toDate->toDateString(),
            'type' => $this->orderType,
            'batch_size' => $this->batchSize
        ]);

        if (!$linnworksService->isConfigured()) {
            Log::error('Linnworks API not configured, cannot fetch orders');
            $this->fail('Linnworks API is not configured');
            return;
        }

        $allOrders = collect();

        try {
            // Fetch open orders if requested
            if ($this->orderType === 'open' || $this->orderType === 'both') {
                $windowDays = max(1, $this->fromDate->diffInDays($this->toDate) ?: 1);

                Log::info('Fetching open orders from Linnworks', [
                    'window_days' => $windowDays,
                ]);

                $openOrders = $linnworksService->getRecentOpenOrders(null, $windowDays);
                $allOrders = $allOrders->merge($openOrders);

                Log::info('Fetched open orders', ['count' => $openOrders->count()]);
            }

            // Fetch processed orders if requested
            if ($this->orderType === 'processed' || $this->orderType === 'both') {
                Log::info('Fetching processed orders from Linnworks');

                $pageNumber = 1;

                do {
                    $result = $linnworksService->getProcessedOrders(
                        $this->fromDate,
                        $this->toDate,
                        $pageNumber,
                        $this->batchSize
                    );

                    $ordersPage = $result->orders;
                    $allOrders = $allOrders->merge($ordersPage);

                    Log::info('Fetched processed orders page', [
                        'page' => $pageNumber,
                        'count' => $ordersPage->count(),
                        'total_results' => $result->totalResults,
                    ]);

                    $pageNumber++;
                } while ($result->hasMorePages);
            }

            Log::info('Total orders fetched from Linnworks', ['total' => $allOrders->count()]);

            // Dispatch processing jobs in batches
            $chunks = $allOrders->chunk(50); // Process 50 orders per job
            
            foreach ($chunks as $index => $chunk) {
                ProcessLinnworksOrders::dispatch($chunk->toArray())
                    ->delay(now()->addSeconds($index * 2)); // Stagger jobs by 2 seconds
            }

            Log::info('Dispatched processing jobs', ['jobs_count' => $chunks->count()]);

        } catch (\Exception $e) {
            Log::error('Failed to fetch orders from Linnworks', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            $this->fail($e);
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('FetchLinnworksOrders job failed', [
            'from' => $this->fromDate->toDateString(),
            'to' => $this->toDate->toDateString(),
            'error' => $exception->getMessage()
        ]);
    }
}
