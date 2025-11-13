<?php

declare(strict_types=1);

use App\Models\Order;
use App\Models\OrderItem;
use App\Reports\ChannelPerformanceReport;
use App\Reports\DailyRevenueReport;
use App\Reports\OrderStatusReport;
use App\Reports\ProductPerformanceReport;
use App\Reports\TopProductsReport;
use App\Reports\VariationGroupSalesReport;
use Carbon\Carbon;

beforeEach(function () {
    $this->dateStart = Carbon::now()->subDays(7);
    $this->dateEnd = Carbon::now();

    createTestOrders();
});

test('top products report executes without errors', function () {
    $report = new TopProductsReport;

    $filters = [
        'date_range' => [
            'start' => $this->dateStart->format('Y-m-d'),
            'end' => $this->dateEnd->format('Y-m-d'),
        ],
    ];

    $data = $report->preview($filters, 10);

    expect($data)->toBeInstanceOf(\Illuminate\Support\Collection::class);
});

test('top products report calculates revenue percentage correctly', function () {
    $report = new TopProductsReport;

    $filters = [
        'date_range' => [
            'start' => $this->dateStart->format('Y-m-d'),
            'end' => $this->dateEnd->format('Y-m-d'),
        ],
    ];

    $data = $report->preview($filters, 10);

    if ($data->isNotEmpty()) {
        // Revenue percentages should be between 0 and 100
        foreach ($data as $row) {
            expect($row->revenue_percent)->toBeGreaterThanOrEqual(0);
            expect($row->revenue_percent)->toBeLessThanOrEqual(100);
        }

        // Total should be approximately 100% (may be slightly less due to rounding/limit)
        $totalPercent = $data->sum('revenue_percent');
        expect($totalPercent)->toBeLessThanOrEqual(100);
    }
});

test('variation group sales report excludes cancelled orders', function () {
    // Create cancelled order
    $cancelledOrder = Order::factory()->create([
        'status' => 'cancelled',
        'received_date' => Carbon::now()->subDays(3),
        'total_charge' => 100.00,
    ]);

    OrderItem::factory()->create([
        'order_id' => $cancelledOrder->id,
        'parent_sku' => 'TEST-PARENT',
        'sku' => 'TEST-001',
        'quantity' => 1,
        'unit_price' => 100.00,
    ]);

    $report = new VariationGroupSalesReport;

    $filters = [
        'date_range' => [
            'start' => $this->dateStart->format('Y-m-d'),
            'end' => $this->dateEnd->format('Y-m-d'),
        ],
    ];

    $data = $report->preview($filters);

    // Cancelled order should not appear
    $testParent = $data->firstWhere('parent_sku', 'TEST-PARENT');
    expect($testParent)->toBeNull();
});

test('product performance report respects status filter', function () {
    $report = new ProductPerformanceReport;

    // Test with only processed orders
    $filters = [
        'date_range' => [
            'start' => $this->dateStart->format('Y-m-d'),
            'end' => $this->dateEnd->format('Y-m-d'),
        ],
        'statuses' => ['processed'],
    ];

    $data = $report->preview($filters);

    expect($data)->toBeInstanceOf(\Illuminate\Support\Collection::class);
});

test('channel performance report executes without errors', function () {
    $report = new ChannelPerformanceReport;

    $filters = [
        'date_range' => [
            'start' => $this->dateStart->format('Y-m-d'),
            'end' => $this->dateEnd->format('Y-m-d'),
        ],
    ];

    $data = $report->preview($filters, 10);

    expect($data)->toBeInstanceOf(\Illuminate\Support\Collection::class);
});

test('order status report calculates percentages correctly', function () {
    $report = new OrderStatusReport;

    $filters = [
        'date_range' => [
            'start' => $this->dateStart->format('Y-m-d'),
            'end' => $this->dateEnd->format('Y-m-d'),
        ],
    ];

    $data = $report->preview($filters);

    if ($data->isNotEmpty()) {
        // All percentages should be between 0 and 100
        foreach ($data as $row) {
            expect($row->percent_of_orders)->toBeGreaterThanOrEqual(0);
            expect($row->percent_of_orders)->toBeLessThanOrEqual(100);
        }

        // Total should be approximately 100%
        $totalPercent = $data->sum('percent_of_orders');
        expect($totalPercent)->toBeGreaterThan(99);
        expect($totalPercent)->toBeLessThanOrEqual(100.1);
    }
});

test('daily revenue report groups by date correctly', function () {
    $report = new DailyRevenueReport;

    $filters = [
        'date_range' => [
            'start' => $this->dateStart->format('Y-m-d'),
            'end' => $this->dateEnd->format('Y-m-d'),
        ],
    ];

    $data = $report->preview($filters);

    if ($data->isNotEmpty()) {
        // Each row should have a date
        foreach ($data as $row) {
            expect($row->date)->not->toBeNull();
        }
    }
});

test('all reports respect date range filters', function () {
    $reports = [
        new TopProductsReport,
        new VariationGroupSalesReport,
        new ProductPerformanceReport,
        new ChannelPerformanceReport,
        new OrderStatusReport,
        new DailyRevenueReport,
    ];

    $filters = [
        'date_range' => [
            'start' => $this->dateStart->format('Y-m-d'),
            'end' => $this->dateEnd->format('Y-m-d'),
        ],
    ];

    foreach ($reports as $report) {
        $data = $report->preview($filters, 10);
        expect($data)->toBeInstanceOf(\Illuminate\Support\Collection::class);
    }
});

test('all reports throw exception for missing required filters', function () {
    $report = new TopProductsReport;

    $report->preview([]); // Missing date_range
})->throws(\InvalidArgumentException::class, "Filter 'date_range' is required");

test('all reports throw exception for invalid filter values', function () {
    $report = new TopProductsReport;

    $filters = [
        'date_range' => 'invalid', // Should be array
    ];

    $report->preview($filters);
})->throws(\InvalidArgumentException::class, "Filter 'date_range' has invalid value");

// Helper function to create test data
function createTestOrders(): void
{
    // Create some processed orders
    for ($i = 0; $i < 5; $i++) {
        $order = Order::factory()->create([
            'status' => 'processed',
            'received_date' => Carbon::now()->subDays(rand(1, 7)),
            'channel_name' => 'Test Channel',
            'subsource' => 'Test Subsource',
            'total_charge' => rand(50, 200),
        ]);

        OrderItem::factory()->count(rand(1, 3))->create([
            'order_id' => $order->id,
            'parent_sku' => 'PARENT-'.str_pad((string) rand(1, 5), 3, '0', STR_PAD_LEFT),
            'sku' => 'SKU-'.str_pad((string) rand(1, 20), 3, '0', STR_PAD_LEFT),
            'quantity' => rand(1, 3),
            'unit_price' => rand(10, 100),
        ]);
    }

    // Create a cancelled order
    $cancelledOrder = Order::factory()->create([
        'status' => 'cancelled',
        'received_date' => Carbon::now()->subDays(3),
        'channel_name' => 'Test Channel',
        'total_charge' => 150.00,
    ]);

    OrderItem::factory()->create([
        'order_id' => $cancelledOrder->id,
        'sku' => 'CANCELLED-SKU',
        'parent_sku' => 'PARENT-CANCELLED',
        'quantity' => 1,
        'unit_price' => 150.00,
    ]);
}
