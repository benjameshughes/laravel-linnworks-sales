<?php

declare(strict_types=1);

namespace App\Factories\Metrics\Sales;

use Illuminate\Support\Collection;

final class SalesFactory
{
    public function __construct(private readonly Collection $orders) {}

    public function totalRevenue(): float
    {
        return $this->orders->sum('total_charge');
    }

    public function totalOrders(): int
    {
        return $this->orders->count();
    }

    public function averageOrderValue(): float
    {
        return $this->orders->avg('total_charge') ?? 0.0;
    }

    public function averageOrderPrice(): float
    {
        return $this->orders->avg('price');
    }

    public function averageOrderQuantity(): float
    {
        return $this->orders->avg('quantity');
    }

    public function averageOrderTotal(): float
    {
        return $this->orders->avg('total');
    }

    public function totalItemsSold(): int
    {
        return $this->orders->sum(fn ($order) => $order->orderItems->sum('quantity'));
    }

    public function totalProcessedOrders(): float
    {
        return $this->orders->where('status', 1)->count();
    }

    public function totalOpenOrders(): float
    {
        return $this->orders->where('status', 0)->count();
    }

    public function topChannels(int $limit = 3): Collection
    {
        $totalRevenue = $this->orders->sum('total_charge');

        // Group by source AND subsource to match ChunkedMetricsCalculator format
        return $this->orders->groupBy(fn ($order) => $order->source.'|'.($order->subsource ?? ''))
            ->map(function ($sourceOrders, $key) use ($totalRevenue) {
                [$source, $subsource] = explode('|', $key, 2);
                $revenue = $sourceOrders->sum('total_charge');
                $orders = $sourceOrders->count();

                // Build display name like ChunkedMetricsCalculator
                $displayName = $subsource
                    ? "{$subsource} ({$source})"
                    : $source;

                return collect([
                    'name' => $displayName,
                    'channel' => $source,
                    'subsource' => $subsource ?: null,
                    'orders' => $orders,
                    'revenue' => $revenue,
                    'avg_order_value' => $orders > 0 ? $revenue / $orders : 0,
                    'percentage' => $totalRevenue > 0 ? ($revenue / $totalRevenue) * 100 : 0,
                ]);
            })
            ->sortByDesc('revenue')
            ->take($limit)
            ->values();
    }

    public function topProducts(int $limit = 10): Collection
    {
        return $this->orders->flatMap(function ($order) {
            return $order->orderItems;
        })->groupBy('sku')->map(function ($itemsWithSameSKU, $sku) {
            return collect([
                'sku' => $sku,
                'title' => $itemsWithSameSKU->first()->item_title ?? 'Unknown Product',
                'quantity' => $itemsWithSameSKU->sum('quantity'),
                'revenue' => $itemsWithSameSKU->sum('line_total'),
            ]);
        })->sortByDesc('quantity')
            ->take($limit)
            ->values();
    }

    public function processedOrdersRevenue(): float
    {
        return $this->orders->where('status', 1)->sum('total_charge');
    }

    public function openOrdersRevenue(): float
    {
        return $this->orders->where('status', 0)->sum('total_charge');
    }

    /**
     * Get daily sales breakdown for the current orders
     */
    public function dailySalesData(): Collection
    {
        return $this->orders
            ->groupBy(fn ($order) => $order->received_at?->format('Y-m-d') ?? 'unknown')
            ->map(function ($dayOrders, $date) {
                return collect([
                    'date' => $date,
                    'revenue' => $dayOrders->sum('total_charge'),
                    'orders' => $dayOrders->count(),
                ]);
            })
            ->sortKeys()
            ->values();
    }

    public function growthRate(SalesFactory $previousFactory): float
    {
        $currentRevenue = $this->totalRevenue();
        $previousRevenue = $previousFactory->totalRevenue();

        if ($previousRevenue === 0.0) {
            return $currentRevenue > 0 ? 100.0 : 0.0;
        }

        return (($currentRevenue - $previousRevenue) / $previousRevenue) * 100;
    }

    /**
     * Get line chart data for revenue trend
     *
     * @param  string|null  $period  Optional period string (unused, data comes from factory)
     * @param  string|null  $start  Optional start date (unused, data comes from factory)
     * @param  string|null  $end  Optional end date (unused, data comes from factory)
     */
    public function getLineChartData(?string $period = null, ?string $start = null, ?string $end = null): array
    {
        $daily = $this->dailySalesData();

        return [
            'labels' => $daily->pluck('date')->toArray(),
            'datasets' => [
                [
                    'label' => 'Revenue',
                    'data' => $daily->pluck('revenue')->toArray(),
                    'borderColor' => 'rgb(59, 130, 246)',
                    'tension' => 0.1,
                ],
            ],
        ];
    }

    /**
     * Get bar chart data for order counts
     *
     * @param  string|null  $period  Optional period string (unused, data comes from factory)
     * @param  string|null  $start  Optional start date (unused, data comes from factory)
     * @param  string|null  $end  Optional end date (unused, data comes from factory)
     */
    public function getBarChartData(?string $period = null, ?string $start = null, ?string $end = null): array
    {
        $daily = $this->dailySalesData();

        return [
            'labels' => $daily->pluck('date')->toArray(),
            'datasets' => [
                [
                    'label' => 'Orders',
                    'data' => $daily->pluck('orders')->toArray(),
                    'backgroundColor' => 'rgba(59, 130, 246, 0.5)',
                ],
            ],
        ];
    }

    /**
     * Get order count chart data
     *
     * @param  string|null  $period  Optional period string (unused, data comes from factory)
     * @param  string|null  $start  Optional start date (unused, data comes from factory)
     * @param  string|null  $end  Optional end date (unused, data comes from factory)
     */
    public function getOrderCountChartData(?string $period = null, ?string $start = null, ?string $end = null): array
    {
        return [
            'labels' => ['Processed', 'Open'],
            'datasets' => [
                [
                    'data' => [(int) $this->totalProcessedOrders(), (int) $this->totalOpenOrders()],
                    'backgroundColor' => ['rgb(34, 197, 94)', 'rgb(234, 179, 8)'],
                ],
            ],
        ];
    }

    /**
     * Get doughnut chart data for channel breakdown
     */
    public function getDoughnutChartData(): array
    {
        $channels = $this->topChannels(5);

        return [
            'labels' => $channels->pluck('name')->toArray(),
            'datasets' => [
                [
                    'data' => $channels->pluck('revenue')->toArray(),
                    'backgroundColor' => [
                        'rgb(59, 130, 246)',
                        'rgb(34, 197, 94)',
                        'rgb(234, 179, 8)',
                        'rgb(239, 68, 68)',
                        'rgb(168, 85, 247)',
                    ],
                ],
            ],
        ];
    }
}
