<?php

use App\Models\Order;
use App\Models\OrderItem;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Order Model', function () {
    
    it('can mark an open order as processed', function () {
        $order = Order::factory()->create([
            'is_open' => true,
            'status' => 'pending',
            'processed_date' => null
        ]);

        $processedDate = Carbon::parse('2025-07-02 14:30:00');
        $result = $order->markAsProcessed($processedDate);

        expect($result)->toBeTrue();
        
        $order->refresh();
        expect($order->is_open)->toBeFalse();
        expect($order->status)->toBe('processed');
        expect($order->processed_date->toDateTimeString())->toBe($processedDate->toDateTimeString());
        expect($order->sync_status)->toBe('synced');
        expect($order->last_synced_at)->not->toBeNull();
    });

    it('can mark an order as processed without specifying date', function () {
        $order = Order::factory()->create([
            'is_open' => true,
            'status' => 'pending'
        ]);

        $beforeTime = now();
        $result = $order->markAsProcessed();
        $afterTime = now();

        expect($result)->toBeTrue();
        
        $order->refresh();
        expect($order->is_open)->toBeFalse();
        expect($order->status)->toBe('processed');
        expect($order->processed_date)->toBeGreaterThanOrEqual($beforeTime);
        expect($order->processed_date)->toBeLessThanOrEqual($afterTime);
    });

    it('can match processed order data by order_id', function () {
        $order = Order::factory()->create([
            'order_id' => 'test-order-123'
        ]);

        $processedOrderData = [
            'order_id' => 'test-order-123',
            'order_number' => 99999,
        ];

        expect($order->matchesProcessedOrder($processedOrderData))->toBeTrue();
    });

    it('can match processed order data by linnworks_order_id', function () {
        $order = Order::factory()->create([
            'linnworks_order_id' => 'linnworks-uuid-456'
        ]);

        $processedOrderData = [
            'order_id' => 'linnworks-uuid-456',
            'order_number' => 88888,
        ];

        expect($order->matchesProcessedOrder($processedOrderData))->toBeTrue();
    });

    it('can match processed order data by order_number', function () {
        $order = Order::factory()->create([
            'order_number' => 12345
        ]);

        $processedOrderData = [
            'order_id' => 'different-id',
            'order_number' => 12345,
        ];

        expect($order->matchesProcessedOrder($processedOrderData))->toBeTrue();
    });

    it('does not match when no identifiers match', function () {
        $order = Order::factory()->create([
            'order_id' => 'test-order-123',
            'linnworks_order_id' => 'linnworks-uuid-456',
            'order_number' => 12345
        ]);

        $processedOrderData = [
            'order_id' => 'different-id',
            'order_number' => 99999,
        ];

        expect($order->matchesProcessedOrder($processedOrderData))->toBeFalse();
    });

    it('can filter open orders using scope', function () {
        // Create mix of open and processed orders
        Order::factory()->create(['is_open' => true, 'status' => 'pending']);
        Order::factory()->create(['is_open' => true, 'status' => 'pending']);
        Order::factory()->create(['is_open' => false, 'status' => 'processed']);

        $openOrders = Order::open()->get();
        
        expect($openOrders)->toHaveCount(2);
        expect($openOrders->every(fn($order) => $order->is_open))->toBeTrue();
    });

    it('can filter processed orders using scope', function () {
        // Create mix of open and processed orders
        Order::factory()->create(['is_open' => true, 'status' => 'pending']);
        Order::factory()->create(['is_open' => false, 'status' => 'processed']);
        Order::factory()->create(['is_open' => false, 'status' => 'processed']);

        $processedOrders = Order::processed()->get();
        
        expect($processedOrders)->toHaveCount(2);
        expect($processedOrders->every(fn($order) => !$order->is_open && $order->status === 'processed'))->toBeTrue();
    });

    it('maintains relationship with order items', function () {
        $order = Order::factory()->create();
        $orderItem = OrderItem::factory()->create(['order_id' => $order->id]);

        expect($order->orderItems)->toHaveCount(1);
        expect($order->orderItems->first()->id)->toBe($orderItem->id);
    });

    it('can transition from open to processed while preserving items', function () {
        $order = Order::factory()->create(['is_open' => true]);
        $orderItem = OrderItem::factory()->create(['order_id' => $order->id]);

        $order->markAsProcessed();

        $order->refresh();
        expect($order->is_open)->toBeFalse();
        expect($order->status)->toBe('processed');
        expect($order->orderItems)->toHaveCount(1);
        expect($order->orderItems->first()->id)->toBe($orderItem->id);
    });
});