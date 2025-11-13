<?php

namespace App\Livewire\Reports;

use App\Reports\ReportRegistry;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class ReportsIndex extends Component
{
    public ?string $selectedReportSlug = null;

    public function mount(): void
    {
        $firstReport = ReportRegistry::all()->first();

        if ($firstReport) {
            $this->selectedReportSlug = $firstReport->slug();
        }
    }

    public function selectReport(string $slug): void
    {
        $this->selectedReportSlug = $slug;
    }

    public function render()
    {
        $reportsByCategory = ReportRegistry::byCategory();

        return view('livewire.reports.reports-index', [
            'reportsByCategory' => $reportsByCategory,
        ]);
    }
}
