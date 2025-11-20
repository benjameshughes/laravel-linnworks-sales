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
use Livewire\Component;
use Livewire\WithPagination;

class ReportViewer extends Component
{
    use WithPagination;

    public string $reportSlug;

    public array $filters = [];

    public ?Collection $previewData = null;

    public ?int $totalRows = null;

    public array $availableSkus = [];

    public array $availableSubsources = [];

    public function mount(string $reportSlug): void
    {
        $this->reportSlug = $reportSlug;

        $report = $this->report;
        $this->filters = $report->getDefaultFilters();
        $this->loadFilterOptions();
    }

    #[Computed]
    public function report(): AbstractReport
    {
        $report = ReportRegistry::findBySlug($this->reportSlug);

        if (! $report) {
            abort(404, "Report not found: {$this->reportSlug}");
        }

        return $report;
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
        $this->resetPage();
        $this->loadFilterOptions();
    }

    public function applyFilters(): void
    {
        $this->previewData = $this->report->preview($this->filters, 100);
        $this->totalRows = $this->report->count($this->filters);
    }

    public function resetFilters(): void
    {
        $this->filters = $this->report->getDefaultFilters();
        $this->previewData = null;
        $this->totalRows = null;
        $this->resetPage();
        $this->loadFilterOptions();
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

    public function download(string $format = 'xlsx'): mixed
    {
        $exportFormat = ExportFormat::from($format);
        $content = $this->report->export($this->filters, $exportFormat);

        $filename = $this->generateFilename($exportFormat);

        ReportExecution::create([
            'user_id' => auth()->id(),
            'report_class' => get_class($this->report),
            'filters' => $this->filters,
            'row_count' => $this->totalRows ?? $this->report->count($this->filters),
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
        $slug = $this->report->slug();
        $dateStr = now()->format('Ymd-His');

        return "{$slug}-{$dateStr}.{$format->extension()}";
    }

    public function render()
    {
        return view('livewire.reports.report-viewer');
    }
}
