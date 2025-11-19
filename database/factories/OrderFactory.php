<?php

namespace Database\Factories;

use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        return [
            // Linnworks identifiers
            'order_id' => Str::uuid(),
            'number' => fake()->unique()->numberBetween(10000, 99999),

            // Order dates
            'received_at' => fake()->dateTimeBetween('-30 days', 'now'),
            'processed_at' => fake()->optional()->dateTimeBetween('-25 days', 'now'),
            'paid_at' => fake()->optional()->dateTimeBetween('-28 days', 'now'),
            'despatch_by_at' => fake()->optional()->dateTimeBetween('-15 days', '+5 days'),

            // Channel information
            'source' => fake()->randomElement(['Amazon', 'eBay', 'Website', 'Etsy']),
            'subsource' => fake()->optional()->randomElement(['Amazon UK', 'eBay UK', 'Amazon US']),

            // Financial information
            'currency' => fake()->randomElement(['GBP', 'USD', 'EUR']),
            'total_charge' => fake()->randomFloat(2, 10, 500),
            'postage_cost' => fake()->randomFloat(2, 0, 25),
            'postage_cost_ex_tax' => fake()->randomFloat(2, 0, 20),
            'tax' => fake()->randomFloat(2, 0, 20),
            'profit_margin' => fake()->randomFloat(2, 0, 100),
            'total_discount' => fake()->randomFloat(2, 0, 50),
            'country_tax_rate' => fake()->optional()->randomFloat(4, 0, 0.25),
            'conversion_rate' => 1.0,

            // Order status
            'status' => fake()->randomElement([0, 1, 2]),
            'is_paid' => fake()->boolean(80),
            'is_cancelled' => fake()->boolean(5),

            // Location
            'location_id' => Str::uuid(),

            // Payment information
            'payment_method' => fake()->optional()->randomElement(['Credit Card', 'PayPal', 'Bank Transfer']),
            'payment_method_id' => fake()->optional()->uuid(),

            // Reference numbers
            'channel_reference_number' => fake()->optional()->numerify('###-#######-#######'),
            'secondary_reference' => fake()->optional()->numerify('REF-########'),
            'external_reference_num' => fake()->optional()->numerify('EXT-########'),

            // Order flags
            'marker' => fake()->randomElement([0, 1, 2, 3, 4]),
            'is_parked' => fake()->boolean(5),
            'label_printed' => fake()->boolean(60),
            'label_error' => fake()->optional()->sentence(),
            'invoice_printed' => fake()->boolean(70),
            'pick_list_printed' => fake()->boolean(65),
            'is_rule_run' => fake()->boolean(80),
            'part_shipped' => fake()->boolean(10),
            'has_scheduled_delivery' => fake()->boolean(20),
            'pickwave_ids' => fake()->optional()->passthrough(json_encode([fake()->uuid(), fake()->uuid()])),
            'num_items' => fake()->numberBetween(1, 10),
        ];
    }

    /**
     * Create an open order (unshipped/pending)
     */
    public function open(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 0,
            'processed_at' => null,
            'paid_at' => null,
        ]);
    }

    /**
     * Create a closed order (shipped/processed)
     */
    public function closed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 1,
            'processed_at' => fake()->dateTimeBetween('-10 days', 'now'),
            'paid_at' => fake()->dateTimeBetween('-12 days', 'now'),
        ]);
    }

    /**
     * Create a cancelled order
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 2,
            'is_cancelled' => true,
        ]);
    }

    /**
     * Create an order from a specific channel
     */
    public function fromChannel(string $channel): static
    {
        return $this->state(fn (array $attributes) => [
            'source' => $channel,
        ]);
    }

    /**
     * Create a paid order
     */
    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_paid' => true,
            'paid_at' => fake()->dateTimeBetween('-15 days', 'now'),
        ]);
    }

    /**
     * Create an order with items
     *
     * @param  array  $items  Array of items [['sku' => 'ABC', 'quantity' => 2], ...]
     */
    public function withItems(array $items): static
    {
        return $this->afterCreating(function (Order $order) use ($items) {
            foreach ($items as $item) {
                \App\Models\OrderItem::factory()->create([
                    'order_id' => $order->id,
                    'sku' => $item['sku'] ?? fake()->regexify('[A-Z]{3}-[0-9]{6}'),
                    'quantity' => $item['quantity'] ?? 1,
                    'item_title' => $item['item_title'] ?? fake()->words(3, true),
                ]);
            }
        });
    }
}
