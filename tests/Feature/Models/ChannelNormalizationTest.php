<?php

use App\DataTransferObjects\LinnworksOrder;
use App\DataTransferObjects\LinnworksOrderItem;
use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('channel name is normalized when creating order from Linnworks DTO', function () {
    $linnworksOrder = new LinnworksOrder(
        orderId: 'test-order-123',
        orderNumber: 123,
        orderSource: 'AMAZON UK',
        subsource: 'Amazon FBA',
        receivedDate: Carbon::now(),
        processedDate: null,
        currency: 'GBP',
        totalCharge: 100.00,
        postageCost: 5.00,
        tax: 20.00,
        profitMargin: 10.00,
        orderStatus: 0,
        locationId: 'LOC-123',
        items: collect([
            new LinnworksOrderItem(
                itemId: 'item-1',
                sku: 'SKU-123',
                quantity: 1,
                unitCost: 50.00,
                pricePerUnit: 100.00,
                lineTotal: 100.00,
                itemTitle: 'Test Product',
                categoryName: 'Test Category'
            )
        ])
    );

    $order = Order::fromLinnworksOrder($linnworksOrder);
    $order->save();

    expect($order->channel_name)->toBe('amazon_uk')
        ->and($order->sub_source)->toBe('amazon_fba')
        ->and($order->source)->toBe('AMAZON UK'); // Original preserved in 'source'
});

test('channel name normalization handles various formats', function () {
    $testCases = [
        ['AMAZON', 'amazon'],
        ['EBAY', 'ebay'],
        ['Amazon UK', 'amazon_uk'],
        ['Amazon FBA', 'amazon_fba'],
        ['Blinds Outlet', 'blinds_outlet'],
        ['The Range', 'the_range'],
        ['Not On The High Street', 'not_on_the_high_street'],
    ];

    $orderNum = 1000;
    foreach ($testCases as [$input, $expected]) {
        $linnworksOrder = new LinnworksOrder(
            orderId: "test-{$orderNum}",
            orderNumber: $orderNum++,
            orderSource: $input,
            subsource: null,
            receivedDate: Carbon::now(),
            processedDate: null,
            currency: 'GBP',
            totalCharge: 100.00,
            postageCost: 5.00,
            tax: 20.00,
            profitMargin: 10.00,
            orderStatus: 0,
            locationId: 'LOC-123',
            items: collect()
        );

        $order = Order::fromLinnworksOrder($linnworksOrder);

        expect($order->channel_name)->toBe($expected, "Failed for input: {$input}");
    }
});

test('subsource is normalized independently of channel', function () {
    $linnworksOrder = new LinnworksOrder(
        orderId: 'test-order-456',
        orderNumber: 456,
        orderSource: 'AMAZON',
        subsource: 'Prime Now UK',
        receivedDate: Carbon::now(),
        processedDate: null,
        currency: 'GBP',
        totalCharge: 100.00,
        postageCost: 5.00,
        tax: 20.00,
        profitMargin: 10.00,
        orderStatus: 0,
        locationId: 'LOC-123',
        items: collect()
    );

    $order = Order::fromLinnworksOrder($linnworksOrder);
    $order->save();

    expect($order->channel_name)->toBe('amazon')
        ->and($order->sub_source)->toBe('prime_now_uk');
});

test('null subsource is handled correctly', function () {
    $linnworksOrder = new LinnworksOrder(
        orderId: 'test-order-789',
        orderNumber: 789,
        orderSource: 'EBAY',
        subsource: null,
        receivedDate: Carbon::now(),
        processedDate: null,
        currency: 'GBP',
        totalCharge: 100.00,
        postageCost: 5.00,
        tax: 20.00,
        profitMargin: 10.00,
        orderStatus: 0,
        locationId: 'LOC-123',
        items: collect()
    );

    $order = Order::fromLinnworksOrder($linnworksOrder);
    $order->save();

    expect($order->channel_name)->toBe('ebay')
        ->and($order->sub_source)->toBeNull();
});
