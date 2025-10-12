<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast aggregate performance metrics during import
 *
 * Sent every few batches to update overall performance stats
 */
class ImportPerformanceUpdate implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly int $totalProcessed,
        public readonly int $created,
        public readonly int $updated,
        public readonly int $failed,
        public readonly float $avgSpeed,
        public readonly float $peakMemory,
        public readonly float $duration,
        public readonly string $currentOperation,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel('orders'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'import.performance.update';
    }

    public function broadcastWith(): array
    {
        return [
            'total_processed' => $this->totalProcessed,
            'created' => $this->created,
            'updated' => $this->updated,
            'failed' => $this->failed,
            'avg_speed' => round($this->avgSpeed, 2),
            'peak_memory' => round($this->peakMemory, 2),
            'duration' => round($this->duration, 2),
            'current_operation' => $this->currentOperation,
        ];
    }
}
