<?php

use App\Models\Order;
use App\Models\OrderItem;
use App\Reports\Enums\ExportFormat;
use App\Reports\ProductPerformanceReport;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $order1 = Order::factory()->create([
        'status' => 1,
        'received_at' => now()->subDays(5),
    ]);

    $order2 = Order::factory()->create([
        'status' => 1,
        'received_at' => now()->subDays(3),
    ]);

    // SKU-A: appears in both orders
    OrderItem::factory()->create([
        'order_id' => $order1->id,
        'sku' => 'SKU-A',
        'item_title' => 'Widget Alpha',
        'category_name' => 'Widgets',
        'quantity' => 2,
        'price_per_unit' => 10.00,
        'line_total' => 20.00,
        'unit_cost' => 4.00,
        'tax' => 4.00,
    ]);

    OrderItem::factory()->create([
        'order_id' => $order2->id,
        'sku' => 'SKU-A',
        'item_title' => 'Widget Alpha',
        'category_name' => 'Widgets',
        'quantity' => 3,
        'price_per_unit' => 12.00,
        'line_total' => 36.00,
        'unit_cost' => 4.50,
        'tax' => 7.20,
    ]);

    // SKU-B: only in first order
    OrderItem::factory()->create([
        'order_id' => $order1->id,
        'sku' => 'SKU-B',
        'item_title' => 'Gadget Beta',
        'category_name' => 'Gadgets',
        'quantity' => 1,
        'price_per_unit' => 50.00,
        'line_total' => 50.00,
        'unit_cost' => 20.00,
        'tax' => 10.00,
    ]);
});

it('returns aggregated product performance data', function () {
    $report = new ProductPerformanceReport;
    $filters = [
        'date_range' => [
            'start' => now()->subDays(30)->toDateString(),
            'end' => now()->toDateString(),
        ],
    ];

    $results = $report->preview($filters, 100);

    expect($results)->toHaveCount(2);

    $skuA = $results->firstWhere('sku', 'SKU-A');
    $skuB = $results->firstWhere('sku', 'SKU-B');

    // SKU-A: 2 orders, 5 units, £56 revenue, £21.50 cost ((4*2) + (4.5*3))
    expect($skuA)->not->toBeNull();
    expect((int) $skuA->units_sold)->toBe(5);
    expect((int) $skuA->orders)->toBe(2);
    expect((float) $skuA->total_revenue)->toBe(56.00);
    expect((float) $skuA->total_cost)->toBe(21.50);

    // SKU-B: 1 order, 1 unit, £50 revenue, £20 cost
    expect($skuB)->not->toBeNull();
    expect((int) $skuB->units_sold)->toBe(1);
    expect((int) $skuB->orders)->toBe(1);
    expect((float) $skuB->total_revenue)->toBe(50.00);
    expect((float) $skuB->total_cost)->toBe(20.00);
});

it('returns correct current price from most recent order via window function', function () {
    $report = new ProductPerformanceReport;
    $filters = [
        'date_range' => [
            'start' => now()->subDays(30)->toDateString(),
            'end' => now()->toDateString(),
        ],
    ];

    $results = $report->preview($filters, 100);
    $skuA = $results->firstWhere('sku', 'SKU-A');

    // Most recent order for SKU-A is order2 (3 days ago) with price_per_unit=12.00
    expect((float) $skuA->current_price)->toBe(12.00);
});

it('counts total unique SKU rows', function () {
    $report = new ProductPerformanceReport;
    $filters = [
        'date_range' => [
            'start' => now()->subDays(30)->toDateString(),
            'end' => now()->toDateString(),
        ],
    ];

    $count = $report->count($filters);

    expect($count)->toBe(2);
});

it('exports CSV to a temp file using cursor', function () {
    $report = new ProductPerformanceReport;
    $filters = [
        'date_range' => [
            'start' => now()->subDays(30)->toDateString(),
            'end' => now()->toDateString(),
        ],
    ];

    $path = $report->exportToFile($filters, ExportFormat::CSV);

    expect($path)->toBeString();
    expect(file_exists($path))->toBeTrue();

    $content = file_get_contents($path);
    expect($content)->toContain('SKU-A');
    expect($content)->toContain('SKU-B');

    @unlink($path);
});

it('exports XLSX to a temp file', function () {
    $report = new ProductPerformanceReport;
    $filters = [
        'date_range' => [
            'start' => now()->subDays(30)->toDateString(),
            'end' => now()->toDateString(),
        ],
    ];

    $path = $report->exportToFile($filters, ExportFormat::XLSX);

    expect($path)->toBeString();
    expect(file_exists($path))->toBeTrue();
    expect(filesize($path))->toBeGreaterThan(0);

    @unlink($path);
});

it('filters by specific SKUs', function () {
    $report = new ProductPerformanceReport;
    $filters = [
        'date_range' => [
            'start' => now()->subDays(30)->toDateString(),
            'end' => now()->toDateString(),
        ],
        'skus' => ['SKU-B'],
    ];

    $results = $report->preview($filters, 100);

    expect($results)->toHaveCount(1);
    expect($results->first()->sku)->toBe('SKU-B');
});

it('cleans up temp files after export via generate()', function () {
    $report = new ProductPerformanceReport;
    $filters = [
        'date_range' => [
            'start' => now()->subDays(30)->toDateString(),
            'end' => now()->toDateString(),
        ],
    ];

    // Old generate() method should still work (returns string content)
    $content = $report->export($filters, ExportFormat::CSV);

    expect($content)->toBeString();
    expect($content)->toContain('SKU-A');
});

it('handles large datasets with many SKUs across many orders', function () {
    $skuCount = 200;
    $skus = collect(range(1, $skuCount))->map(fn ($i) => sprintf('LOAD-TEST-%04d', $i));

    // Guarantee every SKU gets at least one order
    $skus->each(function (string $sku) {
        $order = Order::factory()->create([
            'status' => Order::STATUS_PROCESSED,
            'received_at' => now()->subDays(rand(1, 30)),
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'sku' => $sku,
            'quantity' => rand(1, 10),
            'price_per_unit' => rand(500, 10000) / 100,
            'line_total' => rand(1000, 50000) / 100,
            'unit_cost' => rand(100, 5000) / 100,
            'tax' => rand(0, 2000) / 100,
        ]);
    });

    // Total unique SKUs: 200 load test + 2 from beforeEach
    $expectedSkus = $skuCount + 2;

    $report = new ProductPerformanceReport;
    $filters = [
        'date_range' => [
            'start' => now()->subDays(30)->toDateString(),
            'end' => now()->toDateString(),
        ],
    ];

    $memBefore = memory_get_usage(true);

    // Preview should cap at 100
    $preview = $report->preview($filters, 100);
    expect($preview)->toHaveCount(100);

    // Count should return all unique SKUs
    $count = $report->count($filters);
    expect($count)->toBe($expectedSkus);

    // CSV export via cursor — the bit that was OOMing
    $csvPath = $report->exportToFile($filters, ExportFormat::CSV);
    expect(file_exists($csvPath))->toBeTrue();

    $csvLines = count(file($csvPath));
    expect($csvLines)->toBe($expectedSkus + 1); // data rows + header

    $memAfter = memory_get_usage(true);
    $memUsedMb = ($memAfter - $memBefore) / 1024 / 1024;

    // Should stay well under 50MB — old approach would balloon with 3 copies in memory
    expect($memUsedMb)->toBeLessThan(50);

    // XLSX export
    $xlsxPath = $report->exportToFile($filters, ExportFormat::XLSX);
    expect(file_exists($xlsxPath))->toBeTrue();
    expect(filesize($xlsxPath))->toBeGreaterThan(0);

    @unlink($csvPath);
    @unlink($xlsxPath);
});
