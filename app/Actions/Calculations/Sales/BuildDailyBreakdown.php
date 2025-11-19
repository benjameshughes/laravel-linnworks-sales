<?php

declare(strict_types=1);

namespace App\Actions\Calculations\Sales;

use Carbon\Carbon;
use Illuminate\Support\Collection;

final readonly class BuildDailyBreakdown
{
    /**
     * Build daily breakdown for chart data from orders and date range
     */
    public function __invoke(Collection $orders, Collection $dateRange): Collection
    {
        // Initialize empty data structure for each date
        $dailyData = [];

        foreach ($dateRange as $date) {
            $dailyData[$date->format('Y-m-d')] = [
                'date' => $date->format('M j, Y'),
                'iso_date' => $date->format('Y-m-d'),
                'day' => $date->format('D'),
                'revenue' => 0.0,
                'orders' => 0,
                'items' => 0,
            ];
        }

        // Single pass through orders - bucket by date
        foreach ($orders as $order) {
            if (! $order->received_at) {
                continue;
            }

            $dateKey = $order->received_at instanceof Carbon
                ? $order->received_at->format('Y-m-d')
                : Carbon::parse($order->received_at)->format('Y-m-d');

            if (! isset($dailyData[$dateKey])) {
                continue;
            }

            $revenue = (float) $order->total_charge;
            $itemsCount = $order->orderItems->sum('quantity');

            $dailyData[$dateKey]['revenue'] += $revenue;
            $dailyData[$dateKey]['orders']++;
            $dailyData[$dateKey]['items'] += $itemsCount;
        }

        // Convert to collection and calculate avg_order_value
        return collect($dailyData)->map(function (array $day) {
            $day['avg_order_value'] = $day['orders'] > 0 ? $day['revenue'] / $day['orders'] : 0;

            return collect($day);
        })->values();
    }
}
