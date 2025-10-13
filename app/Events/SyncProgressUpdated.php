<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SyncProgressUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $stage,
        public string $message,
        public int $count = 0,
    ) {}

    public function broadcastOn(): Channel
    {
        return new Channel('sync-progress');
    }

    public function broadcastWith(): array
    {
        return [
            'stage' => $this->stage,
            'message' => $this->message,
            'count' => $this->count,
        ];
    }
}
