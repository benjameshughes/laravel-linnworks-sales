<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SyncCompleted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $processed,
        public int $created,
        public int $updated,
        public int $failed,
        public bool $success,
    ) {}

    public function broadcastOn(): Channel
    {
        return new Channel('sync-progress');
    }

    public function broadcastWith(): array
    {
        return [
            'processed' => $this->processed,
            'created' => $this->created,
            'updated' => $this->updated,
            'failed' => $this->failed,
            'success' => $this->success,
            'completed_at' => now()->toISOString(),
        ];
    }
}
