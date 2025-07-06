<?php

namespace App\Jobs;

use App\Models\Product;
use App\Models\SyncLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessProductJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected array $inventoryItem;
    protected int $syncLogId;

    public function __construct(array $inventoryItem, int $syncLogId)
    {
        $this->inventoryItem = $inventoryItem;
        $this->syncLogId = $syncLogId;
    }

    public function handle(): void
    {
        $sku = $this->inventoryItem['ItemNumber'] ?? null;
        $linnworksId = $this->inventoryItem['StockItemId'] ?? null;

        Log::info('Processing product sync job', [
            'sku' => $sku,
            'linnworks_id' => $linnworksId,
            'sync_log_id' => $this->syncLogId
        ]);

        if (!$sku || !$linnworksId) {
            Log::error('Invalid product data - missing SKU or ID', [
                'sku' => $sku,
                'linnworks_id' => $linnworksId,
                'inventory_item' => $this->inventoryItem
            ]);
            $this->incrementSyncCounter('failed');
            return;
        }

        try {
            $existingProduct = Product::where('sku', $sku)->first();
            
            if ($existingProduct) {
                $this->updateExistingProduct($existingProduct);
            } else {
                $this->createNewProduct();
            }

        } catch (\Exception $e) {
            Log::error('Failed to process product', [
                'sku' => $sku,
                'linnworks_id' => $linnworksId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $this->incrementSyncCounter('failed');
        }
    }

    protected function updateExistingProduct(Product $product): void
    {
        $updatedProduct = Product::fromLinnworksInventory($this->inventoryItem);
        
        $product->update($updatedProduct->toArray());

        Log::info('Successfully updated existing product', [
            'sku' => $product->sku,
            'title' => $product->title,
            'category' => $product->category_name,
            'stock_available' => $product->stock_available
        ]);

        $this->incrementSyncCounter('updated');
    }

    protected function createNewProduct(): void
    {
        $product = Product::fromLinnworksInventory($this->inventoryItem);
        $product->save();

        Log::info('Successfully created new product', [
            'sku' => $product->sku,
            'title' => $product->title,
            'category' => $product->category_name,
            'stock_available' => $product->stock_available
        ]);

        $this->incrementSyncCounter('created');
    }

    protected function incrementSyncCounter(string $type): void
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
                $syncLog->increment($field);
                
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

        if ($totalProcessed >= $totalFetched && $syncLog->status === SyncLog::STATUS_STARTED) {
            $syncLog->update([
                'status' => SyncLog::STATUS_COMPLETED,
                'completed_at' => now(),
                'metadata' => array_merge($syncLog->metadata ?? [], [
                    'completed_by_product_job' => true,
                    'final_totals' => [
                        'fetched' => $totalFetched,
                        'created' => $syncLog->total_created,
                        'updated' => $syncLog->total_updated,
                        'skipped' => $syncLog->total_skipped,
                        'failed' => $syncLog->total_failed,
                    ]
                ])
            ]);

            Log::info('Product sync completed by worker job', [
                'sync_log_id' => $syncLog->id,
                'total_processed' => $totalProcessed,
                'total_fetched' => $totalFetched
            ]);
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('ProcessProductJob failed', [
            'sku' => $this->inventoryItem['ItemNumber'] ?? 'unknown',
            'sync_log_id' => $this->syncLogId,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);

        $this->incrementSyncCounter('failed');
    }
}