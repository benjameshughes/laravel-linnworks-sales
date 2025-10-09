<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CreateProductsFromOrders extends Command
{
    protected $signature = 'products:create-from-orders';

    protected $description = 'Create product records from order items to satisfy FK constraints';

    public function handle(): int
    {
        $this->info('Creating products from order items...');

        $uniqueProducts = collect();

        // Get all unique SKUs from orders' items JSON
        Order::whereNotNull('items')->chunk(100, function ($orders) use (&$uniqueProducts) {
            foreach ($orders as $order) {
                $items = $order->items ?? [];

                foreach ($items as $item) {
                    $sku = $item['sku'] ?? null;

                    if (!$sku) {
                        continue;
                    }

                    // Check if we already have this SKU
                    if (!$uniqueProducts->has($sku)) {
                        $uniqueProducts->put($sku, [
                            'sku' => $sku,
                            'title' => $item['item_title'] ?? "Product {$sku}",
                            'price' => $item['price_per_unit'] ?? 0,
                            'category' => $item['category_name'] ?? null,
                        ]);
                    }
                }
            }
        });

        $this->info("Found {$uniqueProducts->count()} unique SKUs in orders");

        // Filter out SKUs that already exist in products table
        $existingSKUs = Product::whereIn('sku', $uniqueProducts->keys())->pluck('sku')->toArray();
        $newProducts = $uniqueProducts->reject(fn ($product) => in_array($product['sku'], $existingSKUs));

        $this->info("{$newProducts->count()} new products to create");

        if ($newProducts->isEmpty()) {
            $this->info('No new products to create');
            return self::SUCCESS;
        }

        // Create products
        $created = 0;
        foreach ($newProducts as $productData) {
            try {
                Product::create([
                    'linnworks_id' => 'order-derived-' . $productData['sku'],
                    'sku' => $productData['sku'],
                    'title' => $productData['title'],
                    'retail_price' => $productData['price'],
                    'category_name' => $productData['category'],
                    'is_active' => true,
                    'stock_level' => 0,
                    'stock_available' => 0,
                    'last_synced_at' => now(),
                    'metadata' => [
                        'source' => 'order_items',
                        'created_from_orders' => true,
                    ],
                ]);
                $created++;
            } catch (\Throwable $e) {
                $this->warn("Failed to create product {$productData['sku']}: {$e->getMessage()}");
            }
        }

        $this->info("✅ Created {$created} products");

        return self::SUCCESS;
    }
}
