<?php

namespace App\Jobs;

use App\Models\Order;
use App\Models\SyncLog;
use App\Services\LinnworksApiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GetOpenOrderDetailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected string $orderUuid;
    protected int $syncLogId;

    /**
     * Create a new job instance.
     */
    public function __construct(string $orderUuid, int $syncLogId)
    {
        $this->orderUuid = $orderUuid;
        $this->syncLogId = $syncLogId;
    }

    /**
     * Execute the job.
     */
    public function handle(LinnworksApiService $linnworksService): void
    {
        Log::info('Processing order detail job', [
            'order_uuid' => $this->orderUuid,
            'sync_log_id' => $this->syncLogId
        ]);

        if (!$linnworksService->isConfigured()) {
            Log::error('Linnworks API is not configured for detail job', [
                'order_uuid' => $this->orderUuid
            ]);
            $this->incrementSyncCounter('failed');
            return;
        }

        try {
            // Step 1: Check if order already exists in database
            $existingOrder = Order::where('linnworks_order_id', $this->orderUuid)->first();
            
            if ($existingOrder) {
                Log::info('Order already exists, discarding', [
                    'order_uuid' => $this->orderUuid,
                    'order_number' => $existingOrder->order_number
                ]);
                $this->incrementSyncCounter('skipped');
                return;
            }

            // Step 2: Order doesn't exist, fetch details from Linnworks
            Log::info('Fetching order details from Linnworks', [
                'order_uuid' => $this->orderUuid
            ]);

            $orderDetails = $linnworksService->getOrdersByIds([$this->orderUuid]);
            
            if ($orderDetails->isEmpty()) {
                Log::warning('No order details returned from Linnworks', [
                    'order_uuid' => $this->orderUuid
                ]);
                $this->incrementSyncCounter('failed');
                return;
            }

            $linnworksOrder = $orderDetails->first();

            // Step 3: Create new order in database
            $orderModel = Order::fromLinnworksOrder($linnworksOrder);
            $orderModel->save();

            Log::info('Successfully created new order', [
                'order_uuid' => $this->orderUuid,
                'order_number' => $orderModel->order_number,
                'channel' => $orderModel->channel_name,
                'total_charge' => $orderModel->total_charge
            ]);

            $this->incrementSyncCounter('created');

        } catch (\Exception $e) {
            Log::error('Failed to process order detail', [
                'order_uuid' => $this->orderUuid,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $this->incrementSyncCounter('failed');
        }
    }

    /**
     * Increment counter in the sync log
     */
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

    /**
     * Check if all jobs are done and complete the sync
     */
    protected function checkAndCompleteSyncIfDone(SyncLog $syncLog): void
    {
        $totalFetched = $syncLog->total_fetched ?? 0;
        $totalProcessed = ($syncLog->total_created ?? 0) + 
                         ($syncLog->total_updated ?? 0) + 
                         ($syncLog->total_skipped ?? 0) + 
                         ($syncLog->total_failed ?? 0);

        // If we've processed all orders, complete the sync
        if ($totalProcessed >= $totalFetched && $syncLog->status === SyncLog::STATUS_STARTED) {
            $syncLog->update([
                'status' => SyncLog::STATUS_COMPLETED,
                'completed_at' => now(),
                'metadata' => array_merge($syncLog->metadata ?? [], [
                    'completed_by_detail_job' => true,
                    'final_totals' => [
                        'fetched' => $totalFetched,
                        'created' => $syncLog->total_created,
                        'updated' => $syncLog->total_updated,
                        'skipped' => $syncLog->total_skipped,
                        'failed' => $syncLog->total_failed,
                    ]
                ])
            ]);

            Log::info('Sync completed by detail job', [
                'sync_log_id' => $syncLog->id,
                'total_processed' => $totalProcessed,
                'total_fetched' => $totalFetched
            ]);
        }
    }

    /**
     * The job failed to process.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('GetOpenOrderDetailJob failed', [
            'order_uuid' => $this->orderUuid,
            'sync_log_id' => $this->syncLogId,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);

        $this->incrementSyncCounter('failed');
    }
}