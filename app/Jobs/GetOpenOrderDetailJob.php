<?php

namespace App\Jobs;

use App\Actions\Linnworks\Orders\ImportOrders;
use App\DataTransferObjects\ImportOrdersResult;
use App\Models\Order;
use App\Models\SyncLog;
use App\Services\LinnworksApiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Livewire\Livewire;

class GetOpenOrderDetailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected array $orderUuids;

    protected int $syncLogId;

    /**
     * Create a new job instance.
     */
    public function __construct(array $orderUuids, int $syncLogId)
    {
        $this->orderUuids = $orderUuids;
        $this->syncLogId = $syncLogId;
        $this->onQueue('high');
    }

    /**
     * Get the middleware the job should pass through.
     */
    public function middleware(): array
    {
        return [new \App\Jobs\Middleware\RateLimitLinnworks];
    }

    /**
     * Execute the job.
     */
    public function handle(LinnworksApiService $linnworksService, ImportOrders $importOrders): void
    {
        Log::info('Processing batch order detail job', [
            'order_count' => count($this->orderUuids),
            'sync_log_id' => $this->syncLogId,
        ]);

        if (! $linnworksService->isConfigured()) {
            Log::error('Linnworks API is not configured for detail job');
            $this->incrementSyncCounter('failed', count($this->orderUuids));

            return;
        }

        try {
            // Step 1: Check which orders already exist in database
            $existingOrderIds = Order::whereIn('linnworks_order_id', $this->orderUuids)
                ->pluck('linnworks_order_id')
                ->toArray();

            $newOrderUuids = array_diff($this->orderUuids, $existingOrderIds);

            if (count($existingOrderIds) > 0) {
                Log::info('Some orders already exist, skipping', [
                    'existing_count' => count($existingOrderIds),
                    'new_count' => count($newOrderUuids),
                ]);
                $this->incrementSyncCounter('skipped', count($existingOrderIds));
            }

            if (empty($newOrderUuids)) {
                return;
            }

            // Step 2: Fetch details for new orders from Linnworks
            Log::info('Fetching batch order details from Linnworks', [
                'order_count' => count($newOrderUuids),
            ]);

            $orderDetails = $linnworksService->getOpenOrderDetails($newOrderUuids);

            if ($orderDetails->isEmpty()) {
                Log::warning('No order details returned from Linnworks', [
                    'requested_count' => count($newOrderUuids),
                ]);
                $this->incrementSyncCounter('failed', count($newOrderUuids));

                return;
            }

            /** @var ImportOrdersResult $result */
            $result = $importOrders->handle($orderDetails);

            if ($result->created > 0) {
                $this->incrementSyncCounter('created', $result->created);
            }

            if ($result->updated > 0) {
                $this->incrementSyncCounter('updated', $result->updated);
            }

            if ($result->skipped > 0) {
                $this->incrementSyncCounter('skipped', $result->skipped);
            }

            if ($result->failed > 0) {
                $this->incrementSyncCounter('failed', $result->failed);
            }

            $storedTotal = $result->created + $result->updated + $result->skipped + $result->failed;
            $notReturned = max(0, count($newOrderUuids) - $storedTotal);

            if ($notReturned > 0) {
                Log::warning('Some orders were not returned by API', [
                    'requested' => count($newOrderUuids),
                    'stored_total' => $storedTotal,
                    'missing' => $notReturned,
                ]);
                $this->incrementSyncCounter('failed', $notReturned);
            }

            Log::info('Order detail batch imported', $result->toArray());

        } catch (\Exception $e) {
            Log::error('Failed to process batch order detail', [
                'order_count' => count($this->orderUuids),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $this->incrementSyncCounter('failed', count($this->orderUuids));
        }
    }

    /**
     * Increment counter in the sync log
     */
    protected function incrementSyncCounter(string $type, int $count = 1): void
    {
        try {
            $syncLog = SyncLog::find($this->syncLogId);
            if (! $syncLog) {
                Log::warning('Sync log not found for counter increment', [
                    'sync_log_id' => $this->syncLogId,
                    'counter_type' => $type,
                ]);

                return;
            }

            $field = match ($type) {
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
                'error' => $e->getMessage(),
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
                    ],
                ]),
            ]);

            if ($syncLog->wasChanged('status')) {
                $syncLog->refresh();

                $summary = [
                    'fetched' => $syncLog->total_fetched ?? 0,
                    'created' => $syncLog->total_created ?? 0,
                    'updated' => $syncLog->total_updated ?? 0,
                    'skipped' => $syncLog->total_skipped ?? 0,
                    'failed' => $syncLog->total_failed ?? 0,
                    'initiated_by' => $syncLog->metadata['started_by'] ?? null,
                    'completed_at' => optional($syncLog->completed_at)->toIso8601String(),
                ];

                Livewire::dispatch('open-orders-synced', $summary);

                Log::info('Sync completed by detail job', [
                    'sync_log_id' => $syncLog->id,
                    'total_processed' => $totalProcessed,
                    'total_fetched' => $totalFetched,
                ]);
            }
        }
    }

    /**
     * The job failed to process.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('GetOpenOrderDetailJob failed', [
            'order_count' => count($this->orderUuids),
            'sync_log_id' => $this->syncLogId,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);

        $this->incrementSyncCounter('failed', count($this->orderUuids));
    }
}
