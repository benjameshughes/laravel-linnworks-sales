<?php

declare(strict_types=1);

namespace App\Livewire\Settings;

use App\Jobs\SyncHistoricalOrdersJob;
use App\Models\SyncLog;
use Carbon\Carbon;
use Flux\DateRange;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Import Progress Component - Simplified UI
 *
 * Shows a date picker and import history table.
 * Active imports show real-time progress inline in the table.
 */
class ImportProgress extends Component
{
    public ?DateRange $dateRange = null;

    // Version counter to force re-renders on WebSocket events
    public int $refreshKey = 0;

    public function mount(): void
    {
        $this->dateRange = new DateRange(
            now()->subDays(730),
            now()
        );
    }

    /**
     * Get the currently active sync (if any)
     */
    #[Computed]
    public function activeSync(): ?SyncLog
    {
        return SyncLog::getActiveSync(SyncLog::TYPE_HISTORICAL_ORDERS);
    }

    /**
     * Get import history including active sync
     */
    #[Computed]
    public function imports(): array
    {
        return SyncLog::where('sync_type', SyncLog::TYPE_HISTORICAL_ORDERS)
            ->orderByDesc('started_at')
            ->limit(20)
            ->get()
            ->map(fn (SyncLog $log) => $this->formatImportRow($log))
            ->toArray();
    }

    /**
     * Format a sync log for table display
     */
    protected function formatImportRow(SyncLog $log): array
    {
        $data = $log->progress_data ?? [];
        $isActive = $log->isInProgress();

        return [
            'id' => $log->id,
            'status' => $log->status,
            'status_label' => $log->status_label,
            'status_color' => $log->status_color,
            'is_active' => $isActive,
            'started_at' => $log->started_at->format('M j, Y g:i A'),
            'completed_at' => $log->completed_at?->format('g:i A'),
            'duration' => $log->duration_for_humans,
            'date_range' => isset($log->metadata['date_range'])
                ? $log->metadata['date_range']['from'].' → '.$log->metadata['date_range']['to']
                : null,
            // Progress data for active imports
            'progress' => $isActive ? [
                'percentage' => $log->progress_percentage,
                'message' => $data['message'] ?? 'Processing...',
                'processed' => $data['total_processed'] ?? 0,
                'expected' => $data['total_expected'] ?? 0,
                'speed' => $data['orders_per_second'] ?? 0,
            ] : null,
            // Final stats for completed imports
            'stats' => [
                'processed' => $data['total_processed'] ?? $log->total_fetched ?? 0,
                'created' => $log->total_created ?? 0,
                'updated' => $log->total_updated ?? 0,
                'failed' => $log->total_failed ?? 0,
            ],
            'error_message' => $log->error_message,
        ];
    }

    /**
     * Start the import job
     */
    public function startImport(): void
    {
        if ($this->activeSync) {
            return; // Already running
        }

        SyncHistoricalOrdersJob::dispatch(
            fromDate: Carbon::parse($this->dateRange->start())->startOfDay(),
            toDate: Carbon::parse($this->dateRange->end())->endOfDay(),
            startedBy: auth()->user()?->name ?? 'UI Import',
        );

        // Clear cache to pick up the new sync
        unset($this->activeSync);
        unset($this->imports);
        $this->refreshKey++;
    }

    /**
     * Handle sync progress updates from Reverb
     */
    #[On('echo:sync-progress,SyncProgressUpdated')]
    public function handleSyncProgress(array $data): void
    {
        $stage = $data['stage'] ?? null;

        // Only refresh on meaningful progress updates
        if (in_array($stage, ['historical-import', 'fetching-batch', 'importing-batch'])) {
            return;
        }

        unset($this->activeSync);
        unset($this->imports);
        $this->refreshKey++;
    }

    /**
     * Handle sync completion from Reverb
     */
    #[On('echo:sync-progress,SyncCompleted')]
    public function handleSyncCompleted(array $data): void
    {
        unset($this->activeSync);
        unset($this->imports);
        $this->refreshKey++;
    }

    public function render()
    {
        return view('livewire.settings.import-progress');
    }
}
