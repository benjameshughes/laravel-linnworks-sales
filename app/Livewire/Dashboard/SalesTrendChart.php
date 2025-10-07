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
final class SalesTrendChart extends Component
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
            ->orderByDesc('received_date')
            ->get();
    }

    #[Computed]
    public function salesMetrics(): SalesMetrics
    {
        return new SalesMetrics($this->orders);
    }

    #[Computed]
    public function chartData(): array
    {
        return $this->salesMetrics->getLineChartData($this->period);
    }

    #[Computed]
    public function periodLabel(): string
    {
        if ($this->period === 'custom') {
            return 'Custom: ' . Carbon::parse($this->customFrom)->format('M j') . ' - ' . Carbon::parse($this->customTo)->format('M j, Y');
        }

        return match ($this->period) {
            '1' => 'Last 24 hours',
            'yesterday' => 'Yesterday',
            '7' => 'Last 7 days',
            '30' => 'Last 30 days',
            '90' => 'Last 90 days',
            default => "Last {$this->period} days",
        };
    }

    #[Computed]
    public function chartKey(): string
    {
        return "sales-trend-{$this->period}-{$this->channel}-{$this->searchTerm}-{$this->customFrom}-{$this->customTo}";
    }

    public function placeholder(): string
    {
        return <<<'HTML'
        <div class="bg-white dark:bg-zinc-800 rounded-xl shadow-sm border border-zinc-200 dark:border-zinc-700 p-6 animate-pulse">
            <div class="h-8 bg-zinc-200 dark:bg-zinc-700 rounded w-1/4 mb-4"></div>
            <div class="h-64 bg-zinc-100 dark:bg-zinc-700/50 rounded"></div>
        </div>
        HTML;
    }

    public function render()
    {
        return view('livewire.dashboard.sales-trend-chart');
    }
}
