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

class GetDetailedProductsJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $uniqueFor = 3600; // 1 hour

    protected ?string $startedBy;

    protected SyncLog $syncLog;

    protected bool $updateExistingOnly;

    public function __construct(?string $startedBy = null, bool $updateExistingOnly = false)
    {
        $this->startedBy = $startedBy ?? 'system';
        $this->updateExistingOnly = $updateExistingOnly;
        $this->onQueue('medium');
    }

    /**
     * The unique ID of the job.
     */
    public function uniqueId(): string
    {
        return 'get-detailed-products-'.($this->updateExistingOnly ? 'existing' : 'all');
    }

    public function handle(LinnworksApiService $linnworksService): void
    {
        $this->syncLog = SyncLog::startSync(SyncLog::TYPE_PRODUCTS, [
            'started_by' => $this->startedBy,
            'job_type' => 'detailed_master',
            'update_existing_only' => $this->updateExistingOnly,
        ]);

        Log::info('Starting detailed product sync job', [
            'started_by' => $this->startedBy,
            'update_existing_only' => $this->updateExistingOnly,
        ]);

        if (! $linnworksService->isConfigured()) {
            Log::error('Linnworks API is not configured');
            $this->syncLog->fail('Linnworks API not configured');
            throw new \Exception('Linnworks API is not configured. Please check your credentials.');
        }

        try {
            if ($this->updateExistingOnly) {
                $this->syncExistingProductsOnly($linnworksService);
            } else {
                $this->syncAllProductsDetailed($linnworksService);
            }

        } catch (\Exception $e) {
            Log::error('Detailed product sync job failed', ['error' => $e->getMessage()]);
            $this->syncLog->fail($e->getMessage());
            throw $e;
        }
    }

    protected function syncExistingProductsOnly(LinnworksApiService $linnworksService): void
    {
        // Get existing products that need detailed sync
        $existingProducts = Product::whereNotNull('linnworks_id')
            ->where(function ($q) {
                $q->whereJsonMissing('metadata.sync_type')
                    ->orWhereJsonDoesntContain('metadata.sync_type', 'detailed');
            })
            ->limit(500) // Limit to prevent overwhelming API
            ->get();

        if ($existingProducts->isEmpty()) {
            Log::info('No existing products need detailed sync');
            $this->syncLog->complete(0);

            return;
        }

        Log::info("Found {$existingProducts->count()} existing products to sync detailed info");

        $this->syncLog->update([
            'total_fetched' => $existingProducts->count(),
            'metadata' => array_merge($this->syncLog->metadata ?? [], [
                'total_products_found' => $existingProducts->count(),
                'sync_type' => 'detailed_existing_only',
                'sample_skus' => $existingProducts->take(3)->pluck('sku')->toArray(),
            ]),
        ]);

        // Get detailed info for existing products in batches by IDs
        $stockItemIds = $existingProducts->pluck('linnworks_id')->filter()->toArray();
        $this->processBatchedDetailSync($linnworksService, $stockItemIds);
    }

    protected function syncAllProductsDetailed(LinnworksApiService $linnworksService): void
    {
        Log::info('Fetching all detailed inventory items from Linnworks...');
        $inventoryItems = $linnworksService->getAllInventoryItemsFull();

        Log::info("Found {$inventoryItems->count()} detailed inventory items");

        if ($inventoryItems->isEmpty()) {
            Log::warning('No detailed inventory items found.');
            $this->syncLog->complete(0);

            return;
        }

        $this->syncLog->update([
            'total_fetched' => $inventoryItems->count(),
            'metadata' => array_merge($this->syncLog->metadata ?? [], [
                'total_products_found' => $inventoryItems->count(),
                'sync_type' => 'detailed_full',
                'sample_skus' => $inventoryItems->take(3)->pluck('ItemNumber')->toArray(),
            ]),
        ]);

        $existingProducts = Product::whereIn('sku', $inventoryItems->pluck('ItemNumber')->toArray())
            ->pluck('sku')
            ->toArray();

        if (! empty($existingProducts)) {
            Log::info("Found {count($existingProducts)} existing products to update with detailed info");
        }

        // Process products in smaller batches due to detailed API limits
        $jobsDispatched = 0;
        $batchSize = 25; // Smaller batch size for detailed endpoint

        foreach ($inventoryItems->chunk($batchSize) as $chunkIndex => $chunk) {
            // Delay each batch to respect 150/minute rate limit
            $delay = $chunkIndex * 30; // 30 seconds delay between batches

            foreach ($chunk as $inventoryItem) {
                ProcessDetailedProductJob::dispatch($inventoryItem, $this->syncLog->id)
                    ->delay(now()->addSeconds($delay));
                $jobsDispatched++;
            }
        }

        Log::info('Dispatched detailed product processing jobs', [
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

        Log::info('Detailed product sync job completed successfully', [
            'total_products' => $inventoryItems->count(),
            'jobs_dispatched' => $jobsDispatched,
        ]);
    }

    protected function processBatchedDetailSync(LinnworksApiService $linnworksService, array $stockItemIds): void
    {
        $jobsDispatched = 0;
        $batchSize = 20; // Efficient batch size for GetStockItemsFullByIds (rate limit: 250/minute)

        foreach (array_chunk($stockItemIds, $batchSize) as $chunkIndex => $chunk) {
            // Delay batches to respect 250/minute rate limit
            $delay = $chunkIndex * 15; // 15 seconds delay between batches

            ProcessDetailedProductBatchJob::dispatch($chunk, $this->syncLog->id)
                ->delay(now()->addSeconds($delay));

            $jobsDispatched++;
        }

        Log::info('Dispatched batched detailed product processing jobs', [
            'total_jobs_dispatched' => $jobsDispatched,
            'total_product_ids' => count($stockItemIds),
        ]);

        $this->syncLog->update([
            'metadata' => array_merge($this->syncLog->metadata ?? [], [
                'jobs_dispatched' => $jobsDispatched,
                'total_product_ids' => count($stockItemIds),
                'master_job_completed_at' => now()->toDateTimeString(),
            ]),
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('GetDetailedProductsJob failed', [
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}
