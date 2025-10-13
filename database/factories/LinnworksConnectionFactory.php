<?php

namespace Database\Factories;

use App\Models\LinnworksConnection;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

class LinnworksConnectionFactory extends Factory
{
    protected $model = LinnworksConnection::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'application_id' => $this->faker->uuid(),
            'application_secret' => $this->faker->sha256(),
            'access_token' => $this->faker->sha256(),
            'session_token' => $this->faker->sha256(),
            'server_location' => 'https://eu-ext.linnworks.net',
            'session_expires_at' => Carbon::now()->addHours(2),
            'is_active' => true,
            'application_data' => [
                'Server' => 'https://eu-ext.linnworks.net',
                'Token' => $this->faker->sha256(),
            ],
            'status' => 'active',
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'session_expires_at' => Carbon::now()->subHour(),
        ]);
    }

    public function withoutSessionToken(): static
    {
        return $this->state(fn (array $attributes) => [
            'session_token' => null,
            'session_expires_at' => null,
        ]);
    }
}
