<?php

namespace App\Jobs;

use App\Models\Product;
use App\Models\SyncLog;
use App\Services\LinnworksApiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessDetailedProductBatchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected array $stockItemIds;
    protected int $syncLogId;

    public function __construct(array $stockItemIds, int $syncLogId)
    {
        $this->stockItemIds = $stockItemIds;
        $this->syncLogId = $syncLogId;
    }

    public function handle(LinnworksApiService $linnworksService): void
    {
        Log::info('Processing detailed product batch', [
            'batch_size' => count($this->stockItemIds),
            'sync_log_id' => $this->syncLogId
        ]);

        if (!$linnworksService->isConfigured()) {
            Log::error('Linnworks API is not configured for detailed batch job');
            $this->incrementSyncCounter('failed', count($this->stockItemIds));
            return;
        }

        try {
            // Fetch detailed info for all products in this batch
            $detailedProducts = $linnworksService->getStockItemsFullByIds($this->stockItemIds);
            
            if ($detailedProducts->isEmpty()) {
                Log::warning('No detailed product information returned from Linnworks', [
                    'requested_count' => count($this->stockItemIds)
                ]);
                $this->incrementSyncCounter('failed', count($this->stockItemIds));
                return;
            }

            $created = 0;
            $updated = 0;
            $failed = 0;
            
            foreach ($detailedProducts as $productData) {
                try {
                    $sku = $productData['ItemNumber'] ?? null;
                    $linnworksId = $productData['StockItemId'] ?? null;

                    if (!$sku || !$linnworksId) {
                        Log::warning('Product missing required identifiers in batch', [
                            'sku' => $sku,
                            'linnworks_id' => $linnworksId
                        ]);
                        $failed++;
                        continue;
                    }

                    // Try to find existing product by SKU or Linnworks ID
                    $existingProduct = Product::where('sku', $sku)
                        ->orWhere('linnworks_id', $linnworksId)
                        ->first();

                    if ($existingProduct) {
                        // Update existing product with detailed information
                        $updatedProduct = Product::fromLinnworksDetailedInventory($productData);
                        
                        $existingProduct->update($updatedProduct->getAttributes());
                        
                        Log::info('Updated product with detailed information', [
                            'sku' => $sku,
                            'linnworks_id' => $linnworksId,
                            'title' => $existingProduct->title
                        ]);
                        
                        $updated++;
                    } else {
                        // Create new product with detailed information
                        $product = Product::fromLinnworksDetailedInventory($productData);
                        $product->save();
                        
                        Log::info('Created new product with detailed information', [
                            'sku' => $sku,
                            'linnworks_id' => $linnworksId,
                            'title' => $product->title
                        ]);
                        
                        $created++;
                    }

                } catch (\Exception $e) {
                    Log::error('Failed to process product in batch', [
                        'sku' => $productData['ItemNumber'] ?? 'unknown',
                        'error' => $e->getMessage(),
                    ]);
                    $failed++;
                }
            }
            
            // Account for any products that weren't returned by the API
            $notReturned = count($this->stockItemIds) - $detailedProducts->count();
            if ($notReturned > 0) {
                Log::warning('Some products were not returned by detailed API', [
                    'requested' => count($this->stockItemIds),
                    'returned' => $detailedProducts->count(),
                    'missing' => $notReturned
                ]);
                $failed += $notReturned;
            }

            // Update counters
            if ($created > 0) {
                $this->incrementSyncCounter('created', $created);
            }
            if ($updated > 0) {
                $this->incrementSyncCounter('updated', $updated);
            }
            if ($failed > 0) {
                $this->incrementSyncCounter('failed', $failed);
            }

            Log::info('Completed detailed product batch processing', [
                'batch_size' => count($this->stockItemIds),
                'created' => $created,
                'updated' => $updated,
                'failed' => $failed
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to process detailed product batch', [
                'batch_size' => count($this->stockItemIds),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $this->incrementSyncCounter('failed', count($this->stockItemIds));
        }
    }

    protected function incrementSyncCounter(string $type, int $count = 1): void
    {
        try {
            $syncLog = SyncLog::find($this->syncLogId);
            if (!$syncLog) {
                Log::warning('Sync log not found for counter increment', [
                    'sync_log_id' => $this->syncLogId,
                    'counter_type' => $type
                ]);
                return;
            }

            $field = match($type) {
                'created' => 'total_created',
                'updated' => 'total_updated', 
                'skipped' => 'total_skipped',
                'failed' => 'total_failed',
                default => null
            };

            if ($field) {
                $syncLog->increment($field, $count);
                
                // Check if this might be the last job and complete the sync
                $this->checkAndCompleteSyncIfDone($syncLog);
            }

        } catch (\Exception $e) {
            Log::error('Failed to increment sync counter', [
                'sync_log_id' => $this->syncLogId,
                'counter_type' => $type,
                'error' => $e->getMessage()
            ]);
        }
    }

    protected function checkAndCompleteSyncIfDone(SyncLog $syncLog): void
    {
        $totalFetched = $syncLog->total_fetched ?? 0;
        $totalProcessed = ($syncLog->total_created ?? 0) + 
                         ($syncLog->total_updated ?? 0) + 
                         ($syncLog->total_skipped ?? 0) + 
                         ($syncLog->total_failed ?? 0);

        // If we've processed all products, complete the sync
        if ($totalProcessed >= $totalFetched && $syncLog->status === SyncLog::STATUS_STARTED) {
            $syncLog->update([
                'status' => SyncLog::STATUS_COMPLETED,
                'completed_at' => now(),
                'metadata' => array_merge($syncLog->metadata ?? [], [
                    'completed_by_detailed_batch_job' => true,
                    'final_totals' => [
                        'fetched' => $totalFetched,
                        'created' => $syncLog->total_created,
                        'updated' => $syncLog->total_updated,
                        'skipped' => $syncLog->total_skipped,
                        'failed' => $syncLog->total_failed,
                    ]
                ])
            ]);

            Log::info('Product sync completed by detailed batch job', [
                'sync_log_id' => $syncLog->id,
                'total_processed' => $totalProcessed,
                'total_fetched' => $totalFetched
            ]);
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('ProcessDetailedProductBatchJob failed', [
            'batch_size' => count($this->stockItemIds),
            'sync_log_id' => $this->syncLogId,
            'error' => $exception->getMessage(),
        ]);

        $this->incrementSyncCounter('failed', count($this->stockItemIds));
    }
}