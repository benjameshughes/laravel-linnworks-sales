<?php

use App\Models\Order;
use App\Services\Metrics\SalesMetrics;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    Cache::flush();
    Carbon::setTestNow(now());
});

afterEach(function () {
    Carbon::setTestNow();
});

it('calculates revenue from order items when totals are missing', function () {
    $order = Order::factory()->create([
        'total_charge' => 0,
        'total_paid' => 0,
        'is_processed' => false,
        'received_date' => now()->subDay(),
        'items' => [[
            'item_id' => 'fallback-1',
            'sku' => 'SKU-001',
            'quantity' => 2,
            'unit_cost' => 0,
            'price_per_unit' => 29.99,
            'line_total' => 0,
        ]],
    ]);

    $metrics = new SalesMetrics(collect([$order->fresh()]));

    expect(round($metrics->totalRevenue(), 2))->toBe(59.98);
    expect(round($metrics->averageOrderValue(), 2))->toBe(59.98);
    expect(round($metrics->openOrdersRevenue(), 2))->toBe(59.98);
});

it('builds daily sales data with derived revenues', function () {
    $orderOpen = Order::factory()->create([
        'total_charge' => 0,
        'total_paid' => 0,
        'is_processed' => false,
        'is_open' => true,
        'received_date' => now()->subDay(),
        'items' => [[
            'item_id' => 'open-1',
            'sku' => 'SKU-OPEN',
            'quantity' => 3,
            'unit_cost' => 0,
            'price_per_unit' => 10,
            'line_total' => 0,
        ]],
    ]);
    $orderProcessed = Order::factory()->create([
        'total_charge' => 0,
        'total_paid' => 0,
        'is_processed' => true,
        'is_open' => false,
        'status' => 'processed',
        'received_date' => now()->subDay(),
        'items' => [[
            'item_id' => 'processed-1',
            'sku' => 'SKU-PROC',
            'quantity' => 1,
            'unit_cost' => 0,
            'price_per_unit' => 15,
            'line_total' => 0,
        ]],
    ]);
    $metrics = new SalesMetrics(Order::whereKey([$orderOpen->id, $orderProcessed->id])->get());

    $daily = $metrics->dailySalesData('7');
    $targetDate = now()->subDay()->format('M j');
    $dayBreakdown = $daily->firstWhere(fn ($day) => $day->get('date') === $targetDate);

    expect($dayBreakdown)->not->toBeNull();
    expect(round($dayBreakdown->get('revenue'), 2))->toBe(45.0);
    expect($dayBreakdown->get('orders'))->toBe(2);
    expect(round($dayBreakdown->get('avg_order_value'), 2))->toBe(22.5);
    expect(round($dayBreakdown->get('open_revenue'), 2))->toBe(30.0);
    expect(round($dayBreakdown->get('processed_revenue'), 2))->toBe(15.0);
});

it('ranks top products using derived revenue totals', function () {
    $order = Order::factory()->create([
        'total_charge' => 0,
        'total_paid' => 0,
        'received_date' => now()->subDay(),
        'items' => [[
            'item_id' => 'product-1',
            'sku' => 'SKU-TOP',
            'quantity' => 3,
            'unit_cost' => 0,
            'price_per_unit' => 20,
            'line_total' => 0,
        ]],
    ]);

    $metrics = new SalesMetrics(Order::whereKey([$order->id])->get());
    $topProduct = $metrics->topProducts()->first();

    expect($topProduct)->not->toBeNull();
    expect($topProduct->get('sku'))->toBe('SKU-TOP');
    expect(round($topProduct->get('revenue'), 2))->toBe(60.0);
    expect($topProduct->get('quantity'))->toBe(3);
});
