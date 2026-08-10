<?php

use App\Models\Order;
use App\Models\OrderItem;
use App\Reports\VariationGroupSalesReport;
use App\Reports\Exports\VariationGroupExport;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function variationFilters(): array
{
    return [
        'date_range' => ['start' => '2026-02-01', 'end' => '2026-02-28'],
        'skus' => [],
        'subsources' => [],
    ];
}

function seedVariationSales(): void
{
    $order = Order::factory()->create([
        'received_at' => '2026-02-10 09:00:00',
        'source' => 'AMAZON',
        'subsource' => 'Amazon UK',
        'status' => 1,
    ]);

    OrderItem::factory()->create([
        'order_id' => $order->id,
        'sku' => '005-001',
        'parent_sku' => '005',
        'quantity' => 3,
        'line_total' => 59.97,
    ]);
}

it('produces a real spreadsheet, not an empty string', function () {
    seedVariationSales();

    $xlsx = new VariationGroupSalesReport()->export(variationFilters());

    // a zip header is the only proof PhpSpreadsheet actually wrote a workbook
    expect(substr($xlsx, 0, 2))->toBe('PK')
        ->and(strlen($xlsx))->toBeGreaterThan(1000);
});

it('survives MySQL returning SUM() as a string', function () {
    seedVariationSales();

    // number_format() on a string throws under strict_types - the export must
    // cast aggregate columns rather than trust their PHP type
    $rows = collect([(object) [
        'parent_sku' => '005',
        'order_count' => '1',
        'total_units' => '3',
        'total_revenue' => '59.97',
    ]]);

    $xlsx = new VariationGroupExport()->generate(variationFilters(), $rows);

    expect(substr($xlsx, 0, 2))->toBe('PK');
});

it('writes the export to a readable file', function () {
    seedVariationSales();

    $path = new VariationGroupSalesReport()->exportToFile(variationFilters());

    expect(file_exists($path))->toBeTrue()
        ->and(filesize($path))->toBeGreaterThan(1000);

    unlink($path);
});

it('returns nothing when no order items carry a parent sku', function () {
    $order = Order::factory()->create(['received_at' => '2026-02-10 09:00:00', 'status' => 1]);
    OrderItem::factory()->create(['order_id' => $order->id, 'parent_sku' => null]);

    expect(new VariationGroupSalesReport()->export(variationFilters()))->toBe('');
});
