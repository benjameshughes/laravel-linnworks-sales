<?php

declare(strict_types=1);

namespace App\Livewire\Orders;

use App\Enums\Period;
use App\Services\Metrics\Orders\OrderService;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Order Filters Component
 *
 * Handles search, period selection, channel and status filtering.
 * Dispatches 'orders-filters-updated' event when filters change.
 *
 * @property-read Collection $channels
 * @property-read Collection $periods
 */
final class OrderFilters extends Component
{
    public string $period = '7';

    public string $channel = 'all';

    public string $status = 'all';

    public string $search = '';

    public ?string $customFrom = null;

    public ?string $customTo = null;

    public bool $showCustomRange = false;

    public function mount(): void
    {
        $this->period = request('period', '7');
        $this->channel = request('channel', 'all');
        $this->status = request('status', 'all');
        $this->dispatchFiltersUpdated();
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['period', 'channel', 'status'])) {
            $this->customFrom = null;
            $this->customTo = null;
            $this->showCustomRange = false;
            $this->dispatchFiltersUpdated();
        }

        if ($property === 'search') {
            $this->dispatchFiltersUpdated();
        }
    }

    private function dispatchFiltersUpdated(): void
    {
        $this->dispatch('orders-filters-updated',
            period: $this->period,
            channel: $this->channel,
            status: $this->status,
            search: $this->search,
            customFrom: $this->customFrom,
            customTo: $this->customTo
        );
    }

    public function applyCustomRange(): void
    {
        if (! $this->customFrom || ! $this->customTo) {
            return;
        }

        $this->period = 'custom';
        $this->showCustomRange = true;
        $this->dispatchFiltersUpdated();
    }

    public function clearCustomRange(): void
    {
        $this->customFrom = null;
        $this->customTo = null;
        $this->period = '7';
        $this->showCustomRange = false;
        $this->dispatchFiltersUpdated();
    }

    public function clearSearch(): void
    {
        $this->search = '';
        $this->dispatchFiltersUpdated();
    }

    public function refresh(): void
    {
        $this->dispatchFiltersUpdated();
    }

    #[On('filters-updated')]
    public function syncFromDashboard(
        string $period,
        string $channel,
        string $status = 'all',
        ?string $customFrom = null,
        ?string $customTo = null
    ): void {
        $this->period = $period;
        $this->channel = $channel;
        $this->status = $status;
        $this->customFrom = $customFrom;
        $this->customTo = $customTo;
        $this->showCustomRange = $customFrom !== null && $customTo !== null;
        $this->dispatchFiltersUpdated();
    }

    #[Computed]
    public function channels(): Collection
    {
        return collect(['all' => 'All Channels'])
            ->merge(
                app(OrderService::class)->getUniqueChannels()
                    ->mapWithKeys(fn ($channel) => [$channel => $channel])
            );
    }

    #[Computed]
    public function periods(): Collection
    {
        return collect(Period::cases())
            ->filter(fn (Period $period) => $period->isCacheable() || $period === Period::CUSTOM)
            ->map(fn (Period $period) => [
                'value' => $period->value,
                'label' => $period->label(),
            ]);
    }

    #[Computed]
    public function periodLabel(): string
    {
        if ($this->period === 'custom' && $this->customFrom && $this->customTo) {
            return date('M j', strtotime($this->customFrom)).' - '.date('M j', strtotime($this->customTo));
        }

        $periodEnum = Period::tryFrom($this->period);

        return $periodEnum?->label() ?? "Last {$this->period} days";
    }

    public function render()
    {
        return view('livewire.orders.order-filters');
    }
}
