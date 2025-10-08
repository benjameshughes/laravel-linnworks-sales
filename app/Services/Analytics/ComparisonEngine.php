<?php

declare(strict_types=1);

namespace App\Services\Analytics;

use App\Services\Metrics\SalesMetrics;
use Illuminate\Support\Collection;

final class ComparisonEngine
{
    /**
     * Compare two datasets and return comparison metrics
     */
    public function compare(Collection $currentData, Collection $previousData): ComparisonResult
    {
        $currentMetrics = new SalesMetrics($currentData);
        $previousMetrics = new SalesMetrics($previousData);

        return new ComparisonResult(
            currentRevenue: $currentMetrics->totalRevenue(),
            previousRevenue: $previousMetrics->totalRevenue(),
            currentOrders: $currentMetrics->totalOrders(),
            previousOrders: $previousMetrics->totalOrders(),
            currentAvgOrderValue: $currentMetrics->averageOrderValue(),
            previousAvgOrderValue: $previousMetrics->averageOrderValue(),
        );
    }

    /**
     * Compare multiple periods and return trend data
     */
    public function compareTrends(array $periods): array
    {
        return collect($periods)
            ->map(function (Collection $data, string $periodName) {
                $metrics = new SalesMetrics($data);

                return [
                    'period' => $periodName,
                    'revenue' => $metrics->totalRevenue(),
                    'orders' => $metrics->totalOrders(),
                    'avg_order_value' => $metrics->averageOrderValue(),
                ];
            })
            ->toArray();
    }
}
