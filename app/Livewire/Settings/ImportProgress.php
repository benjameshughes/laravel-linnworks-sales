<?php

namespace App\Livewire\Settings;

use App\Jobs\SyncHistoricalOrdersJob;
use App\Models\SyncLog;
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

    // Performance metrics
    public int $batchNumber = 0;

    public int $totalBatches = 0;

    public int $ordersInBatch = 0;

    public int $created = 0;

    public int $updated = 0;

    public float $ordersPerSecond = 0;

    public float $memoryMb = 0;

    public float $timeElapsed = 0;

    public ?float $estimatedRemaining = null;

    public float $avgSpeed = 0;

    public float $peakMemory = 0;

    public string $fromDate = '';

    public string $toDate = '';

    public int $batchSize = 200;

    public ?string $startedAt = null;

    public int $currentStage = 1;

    public function mount(): void
    {
        // Set default date range (maximum 730 days)
        $this->toDate = now()->format('Y-m-d');
        $this->fromDate = now()->subDays(730)->format('Y-m-d');

        // Load persisted state if there's an active sync
        $this->loadPersistedState();
    }

    /**
     * Load persisted sync state from database
     */
    public function loadPersistedState(): void
    {
        $activeSync = SyncLog::getActiveSync(SyncLog::TYPE_HISTORICAL_ORDERS);

        if (! $activeSync) {
            return;
        }

        $this->isImporting = true;
        $this->isCompleted = false;
        $this->startedAt = $activeSync->started_at->toISOString();
        $this->percentage = $activeSync->progress_percentage;

        // Load progress data if available
        if ($activeSync->progress_data) {
            $data = $activeSync->progress_data;

            // Determine current stage (cast to int for strict comparison)
            $this->currentStage = (int) ($data['stage'] ?? 1);

            // Only show UI when Stage 2 starts (importing)
            // Stage 1 (streaming IDs) is internal detail - don't show to user
            if ($this->currentStage === 1) {
                // Stage 1: Just show "Preparing import..."
                $this->message = 'Preparing import...';
                $this->totalOrders = $data['total_results'] ?? 0;
            } else {
                // Stage 2: Show actual import progress
                $this->message = $data['message'] ?? 'Importing orders...';
                $this->totalProcessed = $data['total_processed'] ?? 0;
                $this->created = $data['created'] ?? 0;
                $this->updated = $data['updated'] ?? 0;
                $this->totalErrors = $data['failed'] ?? 0;
                $this->batchNumber = $data['current_batch'] ?? 0;
                $this->totalOrders = $data['total_expected'] ?? 0;
                $this->totalBatches = $data['total_batches'] ?? 0;
                $this->ordersPerSecond = $data['orders_per_second'] ?? 0;
                $this->memoryMb = $data['memory_mb'] ?? 0;
                $this->timeElapsed = $data['time_elapsed'] ?? 0;
                $this->estimatedRemaining = $data['estimated_remaining'] ?? null;
            }
        }
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

        // Dispatch historical import job
        SyncHistoricalOrdersJob::dispatch(
            fromDate: \Carbon\Carbon::parse($this->fromDate)->startOfDay(),
            toDate: \Carbon\Carbon::parse($this->toDate)->endOfDay(),
            startedBy: auth()->user()?->name ?? 'UI Import',
        );

        $this->message = 'Historical import queued. Waiting for background workers...';
    }

    // Note: SyncProgressUpdated events are ignored - we only use polling
    // Stage 1 (streaming IDs) is an internal detail users don't need to see
    // Stage 2 (importing) updates come from database polling every 3s

    #[On('echo:sync-progress,SyncCompleted')]
    public function handleSyncCompleted(array $data): void
    {
        $this->isImporting = false;
        $this->isCompleted = true;
        $this->success = $data['success'];
        $this->totalProcessed = $data['processed'];
        $this->created = $data['created'];
        $this->updated = $data['updated'];
        $this->totalErrors = $data['failed'];
        $this->percentage = $data['success'] ? 100 : $this->percentage;
        $this->status = $data['success'] ? 'completed' : 'failed';
        $this->message = $data['success']
            ? "Import completed! Processed {$data['processed']} orders."
            : "Import failed after processing {$data['processed']} orders. Check logs for details.";
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

    #[On('echo:orders,import.batch.processed')]
    public function handleBatchProcessed(array $data): void
    {
        $this->batchNumber = $data['batch_number'];
        $this->totalBatches = $data['total_batches'];
        $this->ordersInBatch = $data['orders_in_batch'];
        $this->totalProcessed = $data['total_processed'];
        $this->created = $data['created'];
        $this->updated = $data['updated'];
        $this->ordersPerSecond = $data['orders_per_second'];
        $this->memoryMb = $data['memory_mb'];
        $this->timeElapsed = $data['time_elapsed'];
        $this->estimatedRemaining = $data['estimated_remaining'];
        $this->percentage = $data['percentage'];
        $this->message = "Processing batch {$this->batchNumber}/{$this->totalBatches}...";
    }

    #[On('echo:orders,import.performance.update')]
    public function handlePerformanceUpdate(array $data): void
    {
        $this->totalProcessed = $data['total_processed'];
        $this->created = $data['created'];
        $this->updated = $data['updated'];
        $this->totalErrors = $data['failed'];
        $this->avgSpeed = $data['avg_speed'];
        $this->peakMemory = $data['peak_memory'];
        $this->timeElapsed = $data['duration'];
        $this->message = $data['current_operation'];
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
            ? 'Import completed successfully!'
            : 'Import failed. Check logs for details.';
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
            'batchNumber',
            'totalBatches',
            'ordersInBatch',
            'created',
            'updated',
            'ordersPerSecond',
            'memoryMb',
            'timeElapsed',
            'estimatedRemaining',
            'avgSpeed',
            'peakMemory',
        ]);
    }

    public function render()
    {
        return view('livewire.settings.import-progress');
    }
}
