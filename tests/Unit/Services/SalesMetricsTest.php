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

it('builds daily sales data for a custom inclusive range', function () {
    $start = now()->subDays(2)->startOfDay();
    $end = now()->startOfDay();

    $firstOrder = Order::factory()->create([
        'total_charge' => 120,
        'received_date' => $start,
        'is_processed' => true,
    ]);

    $lastOrder = Order::factory()->create([
        'total_charge' => 80,
        'received_date' => $end,
        'is_processed' => false,
    ]);

    $metrics = new SalesMetrics(Order::whereKey([$firstOrder->id, $lastOrder->id])->get());

    $daily = $metrics->dailySalesData('custom', $start->toDateString(), $end->toDateString());

    expect($daily)->toHaveCount(3);

    $dates = $daily->pluck('iso_date')->all();
    expect($dates)->toBe([
        $start->toDateString(),
        $start->copy()->addDay()->toDateString(),
        $end->toDateString(),
    ]);

    $firstDay = $daily->first();
    $middleDay = $daily->get(1);
    $lastDay = $daily->last();

    expect((float) $firstDay->get('revenue'))->toBe(120.0);
    expect((float) $firstDay->get('processed_revenue'))->toBe(120.0);
    expect((float) $firstDay->get('open_revenue'))->toBe(0.0);

    expect((float) $middleDay->get('revenue'))->toBe(0.0);
    expect($middleDay->get('orders'))->toBe(0);

    expect((float) $lastDay->get('revenue'))->toBe(80.0);
    expect((float) $lastDay->get('open_revenue'))->toBe(80.0);
    expect((float) $lastDay->get('processed_revenue'))->toBe(0.0);
});

it('builds line chart data with normalised keys for custom ranges', function () {
    $start = now()->subDays(2)->startOfDay();
    $end = now()->startOfDay();

    $firstOrder = Order::factory()->create([
        'total_charge' => 120,
        'received_date' => $start,
        'is_processed' => true,
    ]);

    $lastOrder = Order::factory()->create([
        'total_charge' => 80,
        'received_date' => $end,
        'is_processed' => false,
    ]);

    $metrics = new SalesMetrics(Order::whereKey([$firstOrder->id, $lastOrder->id])->get());

    $lineChart = $metrics->getLineChartData('custom', $start->toDateString(), $end->toDateString());

    expect(array_values($lineChart['labels']))->toBe([
        $start->format('M j'),
        $start->copy()->addDay()->format('M j'),
        $end->format('M j'),
    ]);

    expect(array_map('floatval', array_values($lineChart['datasets'][0]['data'])))->toBe([120.0, 0.0, 80.0]);
    expect(array_values($lineChart['meta']['iso_dates']))->toBe([
        $start->toDateString(),
        $start->copy()->addDay()->toDateString(),
        $end->toDateString(),
    ]);
});
