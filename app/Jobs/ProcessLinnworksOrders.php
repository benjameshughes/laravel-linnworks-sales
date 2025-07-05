<?php

namespace App\Jobs;

use App\Models\Order;
use App\DataTransferObjects\LinnworksOrder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
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
        $this->onQueue('linnworks-processing');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('Starting processing job for orders', [
            'orders_count' => count($this->ordersData),
            'force_update' => $this->forceUpdate
        ]);

        $processed = 0;
        $created = 0;
        $updated = 0;
        $skipped = 0;
        $errors = 0;

        try {
            DB::beginTransaction();

            foreach ($this->ordersData as $orderData) {
                try {
                    // Convert array back to LinnworksOrder DTO
                    $linnworksOrder = LinnworksOrder::fromArray($orderData);

                    if (!$linnworksOrder->orderId) {
                        Log::warning('Skipping order without ID', ['data' => $orderData]);
                        $skipped++;
                        continue;
                    }

                    // Check if order already exists
                    $existingOrder = Order::where('linnworks_order_id', $linnworksOrder->orderId)->first();

                    if ($existingOrder && !$this->forceUpdate) {
                        // Update existing order only if there are changes
                        $orderModel = Order::fromLinnworksOrder($linnworksOrder);
                        $existingOrder->fill($orderModel->getAttributes());
                        
                        if ($existingOrder->isDirty()) {
                            $existingOrder->save();
                            $updated++;
                            Log::debug('Updated existing order', [
                                'order_id' => $linnworksOrder->orderId,
                                'changes' => $existingOrder->getChanges()
                            ]);
                        } else {
                            $skipped++;
                        }
                    } else {
                        // Create new order or force update
                        $orderModel = Order::fromLinnworksOrder($linnworksOrder);
                        
                        if ($existingOrder && $this->forceUpdate) {
                            // Force update existing order
                            $existingOrder->fill($orderModel->getAttributes());
                            $existingOrder->save();
                            $updated++;
                            Log::debug('Force updated order', ['order_id' => $linnworksOrder->orderId]);
                        } else {
                            // Create new order
                            $orderModel->save();
                            $created++;
                            Log::debug('Created new order', ['order_id' => $linnworksOrder->orderId]);
                        }
                    }

                    $processed++;

                } catch (\Exception $e) {
                    $errors++;
                    Log::error('Failed to process individual order', [
                        'order_data' => $orderData,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                    
                    // Continue processing other orders instead of failing the entire job
                    continue;
                }
            }

            DB::commit();

            Log::info('Completed processing orders batch', [
                'processed' => $processed,
                'created' => $created,
                'updated' => $updated,
                'skipped' => $skipped,
                'errors' => $errors
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to process orders batch', [
                'orders_count' => count($this->ordersData),
                'processed' => $processed,
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
