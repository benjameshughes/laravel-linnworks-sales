<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

class ImportBatchProcessed
{
    use Dispatchable;

    public function __construct(
        public int $batchNumber,
        public int $totalBatches,
        public int $ordersInBatch,
        public int $totalProcessed,
        public int $created,
        public int $updated,
        public float $ordersPerSecond,
        public float $memoryMb,
        public float $timeElapsed,
        public ?float $estimatedRemaining
    ) {}
}
