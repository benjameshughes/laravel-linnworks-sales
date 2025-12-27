<?php

declare(strict_types=1);

namespace App\Livewire\Settings;

use App\Jobs\SyncHistoricalOrdersJob;
use App\Models\SyncLog;
use Carbon\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Import Progress Component
 *
 * Simple flow:
 * 1. User configures import -> dispatches job
 * 2. Job updates SyncLog in database
 * 3. Job broadcasts SyncProgressUpdated/SyncCompleted via Reverb
 * 4. This component listens and refreshes from database
 *
 * Single source of truth: SyncLog model
 *
 * @property-read \App\Models\SyncLog|null $syncLog
 * @property-read array $recentSyncs
 */
class ImportProgress extends Component
{
    // Form inputs
    public string $fromDate = '';

    public string $toDate = '';

    public int $batchSize = 200;

    // Active sync tracking
    public ?int $activeSyncId = null;

    // Immediate feedback when starting import
    public bool $isStarting = false;

    // Sync history for display
    public array $syncHistory = [];

    // Version counter to force re-renders on WebSocket events
    public int $refreshKey = 0;

    public function mount(): void
    {
        $this->toDate = now()->format('Y-m-d');
        $this->fromDate = now()->subDays(730)->format('Y-m-d');

        $this->loadActiveSyncId();
        $this->loadSyncHistory();
    }

    /**
     * The current sync log - single source of truth
     */
    #[Computed]
    public function syncLog(): ?SyncLog
    {
        if ($this->activeSyncId) {
            return SyncLog::find($this->activeSyncId);
        }

        // Check for active or recent sync
        $sync = SyncLog::getActiveSync(SyncLog::TYPE_HISTORICAL_ORDERS)
            ?? SyncLog::getRecentSync(SyncLog::TYPE_HISTORICAL_ORDERS, 60);

        if ($sync) {
            $this->activeSyncId = $sync->id;
        }

        return $sync;
    }

    /**
     * Whether to show the import form or progress display
     */
    #[Computed]
    public function showProgress(): bool
    {
        // Show progress immediately when user clicks Start Import
        if ($this->isStarting) {
            return true;
        }

        $sync = $this->syncLog;

        if (! $sync) {
            return false;
        }

        // Show progress for all active syncs (including Stage 1)
        if ($sync->isInProgress()) {
            return true;
        }

        // Show completed/failed syncs from the last hour
        return $sync->started_at->isAfter(now()->subHour());
    }

    /**
     * Load the active sync ID if one exists
     */
    protected function loadActiveSyncId(): void
    {
        $sync = SyncLog::getActiveSync(SyncLog::TYPE_HISTORICAL_ORDERS)
            ?? SyncLog::getRecentSync(SyncLog::TYPE_HISTORICAL_ORDERS, 60);

        $this->activeSyncId = $sync?->id;
    }

    /**
     * Load sync history for display
     */
    public function loadSyncHistory(): void
    {
        $history = SyncLog::getSyncHistory(SyncLog::TYPE_HISTORICAL_ORDERS, 10);

        $this->syncHistory = $history->map(fn (SyncLog $log) => [
            'id' => $log->id,
            'status' => $log->status,
            'status_label' => $log->status_label,
            'status_color' => $log->status_color,
            'started_at' => $log->started_at->format('M j, Y g:i A'),
            'completed_at' => $log->completed_at?->format('M j, Y g:i A'),
            'duration' => $log->duration_for_humans,
            'total_processed' => $log->progress_data['total_processed'] ?? $log->total_fetched ?? 0,
            'created' => $log->total_created ?? 0,
            'updated' => $log->total_updated ?? 0,
            'failed' => $log->total_failed ?? 0,
            'error_message' => $log->error_message,
            'date_range' => isset($log->metadata['date_range'])
                ? $log->metadata['date_range']['from'].' to '.$log->metadata['date_range']['to']
                : null,
        ])->toArray();
    }

    /**
     * Start the import job
     */
    public function startImport(): void
    {
        // Provide immediate feedback before validation
        $this->isStarting = true;

        $this->validate([
            'fromDate' => 'required|date',
            'toDate' => 'required|date|after_or_equal:fromDate',
            'batchSize' => 'required|integer|min:50|max:200',
        ]);

        SyncHistoricalOrdersJob::dispatch(
            fromDate: Carbon::parse($this->fromDate)->startOfDay(),
            toDate: Carbon::parse($this->toDate)->endOfDay(),
            startedBy: auth()->user()?->name ?? 'UI Import',
        );

        // Clear cached computed property to pick up the new sync
        unset($this->syncLog);
        $this->activeSyncId = null;
    }

    /**
     * Handle sync progress updates from Reverb
     * Clear computed cache and re-render with fresh data from database
     */
    #[On('echo:sync-progress,SyncProgressUpdated')]
    public function handleSyncProgress(array $data): void
    {
        $stage = $data['stage'] ?? null;

        // Real progress is now available, clear the starting state
        $this->isStarting = false;

        // Ignore Stage 1 and intermediate events
        if (in_array($stage, ['historical-import', 'fetching-batch', 'importing-batch'])) {
            return;
        }

        // Clear computed cache - next render will fetch fresh data
        unset($this->syncLog);
        unset($this->showProgress);

        // Increment to trigger Livewire re-render
        $this->refreshKey++;
    }

    /**
     * Handle sync completion from Reverb
     */
    #[On('echo:sync-progress,SyncCompleted')]
    public function handleSyncCompleted(array $data): void
    {
        // Real progress is now available, clear the starting state
        $this->isStarting = false;

        unset($this->syncLog);
        unset($this->showProgress);
        $this->loadSyncHistory();

        // Increment to trigger Livewire re-render
        $this->refreshKey++;
    }

    /**
     * Reset to show the import form again
     */
    public function resetImport(): void
    {
        $this->activeSyncId = null;
        $this->isStarting = false;
        unset($this->syncLog);
    }

    public function render()
    {
        return view('livewire.settings.import-progress');
    }
}
