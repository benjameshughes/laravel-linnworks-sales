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
        $unitPrice = $this->faker->randomFloat(2, 5, 100);
        $totalPrice = $quantity * $unitPrice;
        $costPrice = $this->faker->randomFloat(2, 1, $unitPrice * 0.7);

        return [
            'order_id' => \App\Models\Order::factory(),
            'linnworks_item_id' => \Illuminate\Support\Str::uuid(),
            'sku' => $this->faker->regexify('[A-Z]{3}-[0-9]{6}'),
            'title' => $this->faker->words(3, true),
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'total_price' => $totalPrice,
            'cost_price' => $costPrice,
            'tax_rate' => $this->faker->randomFloat(2, 0, 25),
            'discount_amount' => 0,
            'is_service' => false,
            'item_attributes' => null,
        ];
    }
}
