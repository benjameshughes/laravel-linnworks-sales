<?php

namespace App\Reports\Concerns;

use Carbon\Carbon;

trait HasDateRangeFilter
{
    protected function parseDateRange(array $filters): array
    {
        return [
            'start' => Carbon::parse($filters['date_range']['start'])->startOfDay(),
            'end' => Carbon::parse($filters['date_range']['end'])->endOfDay(),
        ];
    }

    protected function applyDateRangeFilter($query, array $filters, string $column = 'received_at', string $table = 'o'): void
    {
        $dates = $this->parseDateRange($filters);
        $query->whereBetween("{$table}.{$column}", [$dates['start'], $dates['end']]);
    }
}
