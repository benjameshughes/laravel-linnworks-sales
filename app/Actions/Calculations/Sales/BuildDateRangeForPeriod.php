<?php

declare(strict_types=1);

namespace App\Actions\Calculations\Sales;

use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;

final readonly class BuildDateRangeForPeriod
{
    /**
     * Build date range collection for the given period
     */
    public function __invoke(
        string $period,
        ?string $customFrom = null,
        ?string $customTo = null
    ): Collection {
        if ($period === 'custom' && $customFrom && $customTo) {
            $start = Carbon::parse($customFrom)->startOfDay();
            $end = Carbon::parse($customTo)->startOfDay();

            return collect(CarbonPeriod::create($start, '1 day', $end));
        }

        if ($period === '0') {
            // Today - return 3 points to center the bar in charts
            $today = Carbon::today();

            return collect([
                $today->copy()->subDay(),
                $today,
                $today->copy()->addDay(),
            ]);
        }

        if ($period === '1') {
            // Yesterday - return 3 points to center the bar in charts
            $yesterday = Carbon::yesterday();

            return collect([
                $yesterday->copy()->subDay(),
                $yesterday,
                $yesterday->copy()->addDay(),
            ]);
        }

        // Last N days
        $days = (int) max(1, $period);

        return collect(range($days - 1, 0))
            ->map(fn (int $daysAgo) => Carbon::now()->subDays($daysAgo));
    }
}
