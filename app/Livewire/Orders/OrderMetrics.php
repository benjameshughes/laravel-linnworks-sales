<?php

declare(strict_types=1);

namespace App\Livewire\Orders;

use App\Services\Metrics\Orders\OrderService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Order Metrics Island
 *
 * Displays KPI cards with comparison indicators:
 * - Total Orders, Revenue, Avg Order Value, Total Profit
 * - Shows period-over-period change percentages
 *
 * Listens for 'orders-filters-updated' event to refresh data.
 *
 * @property-read Collection $metrics
 * @property-read Collection $comparison
 */
final class OrderMetrics extends Component
{
    public string $period = '7';

    public string $channel = 'all';

    public string $status = 'all';

    public ?string $customFrom = null;

    public ?string $customTo = null;

    public function mount(): void
    {
        $this->period = request('period', '7');
        $this->channel = request('channel', 'all');
        $this->status = request('status', 'all');
    }

    #[On('orders-filters-updated')]
    public function updateFilters(
        string $period,
        string $channel = 'all',
        string $status = 'all',
        string $search = '',
        ?string $customFrom = null,
        ?string $customTo = null
    ): void {
        $this->period = $period;
        $this->channel = $channel;
        $this->status = $status;
        $this->customFrom = $customFrom;
        $this->customTo = $customTo;

        unset($this->metrics, $this->comparison);
    }

    #[Computed]
    public function metrics(): Collection
    {
        return app(OrderService::class)->getOrderMetrics(
            period: $this->period,
            channel: $this->channel !== 'all' ? $this->channel : null,
            status: $this->status !== 'all' ? $this->status : null,
            customFrom: $this->customFrom,
            customTo: $this->customTo
        );
    }

    #[Computed]
    public function comparison(): Collection
    {
        return app(OrderService::class)->getComparisonMetrics(
            period: $this->period,
            channel: $this->channel !== 'all' ? $this->channel : null,
            customFrom: $this->customFrom,
            customTo: $this->customTo
        );
    }

    public function render()
    {
        return view('livewire.orders.order-metrics');
    }

    public function placeholder(array $params = []): View
    {
        return view('livewire.placeholders.order-metrics', $params);
    }
}
