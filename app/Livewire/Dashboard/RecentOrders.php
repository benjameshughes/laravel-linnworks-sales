<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard;

use App\Services\Dashboard\DashboardDataService;
use App\Services\Metrics\SalesMetrics;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Lazy;
use Livewire\Attributes\On;
use Livewire\Component;

#[Lazy]
final class RecentOrders extends Component
{
    public string $period = '7';
    public string $channel = 'all';
    public string $searchTerm = '';
    public ?string $customFrom = null;
    public ?string $customTo = null;

    public function mount(): void
    {
        $this->period = request('period', '7');
        $this->channel = request('channel', 'all');
        $this->searchTerm = request('search', '');
    }

    #[On('filters-updated')]
    public function updateFilters(
        string $period,
        string $channel,
        string $searchTerm = '',
        ?string $customFrom = null,
        ?string $customTo = null
    ): void {
        $this->period = $period;
        $this->channel = $channel;
        $this->searchTerm = $searchTerm;
        $this->customFrom = $customFrom;
        $this->customTo = $customTo;
    }

    #[Computed]
    public function orders(): Collection
    {
        return app(DashboardDataService::class)->getOrders(
            period: $this->period,
            channel: $this->channel,
            searchTerm: $this->searchTerm,
            customFrom: $this->customFrom,
            customTo: $this->customTo
        );
    }

    #[Computed]
    public function salesMetrics(): SalesMetrics
    {
        return new SalesMetrics($this->orders);
    }

    #[Computed]
    public function recentOrders(): Collection
    {
        return $this->salesMetrics->recentOrders(15);
    }

    #[Computed]
    public function totalOrders(): int
    {
        return $this->orders->count();
    }

    public function placeholder(): string
    {
        return <<<'HTML'
        <div class="bg-white dark:bg-zinc-800 rounded-xl shadow-sm border border-zinc-200 dark:border-zinc-700 animate-pulse">
            <div class="p-6 border-b border-zinc-200 dark:border-zinc-700">
                <div class="h-6 bg-zinc-200 dark:bg-zinc-700 rounded w-1/4"></div>
            </div>
            <div class="p-6 space-y-4">
                <div class="h-12 bg-zinc-100 dark:bg-zinc-700/50 rounded"></div>
                <div class="h-12 bg-zinc-100 dark:bg-zinc-700/50 rounded"></div>
                <div class="h-12 bg-zinc-100 dark:bg-zinc-700/50 rounded"></div>
                <div class="h-12 bg-zinc-100 dark:bg-zinc-700/50 rounded"></div>
                <div class="h-12 bg-zinc-100 dark:bg-zinc-700/50 rounded"></div>
            </div>
        </div>
        HTML;
    }

    public function render()
    {
        return view('livewire.dashboard.recent-orders');
    }
}
