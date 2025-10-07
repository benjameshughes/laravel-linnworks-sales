<?php

declare(strict_types=1);

namespace App\Actions\Orders;

use App\Models\Order;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Action class to update orders' processed status based on Linnworks API data.
 * This will take a collection of order data from Linnworks and update the corresponding
 * orders in the database with their processed status.
 */
class MarkOrderAsProcessed
{
    /**
     * Update orders' processed status in the database
     * 
     * @param Collection $processedOrdersData Collection of orders with order_id and is_processed status
     * @return bool Success status
     */
    public function handle(Collection $processedOrdersData): bool
    {
        if ($processedOrdersData->isEmpty()) {
            Log::info('No processed orders data to update');
            return true;
        }

        try {
            $updatedCount = 0;
            $skippedCount = 0;
            $errorCount = 0;

            Log::info('Starting to update orders processed status', [
                'total_orders' => $processedOrdersData->count()
            ]);

            foreach ($processedOrdersData as $orderData) {
                $orderId = $orderData['order_id'] ?? null;
                $isProcessed = $orderData['is_processed'] ?? false;

                if (!$orderId) {
                    Log::warning('Missing order_id in processed orders data', [
                        'order_data' => $orderData
                    ]);
                    $errorCount++;
                    continue;
                }

                // Find the order in our database by linnworks_order_id
                $order = Order::where('linnworks_order_id', $orderId)->first();

                if (!$order) {
                    Log::debug('Order not found in database', [
                        'linnworks_order_id' => $orderId
                    ]);
                    $skippedCount++;
                    continue;
                }

                // Only update if the processed status has changed
                if ($order->is_processed !== $isProcessed) {
                    $oldStatus = $order->is_processed;
                    $order->is_processed = $isProcessed;
                    $order->is_open = !$isProcessed;
                    if ($isProcessed) {
                        $order->status = 'processed';
                        $order->processed_date = $order->processed_date ?? now();
                    }
                    
                    if ($order->save()) {
                        Log::debug('Updated order processed status', [
                            'order_id' => $order->id,
                            'linnworks_order_id' => $orderId,
                            'old_status' => $oldStatus,
                            'new_status' => $isProcessed
                        ]);
                        $updatedCount++;
                    } else {
                        Log::error('Failed to save order processed status', [
                            'order_id' => $order->id,
                            'linnworks_order_id' => $orderId
                        ]);
                        $errorCount++;
                    }
                } else {
                    // Status is already correct, no update needed
                    $skippedCount++;
                }
            }

            Log::info('Completed updating orders processed status', [
                'total_processed' => $processedOrdersData->count(),
                'updated' => $updatedCount,
                'skipped' => $skippedCount,
                'errors' => $errorCount
            ]);

            // Return true if no errors occurred
            return $errorCount === 0;

        } catch (\Exception $e) {
            Log::error('Error updating orders processed status', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }
}
