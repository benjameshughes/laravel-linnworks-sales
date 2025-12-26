<?php

namespace App\Models;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SyncLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'sync_type',
        'status',
        'started_at',
        'completed_at',
        'total_fetched',
        'total_created',
        'total_updated',
        'total_skipped',
        'total_failed',
        'metadata',
        'error_message',
        'current_phase',
        'current_step',
        'total_steps',
        'progress_data',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'metadata' => 'array',
        'progress_data' => 'array',
    ];

    // Sync types
    const TYPE_OPEN_ORDERS = 'open_orders';

    const TYPE_HISTORICAL_ORDERS = 'historical_orders';

    const TYPE_ORDER_UPDATES = 'order_updates';

    const TYPE_PRODUCTS = 'products';

    // Statuses
    const STATUS_STARTED = 'started';

    const STATUS_COMPLETED = 'completed';

    const STATUS_FAILED = 'failed';

    /**
     * Start a new sync log
     */
    public static function startSync(string $type, array $metadata = []): self
    {
        return self::create([
            'sync_type' => $type,
            'status' => self::STATUS_STARTED,
            'started_at' => now(),
            'metadata' => $metadata,
        ]);
    }

    /**
     * Complete the sync log
     */
    public function complete(int $fetched = 0, int $created = 0, int $updated = 0, int $skipped = 0, int $failed = 0): void
    {
        $this->update([
            'status' => self::STATUS_COMPLETED,
            'completed_at' => now(),
            'total_fetched' => $fetched,
            'total_created' => $created,
            'total_updated' => $updated,
            'total_skipped' => $skipped,
            'total_failed' => $failed,
        ]);
    }

    /**
     * Fail the sync log
     */
    public function fail(string $errorMessage): void
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'completed_at' => now(),
            'error_message' => $errorMessage,
        ]);
    }

    /**
     * Get the last successful sync for a given type
     */
    public static function lastSuccessfulSync(string $type): ?self
    {
        return self::where('sync_type', $type)
            ->where('status', self::STATUS_COMPLETED)
            ->orderByDesc('completed_at')
            ->first();
    }

    /**
     * Get sync duration in seconds
     */
    public function getDurationAttribute(): ?int
    {
        if (! $this->completed_at) {
            return null;
        }

        return (int) $this->completed_at->diffInSeconds($this->started_at);
    }

    /**
     * Get human-readable duration
     */
    public function getDurationForHumansAttribute(): ?string
    {
        if (! $this->duration) {
            return null;
        }

        return Carbon::now()->subSeconds($this->duration)->diffForHumans(syntax: CarbonInterface::DIFF_ABSOLUTE);
    }

    /**
     * Get success rate percentage
     */
    public function getSuccessRateAttribute(): float
    {
        $total = $this->total_fetched;
        if ($total === 0) {
            return 0;
        }

        $successful = $this->total_created + $this->total_updated;

        return round(($successful / $total) * 100, 2);
    }

    /**
     * Update progress tracking
     */
    public function updateProgress(string $phase, int $currentStep, int $totalSteps, array $data = []): void
    {
        $this->update([
            'current_phase' => $phase,
            'current_step' => $currentStep,
            'total_steps' => $totalSteps,
            'progress_data' => $data,
        ]);
    }

    /**
     * Get current progress percentage
     */
    public function getProgressPercentageAttribute(): float
    {
        if ($this->total_steps === 0) {
            return 0;
        }

        return round(($this->current_step / $this->total_steps) * 100, 2);
    }

    /**
     * Get the latest active sync log (started but not completed)
     */
    public static function getActiveSync(string $type): ?self
    {
        return self::where('sync_type', $type)
            ->where('status', self::STATUS_STARTED)
            ->orderByDesc('started_at')
            ->first();
    }

    /**
     * Get the most recent sync log regardless of status
     * Useful for showing completed/failed syncs on page load
     */
    public static function getRecentSync(string $type, int $withinMinutes = 60): ?self
    {
        return self::where('sync_type', $type)
            ->where('started_at', '>=', now()->subMinutes($withinMinutes))
            ->orderByDesc('started_at')
            ->first();
    }

    /**
     * Get sync history for a given type
     */
    public static function getSyncHistory(string $type, int $limit = 10): \Illuminate\Database\Eloquent\Collection
    {
        return self::where('sync_type', $type)
            ->orderByDesc('started_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Check if sync is in progress
     */
    public function isInProgress(): bool
    {
        return $this->status === self::STATUS_STARTED;
    }

    /**
     * Check if sync completed successfully
     */
    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /**
     * Check if sync failed
     */
    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    /**
     * Get formatted status for display
     */
    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_STARTED => 'In Progress',
            self::STATUS_COMPLETED => 'Completed',
            self::STATUS_FAILED => 'Failed',
            default => 'Unknown',
        };
    }

    /**
     * Get status color for UI
     */
    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_STARTED => 'amber',
            self::STATUS_COMPLETED => 'green',
            self::STATUS_FAILED => 'red',
            default => 'zinc',
        };
    }
}
