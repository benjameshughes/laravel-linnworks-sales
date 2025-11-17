<?php

declare(strict_types=1);

namespace App\Factories\Metrics\Sales;

use App\Models\Order;
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
        return $this->orders->sum(fn ($order) => collect($order->items)->sum('quantity'));
    }

    public function totalProcessedOrders(): float
    {
        return $this->orders->where('is_processed', 1 | true)->count();
    }

    public function totalOpenOrders(): float
    {
        return $this->orders->where('is_processed', 0 | false)->count();
    }

    public function topChannels(int $limit = 3): Collection
    {
        // Get the channels for each order. Calculate the total revenue and amount of orders for each channel. Sort by largest first and take the top 3
        return $this->orders->groupBy('source')->map(function ($sourceOrders, $sourceName) {
            return collect([
                'source' => $sourceName,
                'revenue' => $sourceOrders->sum('total_charge'),
                'order_count' => $sourceOrders->count(),
            ]);
        })
            ->sortByDesc('revenue')
            ->take($limit)
            ->values();
    }

    public function topProducts(int $limit = 10): Collection
    {
        return $this->orders->flatMap(function ($order) {
            return $order->items ?? [];
        })->groupBy('sku')->map(function ($itemWithSameSKU, $sku) {
            return collect([
                'sku' => $sku,
                'quantity' => $itemWithSameSKU->sum('quantity'),
            ]);
        })->sortByDesc('quantity')
            ->take($limit)
            ->values();
    }

    public function processedOrdersRevenue(): float
    {
        return $this->orders->where('is_processed', 1)->sum('total_charge');
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
