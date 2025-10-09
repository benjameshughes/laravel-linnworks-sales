<?php

namespace App\Jobs;

use App\Actions\Linnworks\Orders\ImportOrders;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessLinnworksOrders implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    public int $tries = 3;
    public int $maxExceptions = 2;
    public int $timeout = 120; // 2 minutes

    /**
     * Create a new job instance.
     */
    public function __construct(
        public array $ordersData,
        public bool $forceUpdate = false
    ) {
        $this->onQueue('low');
    }

    /**
     * Execute the job.
     */
    public function handle(ImportOrders $importOrders): void
    {
        Log::info('Starting processing job for orders', [
            'orders_count' => count($this->ordersData),
            'force_update' => $this->forceUpdate
        ]);

        try {
            $result = $importOrders->handle($this->ordersData, $this->forceUpdate);

            Log::info('Completed processing orders batch', [
                'summary' => $result->toArray(),
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to process orders batch', [
                'orders_count' => count($this->ordersData),
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
        Log::error('ProcessLinnworksOrders job failed', [
            'orders_count' => count($this->ordersData),
            'error' => $exception->getMessage()
        ]);
    }

    /**
     * Calculate the number of seconds to wait before retrying the job.
     */
    public function backoff(): array
    {
        return [30, 60, 120]; // Wait 30s, then 1m, then 2m between retries
    }
}
