<?php

namespace App\Livewire\Reports;

use App\Models\ReportExecution;
use App\Reports\AbstractReport;
use App\Reports\Enums\ExportFormat;
use App\Reports\ReportRegistry;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * @property-read \App\Reports\AbstractReport|null $selectedReport
 * @property-read array $reportFilters
 * @property-read \Illuminate\Support\Collection $availableReports
 * @property-read \Illuminate\Support\Collection $recentExecutions
 */
#[Layout('components.layouts.app')]
class ReportsIndex extends Component
{
    public ?string $selectedReportSlug = null;

    public array $filters = [];

    public ?Collection $previewData = null;

    public ?int $totalRows = null;

    public array $availableSkus = [];

    public array $availableSubsources = [];

    public function mount(): void
    {
        $firstReport = ReportRegistry::all()->first();

        if ($firstReport) {
            $this->selectedReportSlug = $firstReport->slug();
            $this->initializeFilters();
        }
    }

    public function updatedSelectedReportSlug(): void
    {
        $this->initializeFilters();
    }

    public function selectReport(string $slug): void
    {
        $this->selectedReportSlug = $slug;
        $this->initializeFilters();
    }

    protected function initializeFilters(): void
    {
        if ($this->selectedReport) {
            $this->filters = $this->selectedReport->getDefaultFilters();
            $this->previewData = null;
            $this->totalRows = null;
            $this->loadFilterOptions();
        }
    }

    #[Computed]
    public function selectedReport(): ?AbstractReport
    {
        if (! $this->selectedReportSlug) {
            return null;
        }

        return ReportRegistry::findBySlug($this->selectedReportSlug);
    }

    #[Computed]
    public function reportFilters(): array
    {
        return $this->selectedReport?->filters() ?? [];
    }

    public function loadFilterOptions(): void
    {
        if (isset($this->filters['date_range'])) {
            $dateStart = Carbon::parse($this->filters['date_range']['start'])->startOfDay();
            $dateEnd = Carbon::parse($this->filters['date_range']['end'])->endOfDay();

            $this->availableSkus = DB::table('order_items as oi')
                ->join('orders as o', 'o.id', '=', 'oi.order_id')
                ->whereNotNull('oi.parent_sku')
                ->whereBetween('o.received_at', [$dateStart, $dateEnd])
                ->distinct()
                ->pluck('oi.parent_sku')
                ->sort()
                ->values()
                ->toArray();

            $this->availableSubsources = DB::table('orders as o')
                ->join('order_items as oi', 'o.id', '=', 'oi.order_id')
                ->whereNotNull('oi.parent_sku')
                ->whereBetween('o.received_at', [$dateStart, $dateEnd])
                ->selectRaw("COALESCE(NULLIF(o.subsource, ''), 'Unknown') as subsource")
                ->distinct()
                ->pluck('subsource')
                ->sort()
                ->values()
                ->toArray();
        }
    }

    public function updatedFilters(): void
    {
        $this->previewData = null;
        $this->totalRows = null;
        $this->loadFilterOptions();
    }

    public function applyFilters(): void
    {
        if ($this->selectedReport) {
            $this->previewData = $this->selectedReport->preview($this->filters, 100);
            $this->totalRows = $this->selectedReport->count($this->filters);
        }
    }

    public function resetFilters(): void
    {
        if ($this->selectedReport) {
            $this->filters = $this->selectedReport->getDefaultFilters();
            $this->previewData = null;
            $this->totalRows = null;
            $this->loadFilterOptions();
        }
    }

    public function toggleSku(string $sku): void
    {
        if (! isset($this->filters['skus'])) {
            $this->filters['skus'] = [];
        }

        if (in_array($sku, $this->filters['skus'])) {
            $this->filters['skus'] = array_values(array_diff($this->filters['skus'], [$sku]));
        } else {
            $this->filters['skus'][] = $sku;
        }

        $this->updatedFilters();
    }

    public function toggleSubsource(string $subsource): void
    {
        if (! isset($this->filters['subsources'])) {
            $this->filters['subsources'] = [];
        }

        if (in_array($subsource, $this->filters['subsources'])) {
            $this->filters['subsources'] = array_values(array_diff($this->filters['subsources'], [$subsource]));
        } else {
            $this->filters['subsources'][] = $subsource;
        }

        $this->updatedFilters();
    }

    public function clearFilters(): void
    {
        if (isset($this->filters['skus'])) {
            $this->filters['skus'] = [];
        }

        if (isset($this->filters['subsources'])) {
            $this->filters['subsources'] = [];
        }

        $this->updatedFilters();
    }

    public function toggleFilterValue(string $filterName, mixed $value): void
    {
        if (! isset($this->filters[$filterName])) {
            $this->filters[$filterName] = [];
        }

        if (! is_array($this->filters[$filterName])) {
            $this->filters[$filterName] = [];
        }

        if (in_array($value, $this->filters[$filterName])) {
            $this->filters[$filterName] = array_values(array_diff($this->filters[$filterName], [$value]));
        } else {
            $this->filters[$filterName][] = $value;
        }

        $this->updatedFilters();
    }

    public function clearFilterValues(string $filterName): void
    {
        if (isset($this->filters[$filterName])) {
            $this->filters[$filterName] = [];
        }

        $this->updatedFilters();
    }

    public function getFilterOptions(string $filterName): array
    {
        return match ($filterName) {
            'skus' => $this->availableSkus,
            'subsources' => $this->availableSubsources,
            'channels' => $this->getAvailableChannels(),
            default => [],
        };
    }

    protected function getAvailableChannels(): array
    {
        if (! isset($this->filters['date_range'])) {
            return [];
        }

        $dateStart = Carbon::parse($this->filters['date_range']['start'])->startOfDay();
        $dateEnd = Carbon::parse($this->filters['date_range']['end'])->endOfDay();

        return DB::table('orders as o')
            ->join('order_items as oi', 'o.id', '=', 'oi.order_id')
            ->whereNotNull('oi.parent_sku')
            ->whereBetween('o.received_at', [$dateStart, $dateEnd])
            ->selectRaw("COALESCE(NULLIF(o.source, ''), 'Unknown') as source")
            ->distinct()
            ->pluck('source')
            ->sort()
            ->values()
            ->toArray();
    }

    public function download(string $format = 'xlsx'): mixed
    {
        if (! $this->selectedReport) {
            return null;
        }

        $exportFormat = ExportFormat::from($format);
        $content = $this->selectedReport->export($this->filters, $exportFormat);

        $filename = $this->generateFilename($exportFormat);

        ReportExecution::create([
            'user_id' => auth()->id(),
            'report_class' => get_class($this->selectedReport),
            'filters' => $this->filters,
            'row_count' => $this->totalRows ?? $this->selectedReport->count($this->filters),
            'status' => 'completed',
            'started_at' => now(),
            'completed_at' => now(),
        ]);

        return response()->streamDownload(
            function () use ($content) {
                echo $content;
            },
            $filename,
            ['Content-Type' => $exportFormat->mimeType()]
        );
    }

    protected function generateFilename(ExportFormat $format): string
    {
        $slug = $this->selectedReport->slug();
        $dateStr = now()->format('Ymd-His');

        return "{$slug}-{$dateStr}.{$format->extension()}";
    }

    public function render()
    {
        $reportsByCategory = ReportRegistry::byCategory();

        return view('livewire.reports.reports-index', [
            'reportsByCategory' => $reportsByCategory,
        ]);
    }
}
