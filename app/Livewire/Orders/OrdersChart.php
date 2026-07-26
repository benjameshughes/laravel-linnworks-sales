<?php

declare(strict_types=1);

namespace App\Livewire\Orders;

use Livewire\Component;
use Livewire\Attributes\On;
use Livewire\Attributes\Computed;
use Illuminate\Support\Collection;
use Illuminate\Contracts\View\View;
use App\Services\Metrics\Orders\OrderService;

/**
 * Orders Chart Island
 *
 * Displays a daily trend chart for orders and revenue.
 * Supports switching between line and bar chart views.
 *
 * Listens for 'orders-filters-updated' event to refresh data.
 *
 * @property-read Collection $chartData
 */
final class OrdersChart extends Component
{
    private ?OrderService $orderService = null;

    /**
     * Livewire cannot inject through the constructor, so dependencies are
     * resolved here - boot() runs on every request, mount and hydrate alike.
     */
    public function boot(OrderService $orderService): void
    {
        $this->orderService = $orderService;
    }

    /**
     * Livewire skips boot() on the lazy-load request, so always reach the
     * dependency through here rather than the property directly.
     */
    private function orderService(): OrderService
    {
        return $this->orderService ??= app(OrderService::class);
    }

    public string $period = '7';

    public string $channel = 'all';

    public string $status = 'all';

    public ?string $customFrom = null;

    public ?string $customTo = null;

    public string $chartType = 'line';

    public string $metric = 'revenue';

    public function mount(): void
    {
        $this->period = request('period', '7');
        $this->channel = request('channel', 'all');
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

        unset($this->chartData);
    }

    public function toggleChartType(): void
    {
        $this->chartType = $this->chartType === 'line' ? 'bar' : 'line';
    }

    public function setMetric(string $metric): void
    {
        $this->metric = $metric;
    }

    #[Computed]
    public function chartData(): Collection
    {
        return $this->orderService()->getDailyChartData(
            period: $this->period,
            channel: $this->channel !== 'all' ? $this->channel : null,
            customFrom: $this->customFrom,
            customTo: $this->customTo
        );
    }

    #[Computed]
    public function chartLabels(): array
    {
        return $this->chartData->pluck('date')->map(fn ($date) => date('M j', strtotime($date)))->toArray();
    }

    #[Computed]
    public function chartValues(): array
    {
        return match ($this->metric) {
            'orders' => $this->chartData->pluck('order_count')->toArray(),
            'items' => $this->chartData->pluck('items_sold')->toArray(),
            'avg_value' => $this->chartData->pluck('avg_value')->toArray(),
            default => $this->chartData->pluck('revenue')->toArray(),
        };
    }

    public function render(): View
    {
        return view('livewire.orders.orders-chart');
    }

    public function placeholder(array $params = []): View
    {
        return view('livewire.placeholders.chart', $params);
    }
}
