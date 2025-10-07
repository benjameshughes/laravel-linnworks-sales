<?php

use App\Actions\Orders\MarkOrderAsProcessed;
use App\Models\Order;
use Illuminate\Support\Collection;

beforeEach(function () {
    // Create test orders in the database
    $this->testOrders = collect([
        Order::factory()->create([
            'linnworks_order_id' => 'test-uuid-1',
            'is_processed' => false,
            'order_number' => '12345',
        ]),
        Order::factory()->create([
            'linnworks_order_id' => 'test-uuid-2', 
            'is_processed' => true,
            'order_number' => '12346',
        ]),
        Order::factory()->create([
            'linnworks_order_id' => 'test-uuid-3',
            'is_processed' => false,
            'order_number' => '12347',
        ]),
    ]);
});

test('can update orders processed status from collection data', function () {
    $processedOrdersData = collect([
        ['order_id' => 'test-uuid-1', 'is_processed' => true],
        ['order_id' => 'test-uuid-2', 'is_processed' => false], 
        ['order_id' => 'test-uuid-3', 'is_processed' => true],
    ]);

    $action = new MarkOrderAsProcessed();
    $result = $action->handle($processedOrdersData);

    expect($result)->toBeTrue();

    // Check database updates
    $order1 = Order::where('linnworks_order_id', 'test-uuid-1')->first();
    $order2 = Order::where('linnworks_order_id', 'test-uuid-2')->first();  
    $order3 = Order::where('linnworks_order_id', 'test-uuid-3')->first();

    expect($order1->is_processed)->toBeTrue();
    expect($order2->is_processed)->toBeFalse();
    expect($order3->is_processed)->toBeTrue();
});

test('handles empty collection gracefully', function () {
    $action = new MarkOrderAsProcessed();
    $result = $action->handle(collect());

    expect($result)->toBeTrue();
});

test('skips orders not found in database', function () {
    $processedOrdersData = collect([
        ['order_id' => 'non-existent-uuid', 'is_processed' => true],
        ['order_id' => 'test-uuid-1', 'is_processed' => true],
    ]);

    $action = new MarkOrderAsProcessed();
    $result = $action->handle($processedOrdersData);

    expect($result)->toBeTrue();

    $order1 = Order::where('linnworks_order_id', 'test-uuid-1')->first();
    expect($order1->is_processed)->toBeTrue();
});

test('handles missing order_id in data', function () {
    $processedOrdersData = collect([
        ['is_processed' => true], // Missing order_id
        ['order_id' => 'test-uuid-1', 'is_processed' => true],
    ]);

    $action = new MarkOrderAsProcessed();
    $result = $action->handle($processedOrdersData);

    expect($result)->toBeFalse(); // Should return false due to error

    $order1 = Order::where('linnworks_order_id', 'test-uuid-1')->first();
    expect($order1->is_processed)->toBeTrue();
});

test('only updates when status changes', function () {
    // Order already processed, API says still processed
    $processedOrdersData = collect([
        ['order_id' => 'test-uuid-2', 'is_processed' => true], // Already true in DB
    ]);

    $originalUpdatedAt = $this->testOrders[1]->updated_at;

    $action = new MarkOrderAsProcessed();
    $result = $action->handle($processedOrdersData);

    expect($result)->toBeTrue();

    $order = Order::where('linnworks_order_id', 'test-uuid-2')->first();
    expect($order->is_processed)->toBeTrue();
    // Updated timestamp should not change since no update was needed
    expect($order->updated_at->eq($originalUpdatedAt))->toBeTrue();
});

test('correctly processes boolean values', function () {
    $processedOrdersData = collect([
        ['order_id' => 'test-uuid-1', 'is_processed' => 1], // Truthy value
        ['order_id' => 'test-uuid-2', 'is_processed' => 0], // Falsy value
        ['order_id' => 'test-uuid-3', 'is_processed' => false], // Boolean false
    ]);

    $action = new MarkOrderAsProcessed();
    $result = $action->handle($processedOrdersData);

    expect($result)->toBeTrue();

    $order1 = Order::where('linnworks_order_id', 'test-uuid-1')->first();
    $order2 = Order::where('linnworks_order_id', 'test-uuid-2')->first();
    $order3 = Order::where('linnworks_order_id', 'test-uuid-3')->first();

    expect($order1->is_processed)->toBeTrue();
    expect($order2->is_processed)->toBeFalse();
    expect($order3->is_processed)->toBeFalse();
});