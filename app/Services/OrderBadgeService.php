<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\OrderBadgeType;
use App\Models\Order;
use App\ValueObjects\OrderBadge;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

readonly class OrderBadgeService
{
    public function __construct(
        private float $highValueThreshold = 100.0,
        private int $bulkItemThreshold = 10,
        private int $multiItemThreshold = 3,
        private float $highMarginThreshold = 30.0,
        private float $lowMarginThreshold = 10.0,
        private int $fastProcessingHours = 24,
        private int $repeatCustomerMinOrders = 3,
    ) {}

    /**
     * @return Collection<int, OrderBadge>
     */
    public function getOrderBadges(Order $order, int $period = 30): Collection
    {
        $cacheKey = "order_badges:{$order->id}:{$period}";

        return Cache::remember($cacheKey, now()->addHour(),
            fn () => $this->calculateBadges($order, $period)
        );
    }

    /**
     * @return Collection<int, OrderBadge>
     */
    private function calculateBadges(Order $order, int $period): Collection
    {
        $badges = collect();

        return $badges
            ->merge($this->getValueBadges($order))
            ->merge($this->getQuantityBadges($order))
            ->merge($this->getProcessingBadges($order))
            ->merge($this->getMarginBadges($order))
            ->merge($this->getCustomerBadges($order, $period))
            ->sortBy(fn (OrderBadge $badge) => $badge->priority())
            ->values();
    }

    /**
     * @return Collection<int, OrderBadge>
     */
    private function getValueBadges(Order $order): Collection
    {
        $badges = collect();

        if ($order->total_charge >= $this->highValueThreshold) {
            $badges->push(new OrderBadge(
                type: OrderBadgeType::HIGH_VALUE,
                metadata: ['order_value' => $order->total_charge]
            ));
        }

        return $badges;
    }

    /**
     * @return Collection<int, OrderBadge>
     */
    private function getQuantityBadges(Order $order): Collection
    {
        $badges = collect();
        $itemCount = $order->num_items ?? $order->orderItems()->sum('quantity');
        $uniqueProducts = $order->orderItems()->distinct('sku')->count('sku');

        if ($itemCount >= $this->bulkItemThreshold) {
            $badges->push(new OrderBadge(
                type: OrderBadgeType::BULK_ORDER,
                metadata: ['item_count' => $itemCount]
            ));
        }

        if ($uniqueProducts >= $this->multiItemThreshold) {
            $badges->push(new OrderBadge(
                type: OrderBadgeType::MULTI_ITEM,
                metadata: ['unique_products' => $uniqueProducts]
            ));
        }

        return $badges;
    }

    /**
     * @return Collection<int, OrderBadge>
     */
    private function getProcessingBadges(Order $order): Collection
    {
        $badges = collect();

        if (! $order->received_at || ! $order->processed_at) {
            return $badges;
        }

        $processingHours = $order->received_at->diffInHours($order->processed_at);
        $isSameDay = $order->received_at->isSameDay($order->processed_at);

        if ($isSameDay) {
            $badges->push(new OrderBadge(
                type: OrderBadgeType::SAME_DAY,
                metadata: ['processing_hours' => $processingHours]
            ));
        } elseif ($processingHours <= $this->fastProcessingHours) {
            $badges->push(new OrderBadge(
                type: OrderBadgeType::FAST_PROCESSING,
                metadata: ['processing_hours' => $processingHours]
            ));
        }

        return $badges;
    }

    /**
     * @return Collection<int, OrderBadge>
     */
    private function getMarginBadges(Order $order): Collection
    {
        $badges = collect();
        $margin = $this->calculateOrderMargin($order);

        if ($margin === null) {
            return $badges;
        }

        if ($margin >= $this->highMarginThreshold) {
            $badges->push(new OrderBadge(
                type: OrderBadgeType::HIGH_MARGIN,
                metadata: ['profit_margin' => $margin]
            ));
        } elseif ($margin > 0 && $margin < $this->lowMarginThreshold) {
            $badges->push(new OrderBadge(
                type: OrderBadgeType::LOW_MARGIN,
                metadata: ['profit_margin' => $margin]
            ));
        }

        return $badges;
    }

    private function calculateOrderMargin(Order $order): ?float
    {
        $order->loadMissing('orderItems');

        $totalRevenue = $order->orderItems->sum('line_total');
        $totalCost = $order->orderItems->sum(fn ($item) => ($item->unit_cost ?? 0) * $item->quantity);

        if ($totalRevenue <= 0) {
            return null;
        }

        return (($totalRevenue - $totalCost) / $totalRevenue) * 100;
    }

    /**
     * @return Collection<int, OrderBadge>
     */
    private function getCustomerBadges(Order $order, int $period): Collection
    {
        $badges = collect();

        if (! $order->source) {
            return $badges;
        }

        $orderCount = $this->getCustomerOrderCount($order, $period);

        if ($orderCount >= $this->repeatCustomerMinOrders) {
            $badges->push(new OrderBadge(
                type: OrderBadgeType::REPEAT_CUSTOMER,
                metadata: ['order_count' => $orderCount]
            ));
        }

        return $badges;
    }

    private function getCustomerOrderCount(Order $order, int $period): int
    {
        $cacheKey = "customer_order_count:{$order->source}:{$order->channel_reference_number}:{$period}";

        return Cache::remember($cacheKey, now()->addMinutes(30), function () use ($order, $period): int {
            $query = Order::query()
                ->where('source', $order->source)
                ->where('received_at', '>=', now()->subDays($period));

            if ($order->channel_reference_number) {
                $query->where('channel_reference_number', $order->channel_reference_number);
            }

            return $query->count();
        });
    }

    /**
     * Get all badge definitions for display/documentation.
     *
     * @return Collection<string, Collection<string, int|string>>
     */
    public function getBadgeDefinitions(): Collection
    {
        return collect(OrderBadgeType::cases())
            ->mapWithKeys(fn (OrderBadgeType $badge) => [
                $badge->value => collect([
                    'label' => $badge->label(),
                    'description' => $badge->description(),
                    'color' => $badge->color(),
                    'icon' => $badge->icon(),
                    'priority' => $badge->priority(),
                ]),
            ]);
    }

    /**
     * Clear cached badges for an order.
     */
    public function clearCache(Order $order): void
    {
        $periods = [7, 14, 30, 60, 90, 180, 365];

        foreach ($periods as $period) {
            Cache::forget("order_badges:{$order->id}:{$period}");
        }
    }
}
