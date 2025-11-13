<?php

namespace App\Livewire\Reports;

use App\Reports\ReportRegistry;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class ReportsIndex extends Component
{
    public ?string $selectedReportClass = null;

    public function mount(): void
    {
        $firstReport = ReportRegistry::all()->first();

        if ($firstReport) {
            $this->selectedReportClass = get_class($firstReport);
        }
    }

    public function selectReport(string $reportClass): void
    {
        $this->selectedReportClass = $reportClass;
    }

    public function render()
    {
        $reportsByCategory = ReportRegistry::byCategory();

        return view('livewire.reports.reports-index', [
            'reportsByCategory' => $reportsByCategory,
        ]);
    }
}
