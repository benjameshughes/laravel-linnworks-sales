<?php

namespace App\Jobs;

use App\Models\Product;
use App\Models\SyncLog;
use App\Services\LinnworksApiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GetAllProductsJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $uniqueFor = 3600; // 1 hour

    protected ?string $startedBy;

    protected SyncLog $syncLog;

    public function __construct(?string $startedBy = null)
    {
        $this->startedBy = $startedBy ?? 'system';
        $this->onQueue('medium');
    }

    /**
     * The unique ID of the job.
     */
    public function uniqueId(): string
    {
        return 'get-all-products';
    }

    public function handle(LinnworksApiService $linnworksService): void
    {
        $this->syncLog = SyncLog::startSync(SyncLog::TYPE_PRODUCTS, [
            'started_by' => $this->startedBy,
            'job_type' => 'master',
        ]);

        Log::info('Starting master job to get all products', [
            'started_by' => $this->startedBy,
        ]);

        if (! $linnworksService->isConfigured()) {
            Log::error('Linnworks API is not configured');
            $this->syncLog->fail('Linnworks API not configured');
            throw new \Exception('Linnworks API is not configured. Please check your credentials.');
        }

        try {
            Log::info('Fetching all inventory items from Linnworks...');
            $inventoryItems = $linnworksService->getAllInventoryItems();

            Log::info("Found {$inventoryItems->count()} inventory items");

            if ($inventoryItems->isEmpty()) {
                Log::warning('No inventory items found.');
                $this->syncLog->complete(0);

                return;
            }

            $this->syncLog->update([
                'total_fetched' => $inventoryItems->count(),
                'metadata' => array_merge($this->syncLog->metadata ?? [], [
                    'total_products_found' => $inventoryItems->count(),
                    'sample_skus' => $inventoryItems->take(3)->pluck('ItemNumber')->toArray(),
                    'sync_type' => 'basic',
                ]),
            ]);

            $existingProducts = Product::whereIn('sku', $inventoryItems->pluck('ItemNumber')->toArray())
                ->pluck('sku')
                ->toArray();

            if (! empty($existingProducts)) {
                $existingCount = count($existingProducts);
                Log::info("Found {$existingCount} existing products to update");
            }

            // Process products in batches to avoid overwhelming the queue
            $jobsDispatched = 0;
            $batchSize = 50; // Process 50 products per job batch

            foreach ($inventoryItems->chunk($batchSize) as $chunkIndex => $chunk) {
                foreach ($chunk as $inventoryItem) {
                    ProcessProductJob::dispatch($inventoryItem, $this->syncLog->id);
                    $jobsDispatched++;
                }

                // Small delay between batches
                if ($chunkIndex > 0) {
                    usleep(100000); // 0.1 seconds
                }
            }

            Log::info('Dispatched individual product processing jobs', [
                'total_jobs_dispatched' => $jobsDispatched,
                'existing_products_count' => count($existingProducts),
            ]);

            $this->syncLog->update([
                'metadata' => array_merge($this->syncLog->metadata ?? [], [
                    'jobs_dispatched' => $jobsDispatched,
                    'existing_products_count' => count($existingProducts),
                    'master_job_completed_at' => now()->toDateTimeString(),
                ]),
            ]);

            Log::info('Master product sync job completed successfully', [
                'total_products' => $inventoryItems->count(),
                'jobs_dispatched' => $jobsDispatched,
            ]);

        } catch (\Exception $e) {
            Log::error('Master product sync job failed', ['error' => $e->getMessage()]);
            $this->syncLog->fail($e->getMessage());
            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('GetAllProductsJob failed', [
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}
