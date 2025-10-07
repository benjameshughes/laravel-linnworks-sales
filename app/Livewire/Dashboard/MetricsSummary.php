<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard;

use App\Models\Order;
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
    public function dateRange(): Collection
    {
        if ($this->period === 'custom') {
            return collect([
                'start' => Carbon::parse($this->customFrom)->startOfDay(),
                'end' => Carbon::parse($this->customTo)->endOfDay(),
            ]);
        }

        if ($this->period === 'yesterday') {
            return collect([
                'start' => Carbon::yesterday()->startOfDay(),
                'end' => Carbon::yesterday()->endOfDay(),
            ]);
        }

        $days = (int) $this->period;

        return collect([
            'start' => Carbon::now()->subDays($days)->startOfDay(),
            'end' => Carbon::now()->endOfDay(),
        ]);
    }

    #[Computed]
    public function orders(): Collection
    {
        return Order::whereBetween('received_date', [
                $this->dateRange->get('start'),
                $this->dateRange->get('end')
            ])
            ->where('channel_name', '!=', 'DIRECT')
            ->when($this->channel !== 'all', fn($query) =>
                $query->where('channel_name', $this->channel)
            )
            ->when($this->searchTerm, fn($query) =>
                $query->where(function ($q) {
                    $q->where('order_number', 'like', "%{$this->searchTerm}%")
                      ->orWhere('channel_name', 'like', "%{$this->searchTerm}%");
                })
            )
            ->get();
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
        if ($this->period === 'custom') {
            $days = Carbon::parse($this->customFrom)->diffInDays(Carbon::parse($this->customTo)) + 1;
            $start = Carbon::parse($this->customFrom)->subDays($days)->startOfDay();
            $end = Carbon::parse($this->customFrom)->subDay()->endOfDay();
        } elseif ($this->period === 'yesterday') {
            $start = Carbon::now()->subDays(2)->startOfDay();
            $end = Carbon::now()->subDays(2)->endOfDay();
        } else {
            $days = (int) $this->period;
            $start = Carbon::now()->subDays($days * 2)->startOfDay();
            $end = Carbon::now()->subDays($days)->endOfDay();
        }

        return Order::whereBetween('received_date', [$start, $end])
            ->where('channel_name', '!=', 'DIRECT')
            ->when($this->channel !== 'all', fn($query) =>
                $query->where('channel_name', $this->channel)
            )
            ->get();
    }
}
