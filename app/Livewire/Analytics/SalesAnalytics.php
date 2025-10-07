<?php

declare(strict_types=1);

namespace App\Livewire\Analytics;

use App\Models\Order;
use App\Services\Metrics\SalesMetrics;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Livewire\Component;
use Livewire\WithPagination;

class SalesAnalytics extends Component
{
    use WithPagination;

    // Date range filters
    public string $startDate = '';
    public string $endDate = '';
    public string $datePreset = 'last_30_days';

    // Advanced filters
    public array $selectedChannels = [];
    public string $sortBy = 'received_date';
    public string $sortDirection = 'desc';
    public int $perPage = 50;

    // Chart settings
    public string $chartType = 'line';
    public string $chartMetric = 'revenue';
    public string $chartGrouping = 'daily';

    // View settings
    public string $activeTab = 'overview';

    protected $queryString = [
        'startDate' => ['except' => ''],
        'endDate' => ['except' => ''],
        'datePreset' => ['except' => 'last_30_days'],
        'selectedChannels' => ['except' => []],
        'sortBy' => ['except' => 'received_date'],
        'sortDirection' => ['except' => 'desc'],
        'perPage' => ['except' => 50],
        'activeTab' => ['except' => 'overview'],
    ];

    public function mount(): void
    {
        $this->applyDatePreset();
        
        // Default to all channels selected
        if (empty($this->selectedChannels)) {
            $this->selectedChannels = $this->availableChannels->toArray();
        }
    }

    public function updatedDatePreset(): void
    {
        $this->applyDatePreset();
    }

    public function applyDatePreset(): void
    {
        $now = Carbon::now();
        
        match ($this->datePreset) {
            'last_7_days' => [
                $this->startDate = $now->subDays(7)->format('Y-m-d'),
                $this->endDate = $now->format('Y-m-d'),
            ],
            'last_30_days' => [
                $this->startDate = $now->subDays(30)->format('Y-m-d'),
                $this->endDate = $now->format('Y-m-d'),
            ],
            'last_90_days' => [
                $this->startDate = $now->subDays(90)->format('Y-m-d'),
                $this->endDate = $now->format('Y-m-d'),
            ],
            'last_6_months' => [
                $this->startDate = $now->subMonths(6)->format('Y-m-d'),
                $this->endDate = $now->format('Y-m-d'),
            ],
            'last_year' => [
                $this->startDate = $now->subYear()->format('Y-m-d'),
                $this->endDate = $now->format('Y-m-d'),
            ],
            'year_to_date' => [
                $this->startDate = $now->startOfYear()->format('Y-m-d'),
                $this->endDate = $now->format('Y-m-d'),
            ],
            'custom' => null,
            default => [
                $this->startDate = $now->subDays(30)->format('Y-m-d'),
                $this->endDate = $now->format('Y-m-d'),
            ],
        };
    }

    public function resetFilters(): void
    {
        $this->reset([
            'sortBy',
            'sortDirection',
            'perPage',
        ]);
        $this->datePreset = 'last_30_days';
        $this->applyDatePreset();
        
        // Reset to all channels selected
        $this->selectedChannels = $this->availableChannels->toArray();
    }

    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
    }

    public function setSortBy(string $field): void
    {
        if ($this->sortBy === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $field;
            $this->sortDirection = 'desc';
        }
    }

    public function getOrdersProperty(): Collection
    {
        $query = Order::query()
            ->when($this->startDate, fn($q) => $q->where('received_date', '>=', $this->startDate))
            ->when($this->endDate, fn($q) => $q->where('received_date', '<=', $this->endDate))
            ->when(!empty($this->selectedChannels), fn($q) => $q->whereIn('channel_name', $this->selectedChannels));

        return $query->orderBy($this->sortBy, $this->sortDirection)->get();
    }

    public function getPaginatedOrdersProperty(): Collection
    {
        $query = Order::query()
            ->when($this->startDate, fn($q) => $q->where('received_date', '>=', $this->startDate))
            ->when($this->endDate, fn($q) => $q->where('received_date', '<=', $this->endDate))
            ->when(!empty($this->selectedChannels), fn($q) => $q->whereIn('channel_name', $this->selectedChannels));

        return $query->orderBy($this->sortBy, $this->sortDirection)
            ->paginate($this->perPage, ['*'], 'page')
            ->getCollection();
    }

    public function getSalesMetricsProperty(): SalesMetrics
    {
        return new SalesMetrics($this->orders);
    }

    public function getAvailableChannelsProperty(): Collection
    {
        return Order::distinct()->pluck('channel_name')->filter()->sort()->values();
    }

    public function getChartDataProperty(): array
    {
        try {
            $period = $this->getChartPeriod();
            
            return match ($this->chartType) {
                'line' => $this->salesMetrics->getLineChartData($period),
                'bar' => $this->salesMetrics->getBarChartData($period),
                'order_count' => $this->salesMetrics->getOrderCountChartData($period),
                'doughnut' => $this->salesMetrics->getDoughnutChartData(),
                default => $this->salesMetrics->getLineChartData($period),
            };
        } catch (\Exception $e) {
            // Return empty chart data structure on error
            return [
                'labels' => [],
                'datasets' => []
            ];
        }
    }

    protected function getChartPeriod(): string
    {
        if (empty($this->startDate) || empty($this->endDate)) {
            return '30'; // Default fallback
        }
        
        try {
            $start = Carbon::parse($this->startDate);
            $end = Carbon::parse($this->endDate);
            $diffInDays = $start->diffInDays($end);

            return (string) max(1, $diffInDays); // Ensure at least 1 day
        } catch (\Exception $e) {
            return '30'; // Default fallback
        }
    }

    public function render()
    {
        return view('livewire.analytics.sales-analytics')->layout('components.layouts.app');
    }
}