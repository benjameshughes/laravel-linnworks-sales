<?php

declare(strict_types=1);

use App\Models\Order;
use App\Enums\Channel;
use App\Models\Product;
use App\Queries\ProfitabilityQueries;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('calculates revenue and profit for a single product', function () {
    $product = Product::factory()->create(['purchase_price' => 5.00, 'shipping_cost' => 2.00]);
    Order::factory()
        ->withItems([['sku' => $product->sku, 'quantity' => 3, 'price_per_unit' => 15.00, 'unit_cost' => 5.00]])
        ->create(['received_at' => now()->subDays(5), 'source' => 'DIRECT']);

    $result = app(ProfitabilityQueries::class)->productProfitability(
        $product->sku,
        now()->subDays(30),
        now()
    );

    expect($result)->not->toBeNull()
        ->and((float) $result->revenue)->toBe(45.0)
        ->and((float) $result->cogs)->toBe(15.0)
        ->and((float) $result->profit)->toBeGreaterThan(0.0);
});

it('applies channel fee percentages from config', function () {
    config(['channel-fees' => ['AMAZON' => 15.0]]);

    $product = Product::factory()->create(['purchase_price' => 0, 'shipping_cost' => 0]);
    Order::factory()
        ->afterCreating(function (Order $order) use ($product) {
            \App\Models\OrderItem::factory()->create([
                'order_id' => $order->id,
                'sku' => $product->sku,
                'quantity' => 1,
                'price_per_unit' => 100.00,
                'line_total' => 100.00,
                'unit_cost' => 0,
                'shipping_cost' => 0,
            ]);
        })
        ->create(['received_at' => now()->subDays(5), 'source' => 'AMAZON']);

    $result = app(ProfitabilityQueries::class)->productProfitability(
        $product->sku,
        now()->subDays(30),
        now()
    );

    expect((float) $result->channel_fees)->toBe(15.0)
        ->and((float) $result->profit)->toBe(85.0);
});

it('returns bulk profitability data keyed by SKU', function () {
    $products = Product::factory()->count(3)->create();
    Order::factory()
        ->withItems([['sku' => $products[0]->sku, 'quantity' => 2, 'price_per_unit' => 10.00]])
        ->create(['received_at' => now()->subDays(5)]);

    $result = app(ProfitabilityQueries::class)->bulkProfitabilityData(
        $products->pluck('sku')->toArray(),
        30
    );

    expect($result)->toHaveCount(3)
        ->and($result->has($products[0]->sku))->toBeTrue()
        ->and((float) $result->get($products[0]->sku)->revenue)->toBe(20.0)
        ->and((float) $result->get($products[1]->sku)->revenue)->toBe(0.0);
});

it('returns channel profit impact grouped by source', function () {
    $product = Product::factory()->create();
    Order::factory()
        ->withItems([['sku' => $product->sku, 'quantity' => 1, 'price_per_unit' => 20.00]])
        ->create(['received_at' => now()->subDays(5), 'source' => 'AMAZON']);
    Order::factory()
        ->withItems([['sku' => $product->sku, 'quantity' => 1, 'price_per_unit' => 15.00]])
        ->create(['received_at' => now()->subDays(5), 'source' => 'EBAY']);

    $result = app(ProfitabilityQueries::class)->channelProfitImpact(
        now()->subDays(30),
        now()
    );

    expect($result)->toHaveCount(2)
        ->and($result->pluck('source')->toArray())->toContain('AMAZON')
        ->and($result->pluck('source')->toArray())->toContain('EBAY');
});

it('uses unit_cost from order items when available', function () {
    $product = Product::factory()->create(['purchase_price' => 3.00]);
    Order::factory()
        ->withItems([['sku' => $product->sku, 'quantity' => 1, 'price_per_unit' => 20.00, 'unit_cost' => 7.00]])
        ->create(['received_at' => now()->subDays(5), 'source' => 'DIRECT']);

    $result = app(ProfitabilityQueries::class)->productProfitability(
        $product->sku,
        now()->subDays(30),
        now()
    );

    expect((float) $result->cogs)->toBe(7.0);
});

it('falls back to purchase_price when unit_cost is zero', function () {
    $product = Product::factory()->create(['purchase_price' => 4.50]);
    Order::factory()
        ->withItems([['sku' => $product->sku, 'quantity' => 2, 'price_per_unit' => 20.00, 'unit_cost' => 0]])
        ->create(['received_at' => now()->subDays(5), 'source' => 'DIRECT']);

    $result = app(ProfitabilityQueries::class)->productProfitability(
        $product->sku,
        now()->subDays(30),
        now()
    );

    expect((float) $result->cogs)->toBe(9.0);
});

describe('Channel enum fee methods', function () {
    it('reads fee percentage from config', function () {
        config(['channel-fees.AMAZON' => 15.0]);

        expect(Channel::Amazon->feePercentage())->toBe(15.0);
    });

    it('returns zero for channels with no configured fee', function () {
        config(['channel-fees.DIRECT' => 0.0]);

        expect(Channel::Direct->feePercentage())->toBe(0.0);
    });

    it('looks up fee by raw source string', function () {
        config(['channel-fees.EBAY' => 12.5]);

        expect(Channel::feePercentageFor('EBAY'))->toBe(12.5)
            ->and(Channel::feePercentageFor('UNKNOWN_CHANNEL'))->toBe(0.0)
            ->and(Channel::feePercentageFor(null))->toBe(0.0);
    });
});
