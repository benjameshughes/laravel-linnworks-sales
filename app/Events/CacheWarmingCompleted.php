<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class CacheWarmingCompleted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $periodsWarmed,
        public bool $success = true,
    ) {}

    public function broadcastOn(): Channel
    {
        return new Channel('cache-management');
    }

    public function broadcastWith(): array
    {
        return [
            'periods_warmed' => $this->periodsWarmed,
            'success' => $this->success,
            'completed_at' => now()->toISOString(),
        ];
    }
}
