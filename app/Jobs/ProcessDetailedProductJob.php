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

class ProcessDetailedProductJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected array $inventoryItem;
    protected int $syncLogId;

    public function __construct(array $inventoryItem, int $syncLogId)
    {
        $this->inventoryItem = $inventoryItem;
        $this->syncLogId = $syncLogId;
        $this->onQueue('medium');
    }

    public function handle(): void
    {
        try {
            $sku = $this->inventoryItem['ItemNumber'] ?? null;
            $linnworksId = $this->inventoryItem['StockItemId'] ?? null;

            if (!$sku || !$linnworksId) {
                Log::warning('Product missing required identifiers', [
                    'sku' => $sku,
                    'linnworks_id' => $linnworksId
                ]);
                $this->incrementSyncCounter('failed');
                return;
            }

            // Try to find existing product by SKU or Linnworks ID
            $existingProduct = Product::where('sku', $sku)
                ->orWhere('linnworks_id', $linnworksId)
                ->first();

            if ($existingProduct) {
                // Update existing product with detailed information
                $updatedProduct = Product::fromLinnworksDetailedInventory($this->inventoryItem);
                
                $existingProduct->update($updatedProduct->getAttributes());
                
                Log::info('Updated product with detailed information', [
                    'sku' => $sku,
                    'linnworks_id' => $linnworksId,
                    'title' => $existingProduct->title
                ]);
                
                $this->incrementSyncCounter('updated');
            } else {
                // Create new product with detailed information
                $product = Product::fromLinnworksDetailedInventory($this->inventoryItem);
                $product->save();
                
                Log::info('Created new product with detailed information', [
                    'sku' => $sku,
                    'linnworks_id' => $linnworksId,
                    'title' => $product->title
                ]);
                
                $this->incrementSyncCounter('created');
            }

        } catch (\Exception $e) {
            Log::error('Failed to process detailed product', [
                'sku' => $this->inventoryItem['ItemNumber'] ?? 'unknown',
                'error' => $e->getMessage(),
            ]);
            $this->incrementSyncCounter('failed');
        }
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
                    'completed_by_detailed_job' => true,
                    'final_totals' => [
                        'fetched' => $totalFetched,
                        'created' => $syncLog->total_created,
                        'updated' => $syncLog->total_updated,
                        'skipped' => $syncLog->total_skipped,
                        'failed' => $syncLog->total_failed,
                    ]
                ])
            ]);

            Log::info('Product sync completed by detailed job', [
                'sync_log_id' => $syncLog->id,
                'total_processed' => $totalProcessed,
                'total_fetched' => $totalFetched
            ]);
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('ProcessDetailedProductJob failed', [
            'sku' => $this->inventoryItem['ItemNumber'] ?? 'unknown',
            'sync_log_id' => $this->syncLogId,
            'error' => $exception->getMessage(),
        ]);

        $this->incrementSyncCounter('failed');
    }
}