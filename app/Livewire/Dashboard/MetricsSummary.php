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
final class MetricsSummary extends Component
{
    public string $period = '7';
    public string $channel = 'all';
    public string $searchTerm = '';
    public ?string $customFrom = null;
    public ?string $customTo = null;

    public function mount(): void
    {
        // Initialize from query params if available
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
        // Use shared singleton service - loads data ONCE per request
        // All islands share same orders collection = massive memory savings
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
    public function metrics(): Collection
    {
        if ($this->period === 'custom') {
            $periodDays = Carbon::parse($this->customFrom)->diffInDays(Carbon::parse($this->customTo)) + 1;
        } elseif ($this->period === 'yesterday') {
            $periodDays = 1;
        } else {
            $periodDays = (int) $this->period;
        }

        $previousPeriodData = $this->getPreviousPeriodOrders();

        return $this->salesMetrics->getMetricsSummary($periodDays, $previousPeriodData);
    }

    public function placeholder(): string
    {
        return <<<'HTML'
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl shadow-sm p-6 animate-pulse h-32"></div>
            <div class="bg-gradient-to-br from-emerald-500 to-emerald-600 rounded-xl shadow-sm p-6 animate-pulse h-32"></div>
            <div class="bg-gradient-to-br from-purple-500 to-purple-600 rounded-xl shadow-sm p-6 animate-pulse h-32"></div>
            <div class="bg-gradient-to-br from-orange-500 to-orange-600 rounded-xl shadow-sm p-6 animate-pulse h-32"></div>
        </div>
        HTML;
    }

    public function render()
    {
        return view('livewire.dashboard.metrics-summary');
    }

    private function getPreviousPeriodOrders(): Collection
    {
        // Also uses shared service for previous period data
        return app(DashboardDataService::class)->getPreviousPeriodOrders(
            period: $this->period,
            channel: $this->channel,
            customFrom: $this->customFrom,
            customTo: $this->customTo
        );
    }
}
