<?php

declare(strict_types=1);

use App\Models\Order;
use App\Models\Product;
use App\Models\OrderItem;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('products:enrich command', function () {
    it('enriches products with pricing from order items', function () {
        $product = Product::factory()->create([
            'sku' => 'TEST-001',
            'retail_price' => null,
            'purchase_price' => null,
            'category_name' => null,
        ]);

        $order = Order::factory()->create();

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'sku' => 'TEST-001',
            'price_per_unit' => 24.99,
            'unit_cost' => 4.50,
            'category_name' => 'AISLE 3',
        ]);

        $this->artisan('products:enrich')
            ->expectsOutputToContain('Updated: 1')
            ->assertExitCode(0);

        $product->refresh();

        expect($product->retail_price)->not->toBeNull()
            ->and((float) $product->purchase_price)->toBe(4.5)
            ->and($product->category_name)->toBe('AISLE 3');
    });

    it('does not overwrite existing data without --force', function () {
        $product = Product::factory()->create([
            'sku' => 'TEST-002',
            'retail_price' => 29.99,
            'purchase_price' => 5.00,
            'category_name' => 'Original Category',
        ]);

        $order = Order::factory()->create();

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'sku' => 'TEST-002',
            'price_per_unit' => 19.99,
            'unit_cost' => 3.00,
            'category_name' => 'New Category',
        ]);

        $this->artisan('products:enrich')
            ->assertExitCode(0);

        $product->refresh();

        expect((float) $product->retail_price)->toBe(29.99)
            ->and((float) $product->purchase_price)->toBe(5.0)
            ->and($product->category_name)->toBe('Original Category');
    });

    it('overwrites existing data with --force', function () {
        $product = Product::factory()->create([
            'sku' => 'TEST-003',
            'retail_price' => 29.99,
            'purchase_price' => 5.00,
        ]);

        $order = Order::factory()->create();

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'sku' => 'TEST-003',
            'price_per_unit' => 19.99,
            'unit_cost' => 3.50,
        ]);

        $this->artisan('products:enrich --force')
            ->assertExitCode(0);

        $product->refresh();

        expect((float) $product->retail_price)->toBe(19.99)
            ->and((float) $product->purchase_price)->toBe(3.5);
    });

    it('handles products with no matching order items', function () {
        Product::factory()->create([
            'sku' => 'ORPHAN-001',
            'retail_price' => null,
        ]);

        $this->artisan('products:enrich')
            ->expectsOutputToContain('Skipped:')
            ->assertExitCode(0);
    });

    it('skips order items with zero unit cost', function () {
        $product = Product::factory()->create([
            'sku' => 'TEST-004',
            'purchase_price' => null,
        ]);

        $order = Order::factory()->create();

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'sku' => 'TEST-004',
            'price_per_unit' => 15.00,
            'unit_cost' => 0,
        ]);

        $this->artisan('products:enrich')
            ->assertExitCode(0);

        $product->refresh();

        expect($product->purchase_price)->toBeNull();
    });
});
