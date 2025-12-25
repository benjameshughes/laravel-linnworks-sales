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

    public function growthRate(SalesFactory $previousFactory): float
    {
        $currentRevenue = $this->totalRevenue();
        $previousRevenue = $previousFactory->totalRevenue();

        if ($previousRevenue === 0.0) {
            return $currentRevenue > 0 ? 100.0 : 0.0;
        }

        return (($currentRevenue - $previousRevenue) / $previousRevenue) * 100;
    }

    public function getBestPerformingDay(string $period, ?string $customFrom, ?string $customTo): float
    {
        // TODO: implement this, for now return a float
        return 9999.99;
    }
}
