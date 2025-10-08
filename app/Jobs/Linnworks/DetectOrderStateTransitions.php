<?php

declare(strict_types=1);

namespace App\Jobs\Linnworks;

use App\Models\Order;
use App\Services\Linnworks\Orders\ProcessedOrdersService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Detect orders that have transitioned from open to processed
 */
class DetectOrderStateTransitions implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 600;

    public function __construct(
        private readonly int $userId,
        private readonly int $daysBack = 7
    ) {}

    /**
     * Execute the job
     */
    public function handle(ProcessedOrdersService $processedOrdersService): void
    {
        Log::info('Starting order state transition detection', [
            'user_id' => $this->userId,
            'days_back' => $this->daysBack,
        ]);

        // Get open orders from database
        $openOrders = Order::query()
            ->where('status', 'pending')
            ->where('received_date', '>=', now()->subDays($this->daysBack))
            ->get(['id', 'linnworks_order_id', 'order_number', 'received_date']);

        if ($openOrders->isEmpty()) {
            Log::info('No open orders to check for transitions');
            return;
        }

        Log::info('Checking orders for state transitions', [
            'order_count' => $openOrders->count(),
        ]);

        $transitionedCount = 0;
        $failedChecks = 0;

        // Check each order against processed orders API
        foreach ($openOrders->chunk(50) as $chunk) {
            $orderIds = $chunk->pluck('linnworks_order_id')->filter()->toArray();

            if (empty($orderIds)) {
                continue;
            }

            try {
                // Query processed orders API for these IDs
                $processedOrders = $processedOrdersService->getProcessedOrdersByIds(
                    userId: $this->userId,
                    orderIds: $orderIds
                );

                if ($processedOrders->isEmpty()) {
                    continue;
                }

                // Update orders that are now processed
                foreach ($processedOrders as $processedOrder) {
                    $orderId = $processedOrder['OrderId'] ?? $processedOrder['pkOrderID'] ?? null;

                    if (!$orderId) {
                        continue;
                    }

                    $updated = Order::query()
                        ->where(function ($query) use ($orderId) {
                            $query->where('linnworks_order_id', $orderId)
                                ->orWhere('order_id', $orderId);
                        })
                        ->where('status', 'pending')
                        ->update([
                            'status' => 'processed',
                            'processed_date' => $processedOrder['ProcessedDateTime'] ?? now(),
                            'updated_at' => now(),
                        ]);

                    if ($updated > 0) {
                        $transitionedCount++;
                        Log::info('Order transitioned to processed', [
                            'order_id' => $orderId,
                        ]);
                    }
                }
            } catch (\Throwable $e) {
                $failedChecks++;
                Log::warning('Failed to check order state transitions for chunk', [
                    'order_count' => count($orderIds),
                    'error' => $e->getMessage(),
                ]);
            }

            // Small delay between chunks to respect rate limits
            if ($failedChecks === 0) {
                usleep(200000); // 200ms
            }
        }

        Log::info('Order state transition detection completed', [
            'checked' => $openOrders->count(),
            'transitioned' => $transitionedCount,
            'failed_checks' => $failedChecks,
        ]);
    }
}

