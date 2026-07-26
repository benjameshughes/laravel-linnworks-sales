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
 * Top Customers Island
 *
 * Displays top customers by total spend and order count.
 * Shows repeat buyer information and customer value.
 *
 * Listens for 'orders-filters-updated' event to refresh data.
 *
 * @property-read Collection $topCustomers
 */
final class TopCustomers extends Component
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

    public ?string $customFrom = null;

    public ?string $customTo = null;

    public int $limit = 10;

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
        $this->customFrom = $customFrom;
        $this->customTo = $customTo;

        unset($this->topCustomers);
    }

    #[Computed]
    public function topCustomers(): Collection
    {
        return $this->orderService()->getTopCustomers(
            period: $this->period,
            limit: $this->limit,
            customFrom: $this->customFrom,
            customTo: $this->customTo
        );
    }

    public function render(): View
    {
        return view('livewire.orders.top-customers');
    }

    public function placeholder(array $params = []): View
    {
        return view('livewire.placeholders.top-list', $params);
    }
}
