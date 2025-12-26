<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

class ImportProgressUpdated
{
    use Dispatchable;

    public function __construct(
        public int $totalProcessed,
        public int $totalImported,
        public int $totalSkipped,
        public int $totalErrors,
        public int $currentPage,
        public int $totalOrders,
        public string $status,
        public ?string $message = null
    ) {}
}
