<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard;

use App\Services\Metrics\Sales\SalesMetrics as SalesMetricsService;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

final class TopProducts extends Component
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

    #[On('filters-updated')]
    public function updateFilters(
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
    }

    #[Computed]
    public function topProducts(): Collection
    {
        return app(SalesMetricsService::class)->getTopProducts(
            period: $this->period,
            channel: $this->channel,
            limit: 10,
            customFrom: $this->customFrom,
            customTo: $this->customTo
        );
    }

    public function render()
    {
        return view('livewire.dashboard.top-products');
    }

    /**
     * Skeleton loader shown while lazy loading
     */
    public function placeholder(array $params = [])
    {
        return view('livewire.placeholders.top-list', $params);
    }
}
