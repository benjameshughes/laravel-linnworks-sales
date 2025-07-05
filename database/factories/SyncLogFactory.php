<?php

namespace Database\Factories;

use App\Models\SyncLog;
use Illuminate\Database\Eloquent\Factories\Factory;
use Carbon\Carbon;

class SyncLogFactory extends Factory
{
    protected $model = SyncLog::class;

    public function definition(): array
    {
        $started = $this->faker->dateTimeBetween('-1 hour', '-10 minutes');
        $completed = $this->faker->optional(0.8)->dateTimeBetween($started, 'now');

        return [
            'sync_type' => $this->faker->randomElement([
                SyncLog::TYPE_OPEN_ORDERS,
                SyncLog::TYPE_HISTORICAL_ORDERS,
                SyncLog::TYPE_ORDER_UPDATES,
            ]),
            'status' => $completed ? SyncLog::STATUS_COMPLETED : SyncLog::STATUS_STARTED,
            'started_at' => $started,
            'completed_at' => $completed,
            'total_fetched' => $this->faker->numberBetween(0, 100),
            'total_created' => $this->faker->numberBetween(0, 20),
            'total_updated' => $this->faker->numberBetween(0, 30),
            'total_skipped' => $this->faker->numberBetween(0, 10),
            'total_failed' => $this->faker->numberBetween(0, 5),
            'metadata' => [
                'force' => $this->faker->boolean(20),
                'started_by' => $this->faker->randomElement(['command', 'schedule', 'manual']),
            ],
            'error_message' => null,
        ];
    }

    /**
     * Create a successful sync log
     */
    public function successful(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SyncLog::STATUS_COMPLETED,
            'completed_at' => $this->faker->dateTimeBetween($attributes['started_at'], 'now'),
            'error_message' => null,
        ]);
    }

    /**
     * Create a failed sync log
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SyncLog::STATUS_FAILED,
            'completed_at' => $this->faker->dateTimeBetween($attributes['started_at'], 'now'),
            'error_message' => $this->faker->sentence(),
            'total_failed' => $this->faker->numberBetween(1, 10),
        ]);
    }

    /**
     * Create an open orders sync log
     */
    public function openOrders(): static
    {
        return $this->state(fn (array $attributes) => [
            'sync_type' => SyncLog::TYPE_OPEN_ORDERS,
        ]);
    }

    /**
     * Create a historical orders sync log
     */
    public function historicalOrders(): static
    {
        return $this->state(fn (array $attributes) => [
            'sync_type' => SyncLog::TYPE_HISTORICAL_ORDERS,
        ]);
    }

    /**
     * Create a recent sync log
     */
    public function recent(): static
    {
        return $this->state(fn (array $attributes) => [
            'started_at' => $this->faker->dateTimeBetween('-5 minutes', 'now'),
            'completed_at' => $this->faker->dateTimeBetween('-3 minutes', 'now'),
        ]);
    }

    /**
     * Create an old sync log
     */
    public function old(): static
    {
        return $this->state(fn (array $attributes) => [
            'started_at' => $this->faker->dateTimeBetween('-1 week', '-1 day'),
            'completed_at' => $this->faker->dateTimeBetween('-1 week', '-1 day'),
        ]);
    }

    /**
     * Create a sync log with specific metrics
     */
    public function withMetrics(int $fetched = 10, int $created = 2, int $updated = 3, int $skipped = 4, int $failed = 1): static
    {
        return $this->state(fn (array $attributes) => [
            'total_fetched' => $fetched,
            'total_created' => $created,
            'total_updated' => $updated,
            'total_skipped' => $skipped,
            'total_failed' => $failed,
        ]);
    }
}