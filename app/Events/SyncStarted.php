<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

final class SyncStarted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $openWindowDays,
        public int $processedWindowDays,
    ) {}

    public function broadcastOn(): Channel
    {
        return new Channel('sync-progress');
    }

    public function broadcastWith(): array
    {
        return [
            'open_window_days' => $this->openWindowDays,
            'processed_window_days' => $this->processedWindowDays,
            'started_at' => now()->toISOString(),
        ];
    }
}
