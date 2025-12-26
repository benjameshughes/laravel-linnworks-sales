<?php

declare(strict_types=1);

namespace App\Livewire\Orders;

use App\Services\Metrics\Orders\OrderService;
use App\Services\OrderBadgeService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Orders Table Island
 *
 * Paginated, searchable, sortable table of orders.
 * Displays order badges and allows navigation to order detail.
 *
 * Listens for 'orders-filters-updated' event to refresh data.
 *
 * @property-read LengthAwarePaginator $orders
 */
final class OrdersTable extends Component
{
    use WithPagination;

    public string $period = '7';

    public string $channel = 'all';

    public string $status = 'all';

    public string $search = '';

    public ?string $customFrom = null;

    public ?string $customTo = null;

    #[Url]
    public string $sortBy = 'received_at';

    #[Url]
    public string $sortDirection = 'desc';

    public int $perPage = 25;

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
        $this->search = $search;
        $this->customFrom = $customFrom;
        $this->customTo = $customTo;
        $this->resetPage();

        unset($this->orders);
    }

    public function sortBy(string $column): void
    {
        if ($this->sortBy === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDirection = 'desc';
        }
    }

    public function viewOrder(string $orderNumber): void
    {
        $this->redirect(route('orders.detail', $orderNumber), navigate: true);
    }

    #[Computed]
    public function orders(): LengthAwarePaginator
    {
        return app(OrderService::class)->getPaginatedOrders(
            period: $this->period,
            channel: $this->channel !== 'all' ? $this->channel : null,
            status: $this->status !== 'all' ? $this->status : null,
            search: $this->search ?: null,
            sortBy: $this->sortBy,
            sortDirection: $this->sortDirection,
            perPage: $this->perPage,
            customFrom: $this->customFrom,
            customTo: $this->customTo
        );
    }

    public function getOrderBadges($order): array
    {
        $badgeService = app(OrderBadgeService::class);
        $badges = $badgeService->getOrderBadges($order, (int) $this->period);

        return $badges->take(3)->map(fn ($badge) => $badge->toArray())->toArray();
    }

    public function render()
    {
        return view('livewire.orders.orders-table');
    }

    public function placeholder(array $params = []): View
    {
        return view('livewire.placeholders.orders-table', $params);
    }
}
