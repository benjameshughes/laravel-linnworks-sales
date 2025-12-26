<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\Order;
use App\Services\Metrics\Orders\OrderService;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Order Detail Page
 *
 * Displays comprehensive order information including:
 * - Order header with badges
 * - Financial summary (revenue, cost, profit, margin)
 * - Order timeline
 * - Items breakdown
 * - Related orders
 *
 * @property-read Collection|null $orderDetail
 * @property-read Collection $orderItems
 * @property-read array $profitAnalysis
 * @property-read Collection $timeline
 * @property-read Collection $orderBadges
 * @property-read Collection $relatedOrders
 */
#[Layout('components.layouts.app')]
#[Title('Order Detail')]
final class OrderDetail extends Component
{
    public string $orderNumber;

    public int $period = 30;

    public ?Order $order = null;

    public function mount(string $number): void
    {
        $this->orderNumber = $number;
        $this->order = Order::where('number', $number)
            ->with(['orderItems', 'shipping', 'notes'])
            ->first();

        if (! $this->order) {
            abort(404, 'Order not found');
        }
    }

    /**
     * Main order detail from OrderService.
     */
    #[Computed]
    public function orderDetail(): ?Collection
    {
        return app(OrderService::class)->getOrderDetail(
            orderNumber: $this->orderNumber,
            period: $this->period
        );
    }

    /**
     * Order items for display.
     */
    #[Computed]
    public function orderItems(): Collection
    {
        return $this->order->orderItems->map(fn ($item) => [
            'sku' => $item->sku,
            'title' => $item->item_title ?? 'Unknown Product',
            'quantity' => (int) $item->quantity,
            'price_per_unit' => (float) $item->price_per_unit,
            'line_total' => (float) $item->line_total,
            'unit_cost' => (float) ($item->unit_cost ?? 0),
            'profit' => $item->profit,
            'profit_margin' => $item->profitMargin,
            'category' => $item->category_name,
        ]);
    }

    /**
     * Profit analysis - derived from order detail.
     */
    #[Computed]
    public function profitAnalysis(): array
    {
        $detail = $this->orderDetail;

        if (! $detail || ! isset($detail['profit_analysis'])) {
            return [
                'total_revenue' => (float) $this->order->total_charge,
                'total_cost' => 0,
                'profit' => 0,
                'margin_percentage' => 0,
            ];
        }

        return $detail['profit_analysis'];
    }

    /**
     * Order timeline - derived from order detail.
     */
    #[Computed]
    public function timeline(): Collection
    {
        $detail = $this->orderDetail;

        if (! $detail || ! isset($detail['timeline'])) {
            return collect();
        }

        return $detail['timeline'];
    }

    /**
     * Order badges.
     */
    #[Computed]
    public function orderBadges(): Collection
    {
        $detail = $this->orderDetail;

        if (! $detail || ! isset($detail['badges'])) {
            return collect();
        }

        return collect($detail['badges']);
    }

    /**
     * Related orders.
     */
    #[Computed]
    public function relatedOrders(): Collection
    {
        $detail = $this->orderDetail;

        if (! $detail || ! isset($detail['related_orders'])) {
            return collect();
        }

        return $detail['related_orders']->map(fn ($order) => [
            'number' => $order->number,
            'date' => $order->received_at?->format('M j, Y'),
            'channel' => $order->source,
            'total' => (float) $order->total_charge,
            'items' => (int) $order->num_items,
        ]);
    }

    /**
     * Order header info.
     */
    #[Computed]
    public function orderInfo(): array
    {
        return [
            'number' => $this->order->number,
            'channel' => $this->order->source ?? 'Unknown',
            'subsource' => $this->order->subsource,
            'status' => $this->order->isProcessed() ? 'Processed' : 'Open',
            'is_paid' => $this->order->is_paid,
            'is_cancelled' => $this->order->is_cancelled,
            'total_charge' => (float) $this->order->total_charge,
            'postage_cost' => (float) ($this->order->postage_cost ?? 0),
            'tax' => (float) ($this->order->tax ?? 0),
            'currency' => $this->order->currency ?? 'GBP',
            'num_items' => (int) ($this->order->num_items ?? $this->order->orderItems->sum('quantity')),
            'received_at' => $this->order->received_at?->format('M j, Y H:i'),
            'processed_at' => $this->order->processed_at?->format('M j, Y H:i'),
            'paid_at' => $this->order->paid_at?->format('M j, Y H:i'),
            'channel_reference' => $this->order->channel_reference_number,
            'payment_method' => $this->order->payment_method,
        ];
    }

    /**
     * Processing time calculation.
     */
    #[Computed]
    public function processingTime(): ?array
    {
        if (! $this->order->received_at || ! $this->order->processed_at) {
            return null;
        }

        $hours = $this->order->received_at->diffInHours($this->order->processed_at);
        $isSameDay = $this->order->received_at->isSameDay($this->order->processed_at);

        return [
            'hours' => $hours,
            'formatted' => $hours < 24
                ? "{$hours} hours"
                : round($hours / 24, 1).' days',
            'is_fast' => $hours <= 24,
            'is_same_day' => $isSameDay,
        ];
    }

    public function updatedPeriod(): void
    {
        unset($this->orderDetail, $this->orderBadges, $this->relatedOrders);
        $this->dispatch('period-changed');
    }

    public function render()
    {
        return view('livewire.order-detail');
    }
}
