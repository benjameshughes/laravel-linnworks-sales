<?php

namespace App\Events;

use Carbon\Carbon;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ImportStarted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Carbon $fromDate,
        public Carbon $toDate,
        public int $batchSize,
        public int $totalOrders,
    ) {}

    public function broadcastOn(): Channel
    {
        return new Channel('import-progress');
    }

    public function broadcastAs(): string
    {
        return 'ImportStarted';
    }

    public function broadcastWith(): array
    {
        return [
            'from_date' => $this->fromDate->toDateString(),
            'to_date' => $this->toDate->toDateString(),
            'batch_size' => $this->batchSize,
            'total_orders' => $this->totalOrders,
            'started_at' => now()->toISOString(),
        ];
    }
}
