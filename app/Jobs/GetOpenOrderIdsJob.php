<?php

namespace App\Jobs;

use App\Events\OrdersSynced;
use App\Jobs\GetOpenOrderDetailJob;
use App\Models\Order;
use App\Models\SyncLog;
use App\Services\LinnworksApiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GetOpenOrderIdsJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $uniqueFor = 3600; // 1 hour

    protected ?string $startedBy;
    protected SyncLog $syncLog;

    /**
     * Create a new job instance.
     */
    public function __construct(?string $startedBy = null)
    {
        $this->startedBy = $startedBy ?? 'system';
        $this->onQueue('high');
    }

    /**
     * The unique ID of the job.
     */
    public function uniqueId(): string
    {
        return 'get-open-order-ids';
    }

    /**
     * Execute the job.
     */
    public function handle(LinnworksApiService $linnworksService): void
    {
        // Start sync log
        $this->syncLog = SyncLog::startSync(SyncLog::TYPE_OPEN_ORDERS, [
            'started_by' => $this->startedBy,
            'job_type' => 'master',
        ]);

        Log::info('Starting master job to get open order UUIDs', [
            'started_by' => $this->startedBy
        ]);

        if (!$linnworksService->isConfigured()) {
            Log::error('Linnworks API is not configured');
            $this->syncLog->fail('Linnworks API not configured');
            throw new \Exception('Linnworks API is not configured. Please check your credentials.');
        }

        try {
            // Get all open order UUIDs from Linnworks
            Log::info('Fetching open order UUIDs from Linnworks...');
            $openOrderIds = $linnworksService->getAllOpenOrderIds();
            
            Log::info("Found {$openOrderIds->count()} open order UUIDs");
            
            if ($openOrderIds->isEmpty()) {
                Log::warning('No open orders found.');
                $this->syncLog->complete(0);
                return;
            }

            $this->syncLog->update([
                'total_fetched' => $openOrderIds->count(),
                'metadata' => array_merge($this->syncLog->metadata ?? [], [
                    'total_uuids_found' => $openOrderIds->count(),
                    'sample_uuids' => $openOrderIds->take(3)->toArray(),
                ])
            ]);

            // Mark existing orders as open (quick database operation)
            $existingOrderIds = Order::whereIn('linnworks_order_id', $openOrderIds->toArray())
                ->pluck('linnworks_order_id')
                ->toArray();
            
            if (!empty($existingOrderIds)) {
                Order::whereIn('linnworks_order_id', $existingOrderIds)
                    ->update([
                        'is_open' => true,
                        'last_synced_at' => now(),
                    ]);
                Log::info('Marked ' . count($existingOrderIds) . ' existing orders as open');
            }

            // Mark orders not in the current sync as closed
            $this->markMissingOrdersAsClosed($openOrderIds);

            // Only dispatch jobs for new orders (not existing ones)
            Log::info('DEBUG: Before filtering', [
                'total_orders' => $openOrderIds->count(),
                'existing_orders' => count($existingOrderIds)
            ]);
            
            $newOrderIds = $openOrderIds->diff(collect($existingOrderIds));
            
            Log::info('DEBUG: After filtering', [
                'new_orders_count' => $newOrderIds->count()
            ]);
            
            if ($newOrderIds->isEmpty()) {
                Log::info('No new orders to process - all orders already exist in database');
                $this->syncLog->complete(0);
                return;
            }

            Log::info('Processing new orders only', [
                'total_orders_from_linnworks' => $openOrderIds->count(),
                'existing_orders_skipped' => count($existingOrderIds),
                'new_orders_to_process' => $newOrderIds->count()
            ]);

            // Dispatch jobs in batches to avoid API rate limits
            $jobsDispatched = 0;
            $batchSize = 100; // Process 100 orders per job
            
            foreach ($newOrderIds->chunk($batchSize) as $chunkIndex => $chunk) {
                // Delay each batch by 1 minute after the first one to avoid rate limits
                $delay = $chunkIndex * 60; // 0, 60, 120, 180 seconds delay
                
                GetOpenOrderDetailJob::dispatch($chunk->values()->toArray(), $this->syncLog->id)
                    ->delay(now()->addSeconds($delay));
                
                $jobsDispatched++;
            }

            Log::info('Dispatched individual order detail jobs', [
                'total_jobs_dispatched' => $jobsDispatched,
                'existing_orders_updated' => count($existingOrderIds),
            ]);

            // Update sync log with dispatch info
            $this->syncLog->update([
                'metadata' => array_merge($this->syncLog->metadata ?? [], [
                    'jobs_dispatched' => $jobsDispatched,
                    'existing_orders_marked_open' => count($existingOrderIds),
                    'master_job_completed_at' => now()->toDateTimeString(),
                ])
            ]);

            // Don't complete the sync log yet - let the detail jobs handle completion
            Log::info('Master job completed successfully', [
                'total_uuids' => $openOrderIds->count(),
                'jobs_dispatched' => $jobsDispatched,
            ]);

            // Dispatch event to warm metrics cache
            OrdersSynced::dispatch(
                ordersProcessed: $openOrderIds->count(),
                syncType: 'open_orders'
            );

        } catch (\Exception $e) {
            Log::error('Master job failed', ['error' => $e->getMessage()]);
            $this->syncLog->fail($e->getMessage());
            throw $e;
        }
    }

    /**
     * Mark orders that weren't in the sync response as potentially closed
     */
    protected function markMissingOrdersAsClosed($currentOpenOrderIds): void
    {
        $closedCount = Order::where('is_open', true)
            ->whereNotIn('linnworks_order_id', $currentOpenOrderIds->toArray())
            ->where('last_synced_at', '<', now()->subMinutes(30))
            ->update([
                'is_open' => false,
                'sync_metadata' => \DB::raw("JSON_SET(COALESCE(sync_metadata, '{}'), '$.marked_closed_at', '" . now()->toDateTimeString() . "')"),
            ]);

        if ($closedCount > 0) {
            Log::info("Marked {$closedCount} missing orders as closed");
        }
    }

    /**
     * The job failed to process.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('GetOpenOrderIdsJob failed', [
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}