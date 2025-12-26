<?php

declare(strict_types=1);

namespace App\Services\Analytics;

use App\Factories\Metrics\Sales\SalesFactory;
use Illuminate\Support\Collection;

final class ComparisonEngine
{
    /**
     * Compare two datasets and return comparison metrics
     */
    public function compare(Collection $currentData, Collection $previousData): ComparisonResult
    {
        $currentFactory = new SalesFactory($currentData);
        $previousFactory = new SalesFactory($previousData);

        return new ComparisonResult(
            currentRevenue: $currentFactory->totalRevenue(),
            previousRevenue: $previousFactory->totalRevenue(),
            currentOrders: $currentFactory->totalOrders(),
            previousOrders: $previousFactory->totalOrders(),
            currentAvgOrderValue: $currentFactory->averageOrderValue(),
            previousAvgOrderValue: $previousFactory->averageOrderValue(),
        );
    }

    /**
     * Compare multiple periods and return trend data
     */
    public function compareTrends(array $periods): array
    {
        return collect($periods)
            ->map(function (Collection $data, string $periodName) {
                $factory = new SalesFactory($data);

                return [
                    'period' => $periodName,
                    'revenue' => $factory->totalRevenue(),
                    'orders' => $factory->totalOrders(),
                    'avg_order_value' => $factory->averageOrderValue(),
                ];
            })
            ->toArray();
    }
}
