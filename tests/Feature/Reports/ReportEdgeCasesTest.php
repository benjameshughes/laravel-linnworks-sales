<?php

declare(strict_types=1);

use App\Models\Order;
use App\Models\OrderItem;
use App\Reports\Filters\DateRangeFilter;
use App\Reports\TopProductsReport;
use App\Reports\VariationGroupSalesReport;
use Carbon\Carbon;

test('date range filter rejects end date before start date', function () {
    $filter = new DateRangeFilter;

    $value = [
        'start' => '2025-01-10',
        'end' => '2025-01-01', // Before start!
    ];

    expect($filter->validate($value))->toBeFalse();
});

test('date range filter accepts end date after start date', function () {
    $filter = new DateRangeFilter;

    $value = [
        'start' => '2025-01-01',
        'end' => '2025-01-10',
    ];

    expect($filter->validate($value))->toBeTrue();
});

test('date range filter accepts same start and end date', function () {
    $filter = new DateRangeFilter;

    $value = [
        'start' => '2025-01-01',
        'end' => '2025-01-01',
    ];

    expect($filter->validate($value))->toBeTrue();
});

test('reports handle empty date range correctly', function () {
    $report = new TopProductsReport;

    // Create orders outside the date range
    Order::factory()->create([
        'status' => 'processed',
        'received_date' => Carbon::now()->subYears(2),
    ]);

    $filters = [
        'date_range' => [
            'start' => Carbon::now()->subDays(7)->format('Y-m-d'),
            'end' => Carbon::now()->format('Y-m-d'),
        ],
    ];

    $data = $report->preview($filters);

    // Should return empty collection without errors
    expect($data)->toBeInstanceOf(\Illuminate\Support\Collection::class);
});

test('reports handle division by zero in percentage calculations', function () {
    // Create NO orders (empty database scenario)
    Order::query()->delete();
    OrderItem::query()->delete();

    $report = new TopProductsReport;

    $filters = [
        'date_range' => [
            'start' => Carbon::now()->subDays(7)->format('Y-m-d'),
            'end' => Carbon::now()->format('Y-m-d'),
        ],
    ];

    // Should not throw division by zero error
    $data = $report->preview($filters);

    expect($data)->toBeInstanceOf(\Illuminate\Support\Collection::class);
    expect($data)->toBeEmpty();
});

test('variation group sales report handles null parent SKUs correctly', function () {
    $order = Order::factory()->create([
        'status' => 'processed',
        'received_date' => Carbon::now()->subDays(3),
    ]);

    // Create item with NULL parent_sku
    OrderItem::factory()->create([
        'order_id' => $order->id,
        'parent_sku' => null,
        'sku' => 'NO-PARENT-SKU',
        'quantity' => 1,
        'unit_price' => 50.00,
    ]);

    $report = new VariationGroupSalesReport;

    $filters = [
        'date_range' => [
            'start' => Carbon::now()->subDays(7)->format('Y-m-d'),
            'end' => Carbon::now()->format('Y-m-d'),
        ],
    ];

    $data = $report->preview($filters);

    // Item with null parent_sku should be excluded
    expect($data->pluck('parent_sku')->contains('NO-PARENT-SKU'))->toBeFalse();
});

test('reports handle extremely large date ranges', function () {
    $report = new TopProductsReport;

    $filters = [
        'date_range' => [
            'start' => Carbon::now()->subYears(10)->format('Y-m-d'),
            'end' => Carbon::now()->format('Y-m-d'),
        ],
    ];

    // Should execute without timeout
    $data = $report->preview($filters, 10);

    expect($data)->toBeInstanceOf(\Illuminate\Support\Collection::class);
});

test('reports handle special characters in filter values', function () {
    $order = Order::factory()->create([
        'status' => 'processed',
        'received_date' => Carbon::now()->subDays(3),
        'subsource' => "Test's \"Special\" Subsource",
    ]);

    OrderItem::factory()->create([
        'order_id' => $order->id,
        'parent_sku' => 'TEST-PARENT',
        'sku' => "SKU-WITH-'QUOTE",
        'quantity' => 1,
        'unit_price' => 50.00,
    ]);

    $report = new VariationGroupSalesReport;

    $filters = [
        'date_range' => [
            'start' => Carbon::now()->subDays(7)->format('Y-m-d'),
            'end' => Carbon::now()->format('Y-m-d'),
        ],
        'subsources' => ["Test's \"Special\" Subsource"],
    ];

    // Should not cause SQL injection or errors
    $data = $report->preview($filters);

    expect($data)->toBeInstanceOf(\Illuminate\Support\Collection::class);
});

test('reports handle Unknown subsource filtering', function () {
    $order = Order::factory()->create([
        'status' => 'processed',
        'received_date' => Carbon::now()->subDays(3),
        'subsource' => null, // Null subsource
    ]);

    OrderItem::factory()->create([
        'order_id' => $order->id,
        'parent_sku' => 'TEST-PARENT',
        'sku' => 'TEST-SKU',
        'quantity' => 1,
        'unit_price' => 50.00,
    ]);

    $report = new VariationGroupSalesReport;

    $filters = [
        'date_range' => [
            'start' => Carbon::now()->subDays(7)->format('Y-m-d'),
            'end' => Carbon::now()->format('Y-m-d'),
        ],
        'subsources' => ['Unknown'],
    ];

    $data = $report->preview($filters);

    // Should find the order with null subsource
    expect($data)->not->toBeEmpty();
});

test('reports handle empty string subsource as Unknown', function () {
    $order = Order::factory()->create([
        'status' => 'processed',
        'received_date' => Carbon::now()->subDays(3),
        'subsource' => '', // Empty string subsource
    ]);

    OrderItem::factory()->create([
        'order_id' => $order->id,
        'parent_sku' => 'TEST-PARENT',
        'sku' => 'TEST-SKU',
        'quantity' => 1,
        'unit_price' => 50.00,
    ]);

    $report = new VariationGroupSalesReport;

    $filters = [
        'date_range' => [
            'start' => Carbon::now()->subDays(7)->format('Y-m-d'),
            'end' => Carbon::now()->format('Y-m-d'),
        ],
        'subsources' => ['Unknown'],
    ];

    $data = $report->preview($filters);

    // Should find the order with empty subsource
    expect($data)->not->toBeEmpty();
});

test('count method works with calculated columns in select', function () {
    Order::factory()->count(5)->create([
        'status' => 'processed',
        'received_date' => Carbon::now()->subDays(3),
    ])->each(function ($order) {
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'sku' => 'TEST-SKU',
            'quantity' => 1,
            'unit_price' => 50.00,
        ]);
    });

    $report = new TopProductsReport;

    $filters = [
        'date_range' => [
            'start' => Carbon::now()->subDays(7)->format('Y-m-d'),
            'end' => Carbon::now()->format('Y-m-d'),
        ],
    ];

    // Should not throw SQL error about ORDER BY with calculated columns
    $count = $report->count($filters);

    expect($count)->toBeGreaterThanOrEqual(0);
});
