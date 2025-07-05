<?php

namespace Database\Factories;

use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;
use Carbon\Carbon;

class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        return [
            'linnworks_order_id' => $this->faker->uuid(),
            'order_number' => $this->faker->unique()->numberBetween(10000, 99999),
            'channel_name' => $this->faker->randomElement(['Amazon', 'eBay', 'Website', 'Etsy']),
            'channel_reference_number' => $this->faker->randomNumber(8),
            'source' => $this->faker->randomElement(['Amazon', 'eBay', 'Website', 'Etsy']),
            'sub_source' => $this->faker->optional()->randomElement(['Amazon UK', 'eBay UK', 'Amazon US']),
            'external_reference' => $this->faker->optional()->randomNumber(8),
            'total_charge' => $this->faker->randomFloat(2, 10, 500),
            'total_discount' => $this->faker->randomFloat(2, 0, 50),
            'postage_cost' => $this->faker->randomFloat(2, 0, 25),
            'total_paid' => function (array $attributes) {
                return $attributes['total_charge'] - $attributes['total_discount'] + $attributes['postage_cost'];
            },
            'profit_margin' => $this->faker->randomFloat(2, 0, 100),
            'currency' => $this->faker->randomElement(['GBP', 'USD', 'EUR']),
            'status' => $this->faker->randomElement(['pending', 'processed', 'cancelled']),
            'addresses' => [
                'billing' => [
                    'name' => $this->faker->name(),
                    'country' => $this->faker->country(),
                ],
                'shipping' => [
                    'name' => $this->faker->name(),
                    'country' => $this->faker->country(),
                ],
            ],
            'received_date' => $this->faker->dateTimeBetween('-30 days', 'now'),
            'processed_date' => $this->faker->optional()->dateTimeBetween('-25 days', 'now'),
            'dispatched_date' => $this->faker->optional()->dateTimeBetween('-20 days', 'now'),
            'is_resend' => $this->faker->boolean(10),
            'is_exchange' => $this->faker->boolean(5),
            'notes' => $this->faker->optional()->sentence(),
            'raw_data' => [
                'linnworks_order_id' => $this->faker->uuid(),
                'order_number' => $this->faker->numberBetween(10000, 99999),
                'order_status' => $this->faker->numberBetween(0, 2),
                'location_id' => $this->faker->uuid(),
            ],
            'items' => [
                [
                    'item_id' => $this->faker->uuid(),
                    'sku' => $this->faker->unique()->regexify('[A-Z]{3}[0-9]{3}'),
                    'item_title' => $this->faker->words(3, true),
                    'quantity' => $this->faker->numberBetween(1, 5),
                    'unit_cost' => $this->faker->randomFloat(2, 5, 50),
                    'price_per_unit' => $this->faker->randomFloat(2, 10, 100),
                    'line_total' => $this->faker->randomFloat(2, 10, 200),
                    'category_name' => $this->faker->word(),
                ],
            ],
            'order_source' => $this->faker->randomElement(['Amazon', 'eBay', 'Website', 'Etsy']),
            'subsource' => $this->faker->optional()->randomElement(['Amazon UK', 'eBay UK', 'Amazon US']),
            'tax' => $this->faker->randomFloat(2, 0, 20),
            'order_status' => $this->faker->randomElement([0, 1, 2]), // 0=pending, 1=processed, 2=cancelled
            'location_id' => $this->faker->uuid(),
            'last_synced_at' => $this->faker->optional()->dateTimeBetween('-1 day', 'now'),
            'is_open' => true,
            'has_refund' => false,
            'sync_status' => 'synced',
            'sync_metadata' => [],
        ];
    }

    /**
     * Create an open order (unshipped/pending)
     */
    public function open(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_open' => true,
            'has_refund' => false,
            'status' => 'pending',
            'order_status' => 0,
            'processed_date' => null,
            'dispatched_date' => null,
        ]);
    }

    /**
     * Create a closed order (shipped/processed)
     */
    public function closed(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_open' => false,
            'has_refund' => false,
            'status' => 'processed',
            'order_status' => 1,
            'processed_date' => $this->faker->dateTimeBetween('-10 days', 'now'),
            'dispatched_date' => $this->faker->dateTimeBetween('-8 days', 'now'),
        ]);
    }

    /**
     * Create a refunded order
     */
    public function refunded(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_open' => false,
            'has_refund' => true,
            'status' => 'cancelled',
            'order_status' => 2,
            'sync_metadata' => [
                'refund_detected_at' => Carbon::now()->toDateTimeString(),
                'refund_amount' => $this->faker->randomFloat(2, 5, $attributes['total_charge']),
            ],
        ]);
    }

    /**
     * Create an order that needs syncing
     */
    public function needingSync(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_open' => true,
            'last_synced_at' => $this->faker->dateTimeBetween('-1 hour', '-16 minutes'),
            'sync_status' => 'pending',
        ]);
    }

    /**
     * Create an order that was recently synced
     */
    public function recentlySync(): static
    {
        return $this->state(fn (array $attributes) => [
            'last_synced_at' => $this->faker->dateTimeBetween('-5 minutes', 'now'),
            'sync_status' => 'synced',
        ]);
    }

    /**
     * Create an order from a specific channel
     */
    public function fromChannel(string $channel): static
    {
        return $this->state(fn (array $attributes) => [
            'channel_name' => $channel,
            'source' => $channel,
            'order_source' => $channel,
        ]);
    }

    /**
     * Create an order with specific items
     */
    public function withItems(array $items): static
    {
        return $this->state(fn (array $attributes) => [
            'items' => $items,
        ]);
    }

    /**
     * Create an order with a specific total charge
     */
    public function withTotal(float $total): static
    {
        return $this->state(fn (array $attributes) => [
            'total_charge' => $total,
            'total_paid' => $total,
        ]);
    }
}