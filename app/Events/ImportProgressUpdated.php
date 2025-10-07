<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ImportProgressUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $totalProcessed,
        public int $totalImported,
        public int $totalSkipped,
        public int $totalErrors,
        public int $currentPage,
        public int $totalOrders,
        public string $status,
        public ?string $message = null,
    ) {}

    public function broadcastOn(): Channel
    {
        return new Channel('import-progress');
    }

    public function broadcastAs(): string
    {
        return 'ImportProgressUpdated';
    }

    public function broadcastWith(): array
    {
        $percentage = $this->totalOrders > 0
            ? min(100, round(($this->totalProcessed / $this->totalOrders) * 100, 2))
            : 0;

        return [
            'total_processed' => $this->totalProcessed,
            'total_imported' => $this->totalImported,
            'total_skipped' => $this->totalSkipped,
            'total_errors' => $this->totalErrors,
            'current_page' => $this->currentPage,
            'total_orders' => $this->totalOrders,
            'percentage' => $percentage,
            'status' => $this->status,
            'message' => $this->message,
        ];
    }
}
