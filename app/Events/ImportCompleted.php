<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ImportCompleted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $totalProcessed,
        public int $totalImported,
        public int $totalSkipped,
        public int $totalErrors,
        public bool $success,
    ) {}

    public function broadcastOn(): Channel
    {
        return new Channel('import-progress');
    }

    public function broadcastAs(): string
    {
        return 'ImportCompleted';
    }

    public function broadcastWith(): array
    {
        return [
            'total_processed' => $this->totalProcessed,
            'total_imported' => $this->totalImported,
            'total_skipped' => $this->totalSkipped,
            'total_errors' => $this->totalErrors,
            'success' => $this->success,
            'completed_at' => now()->toISOString(),
        ];
    }
}
