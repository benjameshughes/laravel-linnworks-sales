<?php

declare(strict_types=1);

namespace App\Actions\Calculations\Sales;

use Carbon\Carbon;

final readonly class CalculatePeriodDates
{
    /**
     * Calculate start/end dates and days for a given period
     *
     * @return array{start: Carbon, end: Carbon, days: int}
     */
    public function __invoke(
        string $period,
        ?string $customFrom = null,
        ?string $customTo = null
    ): array {
        if ($period === 'custom' && $customFrom && $customTo) {
            $start = Carbon::parse($customFrom)->startOfDay();
            $end = Carbon::parse($customTo)->endOfDay();
            $days = max(1, $start->diffInDays($end) + 1);

            return ['start' => $start, 'end' => $end, 'days' => $days];
        }

        if ($period === '1') {
            // Yesterday
            $start = Carbon::yesterday()->startOfDay();
            $end = Carbon::yesterday()->endOfDay();

            return ['start' => $start, 'end' => $end, 'days' => 1];
        }

        if ($period === '0') {
            // Today
            $start = Carbon::today()->startOfDay();
            $end = Carbon::now();

            return ['start' => $start, 'end' => $end, 'days' => 1];
        }

        // Last N days (e.g., "7", "30", "90")
        $days = max(1, (int) $period);
        $end = Carbon::now();
        $start = Carbon::now()->subDays($days)->startOfDay();

        return ['start' => $start, 'end' => $end, 'days' => $days];
    }
}
