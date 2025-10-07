<?php

declare(strict_types=1);

namespace App\Actions\Linnworks\Orders;

use App\Jobs\GetOpenOrderIdsJob;

final class QueueOpenOrderSync
{
    public function handle(?string $startedBy = null): void
    {
        GetOpenOrderIdsJob::dispatch($startedBy ?? 'ui');
    }
}
