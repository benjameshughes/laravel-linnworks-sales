<?php

declare(strict_types=1);

namespace App\Events;

use Carbon\Carbon;
use Illuminate\Foundation\Events\Dispatchable;

class ImportStarted
{
    use Dispatchable;

    public function __construct(
        public Carbon $from,
        public Carbon $to,
        public int $batchSize,
        public int $totalOrders
    ) {}
}
