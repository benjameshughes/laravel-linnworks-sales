<?php

declare(strict_types=1);

namespace App\Livewire\Analytics;

use App\Factories\Metrics\Sales\SalesFactory;
use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
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

    public string $sortBy = 'received_at';

    public string $sortDirection = 'desc';

    public int $perPage = 50;

    // Chart settings
    public string $chartType = 'line';

    public string $chartMetric = 'revenue';

    public string $chartGrouping = 'daily';

    // View settings
    public string $activeTab = 'overview';

    protected ?string $chartSelectionAnchor = null;

    protected $queryString = [
        'startDate' => ['except' => ''],
        'endDate' => ['except' => ''],
        'datePreset' => ['except' => 'last_30_days'],
        'selectedChannels' => ['except' => []],
        'sortBy' => ['except' => 'received_at'],
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
        $end = $now->format('Y-m-d');

        $this->chartSelectionAnchor = null;

        match ($this->datePreset) {
            'last_7_days' => [
                $this->startDate = $now->copy()->subDays(7)->format('Y-m-d'),
                $this->endDate = $end,
            ],
            'last_30_days' => [
                $this->startDate = $now->copy()->subDays(30)->format('Y-m-d'),
                $this->endDate = $end,
            ],
            'last_90_days' => [
                $this->startDate = $now->copy()->subDays(90)->format('Y-m-d'),
                $this->endDate = $end,
            ],
            'last_6_months' => [
                $this->startDate = $now->copy()->subMonths(6)->format('Y-m-d'),
                $this->endDate = $end,
            ],
            'last_year' => [
                $this->startDate = $now->copy()->subYear()->format('Y-m-d'),
                $this->endDate = $end,
            ],
            'year_to_date' => [
                $this->startDate = $now->copy()->startOfYear()->format('Y-m-d'),
                $this->endDate = $end,
            ],
            'custom' => null,
            default => [
                $this->startDate = $now->copy()->subDays(30)->format('Y-m-d'),
                $this->endDate = $end,
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
        $this->chartSelectionAnchor = null;
    }

    public function toggleChannel(string $channel): void
    {
        if (in_array($channel, $this->selectedChannels)) {
            $this->selectedChannels = array_values(array_diff($this->selectedChannels, [$channel]));
        } else {
            $this->selectedChannels[] = $channel;
        }
    }

    public function clearFilters(): void
    {
        $this->selectedChannels = [];
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

    #[Computed]
    public function orders(): Collection
    {
        $start = $this->startDate ? Carbon::parse($this->startDate)->startOfDay() : null;
        $end = $this->endDate ? Carbon::parse($this->endDate)->endOfDay() : null;

        $query = Order::query()
            ->when($start, fn ($q) => $q->where('received_at', '>=', $start))
            ->when($end, fn ($q) => $q->where('received_at', '<=', $end))
            ->when(! empty($this->selectedChannels), fn ($q) => $q->whereIn('source', $this->selectedChannels));

        return $query->orderBy($this->sortBy, $this->sortDirection)->get();
    }

    public function getPaginatedOrdersProperty(): Collection
    {
        $start = $this->startDate ? Carbon::parse($this->startDate)->startOfDay() : null;
        $end = $this->endDate ? Carbon::parse($this->endDate)->endOfDay() : null;

        $query = Order::query()
            ->when($start, fn ($q) => $q->where('received_at', '>=', $start))
            ->when($end, fn ($q) => $q->where('received_at', '<=', $end))
            ->when(! empty($this->selectedChannels), fn ($q) => $q->whereIn('source', $this->selectedChannels));

        return $query->orderBy($this->sortBy, $this->sortDirection)
            ->paginate($this->perPage, ['*'], 'page')
            ->getCollection();
    }

    #[Computed]
    public function salesMetrics(): SalesFactory
    {
        return new SalesFactory($this->orders);
    }

    #[Computed]
    public function availableChannels(): Collection
    {
        return Order::distinct()->pluck('source')->filter()->sort()->values();
    }

    #[Computed]
    public function chartData(): array
    {
        try {
            $period = $this->getChartPeriod();
            [$start, $end] = $this->getActiveDateRange();

            $data = match ($this->chartType) {
                'line' => $this->salesMetrics->getLineChartData($period, $start, $end),
                'bar' => $this->salesMetrics->getBarChartData($period, $start, $end),
                'order_count' => $this->salesMetrics->getOrderCountChartData($period, $start, $end),
                'doughnut' => $this->salesMetrics->getDoughnutChartData(),
                default => $this->salesMetrics->getLineChartData($period, $start, $end),
            };

            return $data;
        } catch (\Exception $e) {
            // Return empty chart data structure on error
            return [
                'labels' => [],
                'datasets' => [],
            ];
        }
    }

    #[Computed]
    public function chartInteractionOptions(): array
    {
        $isoDates = data_get($this->chartData, 'meta.iso_dates', []);

        if (empty($isoDates)) {
            return [];
        }

        return [
            'onClick' => <<<'JS'
function (event, elements, chart) {
    if (!elements.length) {
        return;
    }

    const element = elements[0];
    const isoDates = chart.data?.meta?.iso_dates ?? [];
    const isoDate = isoDates[element.index] ?? chart.data.labels[element.index] ?? null;

    if (!isoDate) {
        return;
    }

    const mode = event?.native?.shiftKey || event?.native?.metaKey || event?.native?.ctrlKey ? 'range' : 'single';

    Livewire.dispatch('analytics:chart-date-selected', [isoDate, mode]);
}
JS,
        ];
    }

    #[On('analytics:chart-date-selected')]
    public function updateDateRangeFromChart(string $date, string $mode = 'single'): void
    {
        try {
            $selectedDate = Carbon::parse($date);
        } catch (\Throwable $exception) {
            return;
        }

        $formattedDate = $selectedDate->format('Y-m-d');

        if ($mode === 'range' && $this->chartSelectionAnchor) {
            $anchor = Carbon::parse($this->chartSelectionAnchor);

            if ($anchor->greaterThan($selectedDate)) {
                [$anchor, $selectedDate] = [$selectedDate, $anchor];
            }

            $this->startDate = $anchor->format('Y-m-d');
            $this->endDate = $selectedDate->format('Y-m-d');
            $this->chartSelectionAnchor = null;
        } else {
            // Show just the clicked date (single day)
            $this->startDate = $formattedDate;
            $this->endDate = $formattedDate;
            $this->chartSelectionAnchor = $formattedDate;
        }

        $this->datePreset = 'custom';

        // Clear computed property caches to force recalculation
        unset($this->orders, $this->salesMetrics, $this->chartData, $this->chartInteractionOptions);
    }

    protected function getChartPeriod(): string
    {
        [$start, $end] = $this->getActiveDateRange();

        if (! $start || ! $end) {
            return '30'; // Default fallback
        }

        try {
            $startDate = Carbon::parse($start);
            $endDate = Carbon::parse($end);
            $diffInDays = $startDate->diffInDays($endDate);

            return (string) max(1, $diffInDays); // Ensure at least 1 day
        } catch (\Exception $e) {
            return '30'; // Default fallback
        }
    }

    /**
     * Normalise and return the active date range for metric queries.
     */
    protected function getActiveDateRange(): array
    {
        if (empty($this->startDate) || empty($this->endDate)) {
            return [null, null];
        }

        try {
            $start = Carbon::parse($this->startDate);
            $end = Carbon::parse($this->endDate);

            if ($start->greaterThan($end)) {
                [$start, $end] = [$end, $start];
            }

            return [$start->format('Y-m-d'), $end->format('Y-m-d')];
        } catch (\Throwable $exception) {
            return [null, null];
        }
    }

    public function render()
    {
        return view('livewire.analytics.sales-analytics')->layout('components.layouts.app');
    }
}
