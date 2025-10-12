<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast detailed metrics after each batch is processed
 *
 * Provides real-time performance feedback matching cache warming UI style
 */
class ImportBatchProcessed implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly int $batchNumber,
        public readonly int $totalBatches,
        public readonly int $ordersInBatch,
        public readonly int $totalProcessed,
        public readonly int $created,
        public readonly int $updated,
        public readonly float $ordersPerSecond,
        public readonly float $memoryMb,
        public readonly float $timeElapsed,
        public readonly ?float $estimatedRemaining = null,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel('orders'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'import.batch.processed';
    }

    public function broadcastWith(): array
    {
        return [
            'batch_number' => $this->batchNumber,
            'total_batches' => $this->totalBatches,
            'orders_in_batch' => $this->ordersInBatch,
            'total_processed' => $this->totalProcessed,
            'created' => $this->created,
            'updated' => $this->updated,
            'orders_per_second' => round($this->ordersPerSecond, 2),
            'memory_mb' => round($this->memoryMb, 2),
            'time_elapsed' => round($this->timeElapsed, 2),
            'estimated_remaining' => $this->estimatedRemaining ? round($this->estimatedRemaining, 2) : null,
            'percentage' => $this->totalBatches > 0 ? round(($this->batchNumber / $this->totalBatches) * 100, 1) : 0,
        ];
    }
}
