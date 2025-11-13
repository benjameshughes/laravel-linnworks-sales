<?php

declare(strict_types=1);

use App\Models\Order;
use App\Models\OrderItem;
use App\Reports\Enums\ExportFormat;
use App\Reports\ProductPerformanceReport;
use App\Reports\TopProductsReport;
use Carbon\Carbon;

beforeEach(function () {
    $this->dateStart = Carbon::now()->subDays(7);
    $this->dateEnd = Carbon::now();

    // Create test data
    for ($i = 0; $i < 3; $i++) {
        $order = Order::factory()->create([
            'status' => 'processed',
            'received_date' => Carbon::now()->subDays(rand(1, 7)),
            'total_charge' => rand(50, 200),
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'sku' => 'TEST-SKU-'.$i,
            'title' => 'Test Product '.$i,
            'quantity' => rand(1, 3),
            'unit_price' => rand(10, 100),
        ]);
    }
});

test('top products report exports to XLSX without errors', function () {
    $report = new TopProductsReport;

    $filters = [
        'date_range' => [
            'start' => $this->dateStart->format('Y-m-d'),
            'end' => $this->dateEnd->format('Y-m-d'),
        ],
    ];

    $content = $report->export($filters, ExportFormat::XLSX);

    expect($content)->toBeString();
    expect($content)->not->toBeEmpty();
});

test('top products report exports to CSV without errors', function () {
    $report = new TopProductsReport;

    $filters = [
        'date_range' => [
            'start' => $this->dateStart->format('Y-m-d'),
            'end' => $this->dateEnd->format('Y-m-d'),
        ],
    ];

    $content = $report->export($filters, ExportFormat::CSV);

    expect($content)->toBeString();
    expect($content)->not->toBeEmpty();
    expect($content)->toContain('SKU'); // Should contain header
});

test('CSV export contains correct number of rows', function () {
    $report = new TopProductsReport;

    $filters = [
        'date_range' => [
            'start' => $this->dateStart->format('Y-m-d'),
            'end' => $this->dateEnd->format('Y-m-d'),
        ],
    ];

    $previewCount = $report->preview($filters)->count();
    $csvContent = $report->export($filters, ExportFormat::CSV);

    $lines = explode("\n", trim($csvContent));

    // Should have header + data rows
    expect(count($lines))->toBe($previewCount + 1);
});

test('export formats currency values correctly', function () {
    $report = new TopProductsReport;

    $filters = [
        'date_range' => [
            'start' => $this->dateStart->format('Y-m-d'),
            'end' => $this->dateEnd->format('Y-m-d'),
        ],
    ];

    $csvContent = $report->export($filters, ExportFormat::CSV);

    // Currency values should be formatted with 2 decimal places
    expect($csvContent)->toMatch('/\d+\.\d{2}/');
});

test('export formats percentage values correctly', function () {
    $report = new TopProductsReport;

    $filters = [
        'date_range' => [
            'start' => $this->dateStart->format('Y-m-d'),
            'end' => $this->dateEnd->format('Y-m-d'),
        ],
    ];

    $csvContent = $report->export($filters, ExportFormat::CSV);

    // Percentage values should contain %
    expect($csvContent)->toContain('%');
});

test('export handles empty results without error', function () {
    // Query with no results
    $report = new TopProductsReport;

    $filters = [
        'date_range' => [
            'start' => Carbon::now()->subYears(10)->format('Y-m-d'),
            'end' => Carbon::now()->subYears(9)->format('Y-m-d'),
        ],
    ];

    $content = $report->export($filters, ExportFormat::CSV);

    expect($content)->toBeString();
    // Should at least have headers
    expect($content)->toContain('SKU');
});

test('export validates required filters', function () {
    $report = new TopProductsReport;

    // Missing required date_range filter
    $report->export([]);
})->throws(\InvalidArgumentException::class);

test('export includes all columns from report definition', function () {
    $report = new ProductPerformanceReport;

    $filters = [
        'date_range' => [
            'start' => $this->dateStart->format('Y-m-d'),
            'end' => $this->dateEnd->format('Y-m-d'),
        ],
    ];

    $csvContent = $report->export($filters, ExportFormat::CSV);

    $columns = $report->columns();

    // Check that all column labels appear in CSV header
    foreach ($columns as $columnConfig) {
        $label = $columnConfig['label'];
        expect($csvContent)->toContain($label);
    }
});

test('XLSX export creates valid spreadsheet structure', function () {
    $report = new TopProductsReport;

    $filters = [
        'date_range' => [
            'start' => $this->dateStart->format('Y-m-d'),
            'end' => $this->dateEnd->format('Y-m-d'),
        ],
    ];

    $content = $report->export($filters, ExportFormat::XLSX);

    // XLSX files start with specific bytes (PK for ZIP format)
    expect(substr($content, 0, 2))->toBe('PK');
});

test('export respects filter parameters', function () {
    // Create specific SKU
    $order = Order::factory()->create([
        'status' => 'processed',
        'received_date' => Carbon::now()->subDays(3),
    ]);

    OrderItem::factory()->create([
        'order_id' => $order->id,
        'sku' => 'SPECIFIC-SKU',
        'quantity' => 1,
        'unit_price' => 50.00,
    ]);

    $report = new ProductPerformanceReport;

    $filters = [
        'date_range' => [
            'start' => $this->dateStart->format('Y-m-d'),
            'end' => $this->dateEnd->format('Y-m-d'),
        ],
        'skus' => ['SPECIFIC-SKU'],
    ];

    $csvContent = $report->export($filters, ExportFormat::CSV);

    // Should contain our specific SKU
    expect($csvContent)->toContain('SPECIFIC-SKU');

    // Should NOT contain other SKUs
    expect($csvContent)->not->toContain('TEST-SKU-0');
});
