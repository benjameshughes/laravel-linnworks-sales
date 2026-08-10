<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ProductParent>
 */
class ProductParentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'linnworks_id' => Str::uuid()->toString(),
            'sku' => $this->faker->unique()->numerify('###'),
            'title' => $this->faker->words(3, true),
            'metadata' => [],
            'last_synced_at' => now(),
        ];
    }
}
