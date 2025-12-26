<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

class ImportCompleted
{
    use Dispatchable;

    public function __construct(
        public int $totalProcessed,
        public int $totalImported,
        public int $totalSkipped,
        public int $totalErrors,
        public bool $success
    ) {}
}
