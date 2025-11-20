<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\OrderItem>
 */
class OrderItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quantity = $this->faker->numberBetween(1, 10);
        $pricePerUnit = $this->faker->randomFloat(2, 5, 100);
        $unitCost = $this->faker->randomFloat(2, 1, $pricePerUnit * 0.7);
        $lineTotal = $quantity * $pricePerUnit;

        return [
            'order_id' => \App\Models\Order::factory(),

            // Linnworks identifiers
            'item_id' => \Illuminate\Support\Str::uuid(),
            'stock_item_id' => \Illuminate\Support\Str::uuid(),
            'stock_item_int_id' => $this->faker->numberBetween(1000, 9999),
            'row_id' => \Illuminate\Support\Str::uuid(),
            'item_number' => $this->faker->numerify('ITEM-######'),

            // SKU & Titles
            'sku' => $this->faker->regexify('[A-Z]{3}-[0-9]{6}'),
            'parent_sku' => $this->faker->optional()->regexify('[A-Z]{3}'),
            'item_title' => $this->faker->words(3, true),
            'item_source' => $this->faker->randomElement(['Amazon', 'eBay', 'Website']),
            'channel_sku' => $this->faker->optional()->regexify('[A-Z0-9]{10}'),
            'channel_title' => $this->faker->optional()->words(4, true),
            'barcode_number' => $this->faker->optional()->ean13(),

            // Quantity
            'quantity' => $quantity,
            'part_shipped_qty' => $this->faker->optional()->numberBetween(0, $quantity),

            // Category
            'category_name' => $this->faker->optional()->word(),

            // Pricing
            'price_per_unit' => $pricePerUnit,
            'unit_cost' => $unitCost,
            'line_total' => $lineTotal,
            'cost' => $unitCost * $quantity,
            'cost_inc_tax' => ($unitCost * $quantity) * 1.2,
            'despatch_stock_unit_cost' => $unitCost,
            'discount' => 0,
            'discount_value' => 0,

            // Tax
            'tax' => $this->faker->randomFloat(2, 0, $lineTotal * 0.2),
            'tax_rate' => $this->faker->randomFloat(4, 0, 0.25),
            'sales_tax' => $this->faker->randomFloat(2, 0, $lineTotal * 0.2),
            'tax_cost_inclusive' => false,

            // Stock levels
            'stock_levels_specified' => false,
            'stock_level' => $this->faker->optional()->numberBetween(0, 100),
            'available_stock' => $this->faker->optional()->numberBetween(0, 100),
            'on_order' => $this->faker->optional()->numberBetween(0, 50),
            'stock_level_indicator' => $this->faker->optional()->numberBetween(0, 3),

            // Inventory tracking
            'inventory_tracking_type' => $this->faker->optional()->numberBetween(0, 2),
            'is_batched_stock_item' => false,
            'is_warehouse_managed' => false,
            'is_unlinked' => false,
            'batch_number_scan_required' => false,
            'serial_number_scan_required' => false,

            // Shipping
            'part_shipped' => false,
            'weight' => $this->faker->randomFloat(3, 0.1, 10),
            'shipping_cost' => $this->faker->randomFloat(2, 0, 10),
            'bin_rack' => $this->faker->optional()->bothify('??-##-##'),
            'bin_racks' => null,

            // Product attributes
            'is_service' => false,
            'has_image' => $this->faker->boolean(60),
            'image_id' => $this->faker->optional()->uuid(),
            'market' => $this->faker->optional()->numberBetween(1, 5),

            // Composite items & additional data
            'composite_sub_items' => null,
            'additional_info' => null,

            // Metadata
            'added_at' => $this->faker->optional()->dateTimeBetween('-30 days', 'now'),
        ];
    }
}
