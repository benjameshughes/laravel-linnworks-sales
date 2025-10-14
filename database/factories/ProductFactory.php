<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'linnworks_id' => \Illuminate\Support\Str::uuid(),
            'sku' => $this->faker->unique()->regexify('[A-Z]{3}-[0-9]{6}'),
            'title' => $this->faker->words(3, true),
            'category_name' => $this->faker->randomElement(['Electronics', 'Clothing', 'Books', 'Home & Garden', 'Toys']),
            'purchase_price' => $this->faker->randomFloat(2, 5, 500),
            'retail_price' => $this->faker->randomFloat(2, 10, 1000),
            'stock_available' => $this->faker->numberBetween(0, 500),
            'stock_minimum' => $this->faker->numberBetween(5, 50),
            'stock_in_orders' => 0,
            'stock_due' => 0,
            'is_active' => true,
        ];
    }
}
