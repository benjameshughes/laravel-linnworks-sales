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
final class ChannelDistributionChart extends Component
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
    public function chartData(): array
    {
        return $this->salesMetrics->getDoughnutChartData();
    }

    #[Computed]
    public function chartKey(): string
    {
        return "channel-doughnut-{$this->period}-{$this->channel}-{$this->searchTerm}-{$this->customFrom}-{$this->customTo}";
    }

    public function placeholder(): string
    {
        return <<<'HTML'
        <div class="bg-white dark:bg-zinc-800 rounded-xl shadow-sm border border-zinc-200 dark:border-zinc-700 p-6 animate-pulse">
            <div class="h-8 bg-zinc-200 dark:bg-zinc-700 rounded w-1/3 mb-4"></div>
            <div class="h-64 bg-zinc-100 dark:bg-zinc-700/50 rounded-full mx-auto w-64"></div>
        </div>
        HTML;
    }

    public function render()
    {
        return view('livewire.dashboard.channel-distribution-chart');
    }
}
