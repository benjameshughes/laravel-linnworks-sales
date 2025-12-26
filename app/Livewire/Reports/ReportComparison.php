<?php

namespace App\Livewire\Reports;

use App\Reports\AbstractReport;
use App\Reports\ReportRegistry;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * @property-read AbstractReport|null $reportA
 * @property-read AbstractReport|null $reportB
 */
class ReportComparison extends Component
{
    public ?string $reportClassA = null;

    public ?string $reportClassB = null;

    public array $filtersA = [];

    public array $filtersB = [];

    public ?Collection $previewDataA = null;

    public ?Collection $previewDataB = null;

    public ?int $totalRowsA = null;

    public ?int $totalRowsB = null;

    public function mount(?string $reportClassA = null, ?string $reportClassB = null): void
    {
        $reports = ReportRegistry::all();

        $this->reportClassA = $reportClassA ?? $reports->first()?->let(fn ($r) => get_class($r));
        $this->reportClassB = $reportClassB ?? $reports->skip(1)->first()?->let(fn ($r) => get_class($r));

        if ($this->reportClassA) {
            $this->filtersA = $this->reportA->getDefaultFilters();
        }

        if ($this->reportClassB) {
            $this->filtersB = $this->reportB->getDefaultFilters();
        }
    }

    #[Computed]
    public function reportA(): ?AbstractReport
    {
        return $this->reportClassA ? app($this->reportClassA) : null;
    }

    #[Computed]
    public function reportB(): ?AbstractReport
    {
        return $this->reportClassB ? app($this->reportClassB) : null;
    }

    #[Computed]
    public function availableReports(): Collection
    {
        return ReportRegistry::all();
    }

    public function updatedReportClassA(): void
    {
        $this->filtersA = $this->reportA?->getDefaultFilters() ?? [];
        $this->previewDataA = null;
        $this->totalRowsA = null;
    }

    public function updatedReportClassB(): void
    {
        $this->filtersB = $this->reportB?->getDefaultFilters() ?? [];
        $this->previewDataB = null;
        $this->totalRowsB = null;
    }

    public function applyFiltersA(): void
    {
        if ($this->reportA) {
            $this->previewDataA = $this->reportA->preview($this->filtersA, 50);
            $this->totalRowsA = $this->reportA->count($this->filtersA);
        }
    }

    public function applyFiltersB(): void
    {
        if ($this->reportB) {
            $this->previewDataB = $this->reportB->preview($this->filtersB, 50);
            $this->totalRowsB = $this->reportB->count($this->filtersB);
        }
    }

    public function resetFiltersA(): void
    {
        $this->filtersA = $this->reportA?->getDefaultFilters() ?? [];
        $this->previewDataA = null;
        $this->totalRowsA = null;
    }

    public function resetFiltersB(): void
    {
        $this->filtersB = $this->reportB?->getDefaultFilters() ?? [];
        $this->previewDataB = null;
        $this->totalRowsB = null;
    }

    public function render()
    {
        return view('livewire.reports.report-comparison');
    }
}
