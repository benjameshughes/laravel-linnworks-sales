<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class CachePeriodWarmed implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $period,
        public int $orders,
        public float $revenue,
        public int $items,
    ) {}

    public function broadcastOn(): Channel
    {
        return new Channel('cache-management');
    }

    public function broadcastAs(): string
    {
        return 'CachePeriodWarmed';
    }

    public function broadcastWith(): array
    {
        return [
            'period' => $this->period,
            'orders' => $this->orders,
            'revenue' => $this->revenue,
            'items' => $this->items,
            'warmed_at' => now()->toISOString(),
        ];
    }
}
