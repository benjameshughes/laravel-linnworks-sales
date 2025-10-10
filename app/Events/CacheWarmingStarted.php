<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class CacheWarmingStarted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public array $periods = ['7d', '30d', '90d'],
    ) {}

    public function broadcastOn(): Channel
    {
        return new Channel('cache-management');
    }

    public function broadcastAs(): string
    {
        return 'CacheWarmingStarted';
    }

    public function broadcastWith(): array
    {
        return [
            'periods' => $this->periods,
            'started_at' => now()->toISOString(),
        ];
    }
}
