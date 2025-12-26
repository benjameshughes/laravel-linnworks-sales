<?php

declare(strict_types=1);

namespace App\Livewire\Orders;

use App\Enums\Period;
use App\Services\Metrics\Orders\OrderService;
use Carbon\Carbon;
use Flux\DateRange;
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
 * @property-read string $formattedDateRange
 */
final class OrderFilters extends Component
{
    public string $period = '7';

    public string $channel = 'all';

    public string $status = 'all';

    public string $search = '';

    public ?string $customFrom = null;

    public ?string $customTo = null;

    public ?DateRange $dateRange = null;

    public function mount(): void
    {
        $this->period = request('period', '7');
        $this->channel = request('channel', 'all');
        $this->status = request('status', 'all');

        // Initialize date range for the Flux date picker (default to last 7 days)
        $this->dateRange = new DateRange(
            Carbon::now()->subDays(7)->startOfDay(),
            Carbon::now()->endOfDay()
        );

        $this->dispatchFiltersUpdated();
    }

    public function updated(string $property): void
    {
        // Don't auto-dispatch for dateRange changes - wait for applyCustomRange
        if (in_array($property, ['channel', 'status'])) {
            $this->dispatchFiltersUpdated();
        }

        if ($property === 'search') {
            $this->dispatchFiltersUpdated();
        }
    }

    /**
     * Sync the DateRange object to the customFrom/customTo properties.
     */
    private function syncDateRangeToProperties(): void
    {
        if ($this->dateRange) {
            $this->customFrom = $this->dateRange->start()->format('Y-m-d');
            $this->customTo = $this->dateRange->end()->format('Y-m-d');
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
        if (! $this->dateRange) {
            return;
        }

        // Sync the DateRange to our string properties
        $this->syncDateRangeToProperties();

        // Switch to custom period mode
        $this->period = 'custom';

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

        // Sync the DateRange object if custom dates are provided
        if ($customFrom && $customTo) {
            $this->dateRange = new DateRange(
                Carbon::parse($customFrom)->startOfDay(),
                Carbon::parse($customTo)->endOfDay()
            );
        }

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
    public function formattedDateRange(): string
    {
        if ($this->dateRange) {
            $start = $this->dateRange->start();
            $end = $this->dateRange->end();

            // Same year - don't repeat it
            if ($start->year === $end->year && $start->year === now()->year) {
                return $start->format('M j').' - '.$end->format('M j');
            }

            return $start->format('M j, Y').' - '.$end->format('M j, Y');
        }

        return 'Select dates';
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
