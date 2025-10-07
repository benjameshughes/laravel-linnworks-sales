<?php

namespace App\Livewire\Settings;

use App\Events\ImportCompleted;
use App\Events\ImportProgressUpdated;
use App\Events\ImportStarted;
use App\Jobs\RunHistoricalImportJob;
use Livewire\Attributes\On;
use Livewire\Component;

class ImportProgress extends Component
{
    public bool $isImporting = false;
    public bool $isCompleted = false;
    public bool $success = false;

    public int $totalProcessed = 0;
    public int $totalImported = 0;
    public int $totalSkipped = 0;
    public int $totalErrors = 0;
    public int $currentPage = 0;
    public int $totalOrders = 0;
    public float $percentage = 0;
    public string $status = 'idle';
    public ?string $message = null;

    public string $fromDate = '';
    public string $toDate = '';
    public int $batchSize = 200;
    public ?string $startedAt = null;
    public function mount(): void
    {
        // Set default date range (maximum 730 days)
        $this->toDate = now()->format('Y-m-d');
        $this->fromDate = now()->subDays(730)->format('Y-m-d');
    }

    public function startImport(): void
    {
        $this->validate([
            'fromDate' => 'required|date',
            'toDate' => 'required|date|after_or_equal:fromDate',
            'batchSize' => 'required|integer|min:50|max:200',
        ]);

        $this->reset([
            'isCompleted',
            'success',
            'totalProcessed',
            'totalImported',
            'totalSkipped',
            'totalErrors',
            'currentPage',
            'totalOrders',
            'percentage',
            'message',
        ]);

        $this->isImporting = true;
        $this->status = 'queued';
        $this->startedAt = now()->toISOString();

        RunHistoricalImportJob::dispatch(
            fromDate: $this->fromDate,
            toDate: $this->toDate,
            batchSize: $this->batchSize,
        );

        $this->message = 'Import queued. Waiting for background workers...';
    }

    #[On('echo:import-progress,ImportStarted')]
    public function handleImportStarted(array $data): void
    {
        $this->isImporting = true;
        $this->isCompleted = false;
        $this->status = 'processing';
        $this->totalOrders = $data['total_orders'];
        $this->startedAt = $data['started_at'];
        $this->message = "Import started: {$data['total_orders']} orders found";
    }

    #[On('echo:import-progress,ImportProgressUpdated')]
    public function handleProgressUpdate(array $data): void
    {
        $this->totalProcessed = $data['total_processed'];
        $this->totalImported = $data['total_imported'];
        $this->totalSkipped = $data['total_skipped'];
        $this->totalErrors = $data['total_errors'];
        $this->currentPage = $data['current_page'];
        $this->totalOrders = $data['total_orders'];
        $this->percentage = $data['percentage'];
        $this->status = $data['status'];
        $this->message = $data['message'];
    }

    #[On('echo:import-progress,ImportCompleted')]
    public function handleImportCompleted(array $data): void
    {
        $this->isImporting = false;
        $this->isCompleted = true;
        $this->success = $data['success'];
        $this->totalProcessed = $data['total_processed'];
        $this->totalImported = $data['total_imported'];
        $this->totalSkipped = $data['total_skipped'];
        $this->totalErrors = $data['total_errors'];
        $this->percentage = 100;
        $this->status = $data['success'] ? 'completed' : 'failed';
        $this->message = $data['success']
            ? "Import completed successfully!"
            : "Import failed. Check logs for details.";
    }

    public function resetImport(): void
    {
        $this->reset([
            'isImporting',
            'isCompleted',
            'success',
            'totalProcessed',
            'totalImported',
            'totalSkipped',
            'totalErrors',
            'currentPage',
            'totalOrders',
            'percentage',
            'status',
            'message',
            'startedAt',
        ]);
    }

    public function render()
    {
        return view('livewire.settings.import-progress');
    }
}
