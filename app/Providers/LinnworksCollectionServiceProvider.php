<?php

namespace App\Providers;

use Illuminate\Support\Collection;
use Illuminate\Support\ServiceProvider;
use App\DataTransferObjects\LinnworksOrder;
use App\DataTransferObjects\LinnworksOrderItem;

class LinnworksCollectionServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Orders Collection Macros
        Collection::macro('totalRevenue', function () {
            return $this->sum(fn (LinnworksOrder $order) => $order->totalCharge);
        });

        Collection::macro('totalProfit', function () {
            return $this->sum(fn (LinnworksOrder $order) => $order->totalProfit());
        });

        Collection::macro('averageOrderValue', function () {
            return $this->isEmpty() ? 0 : $this->avg(fn (LinnworksOrder $order) => $order->totalCharge);
        });

        Collection::macro('byChannel', function () {
            return $this->groupBy(fn (LinnworksOrder $order) => $order->channel());
        });

        Collection::macro('bySource', function () {
            return $this->groupBy(fn (LinnworksOrder $order) => $order->orderSource ?? 'Unknown');
        });

        Collection::macro('processed', function () {
            return $this->filter(fn (LinnworksOrder $order) => $order->isProcessed());
        });

        Collection::macro('unprocessed', function () {
            return $this->filter(fn (LinnworksOrder $order) => !$order->isProcessed());
        });

        Collection::macro('fromToday', function () {
            return $this->filter(fn (LinnworksOrder $order) => 
                $order->receivedDate?->isToday() ?? false
            );
        });

        Collection::macro('fromLastDays', function (int $days) {
            $cutoff = now()->subDays($days);
            return $this->filter(fn (LinnworksOrder $order) => 
                $order->receivedDate && $order->receivedDate->gte($cutoff)
            );
        });

        Collection::macro('topProducts', function (int $limit = 10) {
            return $this
                ->flatMap(fn (LinnworksOrder $order) => $order->items)
                ->groupBy('sku')
                ->map(function (Collection $items) {
                    $firstItem = $items->first();
                    return [
                        'sku' => $firstItem->sku,
                        'title' => $firstItem->itemTitle,
                        'quantity_sold' => $items->sum('quantity'),
                        'total_revenue' => $items->sum(fn (LinnworksOrderItem $item) => $item->totalValue()),
                        'total_profit' => $items->sum(fn (LinnworksOrderItem $item) => $item->profit()),
                        'orders_count' => $items->count(),
                    ];
                })
                ->sortByDesc('quantity_sold')
                ->take($limit)
                ->values();
        });

        Collection::macro('channelPerformance', function () {
            return $this
                ->byChannel()
                ->map(function (Collection $orders, string $channel) {
                    return [
                        'channel' => $channel,
                        'orders_count' => $orders->count(),
                        'total_revenue' => $orders->totalRevenue(),
                        'total_profit' => $orders->totalProfit(),
                        'average_order_value' => $orders->averageOrderValue(),
                        'profit_margin' => $orders->totalRevenue() > 0 
                            ? ($orders->totalProfit() / $orders->totalRevenue()) * 100 
                            : 0,
                    ];
                })
                ->sortByDesc('total_revenue')
                ->values();
        });

        Collection::macro('dailySales', function () {
            return $this
                ->groupBy(fn (LinnworksOrder $order) => 
                    $order->receivedDate?->format('Y-m-d') ?? 'unknown'
                )
                ->map(function (Collection $orders, string $date) {
                    return [
                        'date' => $date,
                        'orders_count' => $orders->count(),
                        'revenue' => $orders->totalRevenue(),
                        'profit' => $orders->totalProfit(),
                        'items_sold' => $orders->sum(fn (LinnworksOrder $order) => $order->itemCount()),
                    ];
                })
                ->sortBy('date')
                ->values();
        });

        Collection::macro('revenueGrowth', function (int $days = 30) {
            $recent = $this->fromLastDays($days);
            $previous = $this->filter(function (LinnworksOrder $order) use ($days) {
                $startDate = now()->subDays($days * 2);
                $endDate = now()->subDays($days);
                return $order->receivedDate && 
                       $order->receivedDate->between($startDate, $endDate);
            });

            $recentRevenue = $recent->totalRevenue();
            $previousRevenue = $previous->totalRevenue();

            return [
                'recent_revenue' => $recentRevenue,
                'previous_revenue' => $previousRevenue,
                'growth_amount' => $recentRevenue - $previousRevenue,
                'growth_percentage' => $previousRevenue > 0 
                    ? (($recentRevenue - $previousRevenue) / $previousRevenue) * 100 
                    : 0,
            ];
        });

        // Order Items Collection Macros
        Collection::macro('totalItemsValue', function () {
            return $this->sum(fn (LinnworksOrderItem $item) => $item->totalValue());
        });

        Collection::macro('totalItemsProfit', function () {
            return $this->sum(fn (LinnworksOrderItem $item) => $item->profit());
        });

        Collection::macro('topSellingItems', function (int $limit = 10) {
            return $this
                ->groupBy('sku')
                ->map(function (Collection $items) {
                    $firstItem = $items->first();
                    return [
                        'sku' => $firstItem->sku,
                        'title' => $firstItem->itemTitle,
                        'total_sold' => $items->sum('quantity'),
                        'revenue' => $items->sum(fn (LinnworksOrderItem $item) => $item->totalValue()),
                    ];
                })
                ->sortByDesc('total_sold')
                ->take($limit)
                ->values();
        });
    }
}