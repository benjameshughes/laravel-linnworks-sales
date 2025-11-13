<?php

namespace App\Reports\Concerns;

trait ExcludesCancelledOrders
{
    protected function excludeCancelledOrders($query, string $table = 'o'): void
    {
        $query->where("{$table}.status", '!=', 'cancelled');
    }
}
