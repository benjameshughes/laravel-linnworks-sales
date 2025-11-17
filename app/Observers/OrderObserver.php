<?php

declare(strict_types=1);

namespace App\Observers;

use App\Events\OrdersSynced;
use App\Models\Order;
use Illuminate\Support\Facades\Log;

/**
 * Observer for Order model changes
 *
 * Automatically triggers cache warming when orders are created, updated, or deleted.
 * This ensures dashboard metrics stay fresh without manual event() calls.
 *
 * IMPORTANT: This observer only fires for Eloquent operations (Model::create(), $model->save(), etc.)
 * It does NOT fire for bulk imports using DB::table(), which is intentional to avoid
 * triggering cache warming on every single order during imports.
 *
 * For bulk operations, use the manual OrdersSynced event after completion.
 */
final class OrderObserver
{
    /**
     * Debounce cache warming to avoid firing on every single order change
     * If multiple orders change within this window, only warm cache once
     */
    private const DEBOUNCE_SECONDS = 30;

    /**
     * Handle the Order "created" event.
     *
     * Fires when a single order is created via Eloquent (e.g., API webhook, manual entry)
     */
    public function created(Order $order): void
    {
        $this->queueCacheWarming('order_created');

        Log::info('[OrderObserver] Order created - cache warming queued', [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
        ]);
    }

    /**
     * Handle the Order "updated" event.
     *
     * Fires when a single order is updated via Eloquent (e.g., status change, manual edit)
     */
    public function updated(Order $order): void
    {
        // Only warm cache if meaningful fields changed
        if ($this->shouldWarmCache($order)) {
            $this->queueCacheWarming('order_updated');

            Log::info('[OrderObserver] Order updated - cache warming queued', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'changed' => array_keys($order->getDirty()),
            ]);
        }
    }

    /**
     * Handle the Order "deleted" event.
     *
     * Fires when an order is soft-deleted
     */
    public function deleted(Order $order): void
    {
        $this->queueCacheWarming('order_deleted');

        Log::info('[OrderObserver] Order deleted - cache warming queued', [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
        ]);
    }

    /**
     * Check if changed fields warrant cache warming
     *
     * Only warm cache if revenue, status, or channel fields changed
     */
    private function shouldWarmCache(Order $order): bool
    {
        $significantFields = [
            'total_charge',
            'total_paid',
            'is_paid',
            'is_open',
            'is_processed',
            'channel_name',
            'received_date',
            'processed_date',
        ];

        return collect($significantFields)
            ->some(fn (string $field) => $order->wasChanged($field));
    }

    /**
     * Queue cache warming with debouncing
     *
     * Uses Laravel's event system to trigger cache warming
     * Debouncing prevents warming cache on every single order change
     */
    private function queueCacheWarming(string $reason): void
    {
        // Dispatch OrdersSynced event with 'observer' sync type
        // The WarmMetricsCache listener will handle this with a delay
        event(new OrdersSynced(
            ordersProcessed: 1,
            syncType: 'observer_'.$reason,
        ));
    }
}
