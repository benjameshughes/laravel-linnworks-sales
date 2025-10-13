<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class SyncCheckpoint extends Model
{
    protected $fillable = [
        'sync_type',
        'source',
        'last_sync_at',
        'sync_started_at',
        'sync_completed_at',
        'status',
        'records_synced',
        'records_created',
        'records_updated',
        'records_failed',
        'metadata',
        'error_message',
    ];

    protected $casts = [
        'last_sync_at' => 'datetime',
        'sync_started_at' => 'datetime',
        'sync_completed_at' => 'datetime',
        'records_synced' => 'integer',
        'records_created' => 'integer',
        'records_updated' => 'integer',
        'records_failed' => 'integer',
        'metadata' => 'array',
    ];

    /**
     * Get or create checkpoint for sync type
     */
    public static function getOrCreateCheckpoint(string $syncType, string $source = 'linnworks'): self
    {
        return self::firstOrCreate(
            ['sync_type' => $syncType, 'source' => $source],
            [
                'last_sync_at' => now()->subYear(), // Default: 1 year ago for first sync
                'status' => 'pending',
            ]
        );
    }

    /**
     * Start a sync operation
     */
    public function startSync(): void
    {
        $this->update([
            'status' => 'in_progress',
            'sync_started_at' => now(),
            'sync_completed_at' => null,
            'error_message' => null,
        ]);
    }

    /**
     * Complete a sync operation successfully
     */
    public function completeSync(int $synced, int $created, int $updated, int $failed = 0, ?array $metadata = null): void
    {
        $this->update([
            'status' => 'completed',
            'sync_completed_at' => now(),
            'last_sync_at' => now(),
            'records_synced' => $synced,
            'records_created' => $created,
            'records_updated' => $updated,
            'records_failed' => $failed,
            'metadata' => $metadata ?? $this->metadata,
        ]);
    }

    /**
     * Mark sync as failed
     */
    public function failSync(string $errorMessage): void
    {
        $this->update([
            'status' => 'failed',
            'sync_completed_at' => now(),
            'error_message' => $errorMessage,
        ]);
    }

    /**
     * Check if sync should run based on last sync time
     */
    public function shouldSync(int $intervalMinutes = 60): bool
    {
        if ($this->status === 'in_progress') {
            // Check if stuck (started more than 1 hour ago)
            if ($this->sync_started_at && $this->sync_started_at->diffInMinutes(now()) > 60) {
                return true; // Allow retry of stuck sync
            }

            return false;
        }

        return $this->last_sync_at->diffInMinutes(now()) >= $intervalMinutes;
    }

    /**
     * Get incremental sync start date
     */
    public function getIncrementalStartDate(): Carbon
    {
        // Start from last successful sync, or 7 days ago for safety
        return $this->status === 'completed' && $this->last_sync_at
            ? $this->last_sync_at
            : now()->subDays(7);
    }

    /**
     * Scope: By sync type
     */
    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('sync_type', $type);
    }

    /**
     * Scope: Recent syncs
     */
    public function scopeRecent(Builder $query, int $hours = 24): Builder
    {
        return $query->where('last_sync_at', '>=', now()->subHours($hours));
    }

    /**
     * Scope: Failed syncs
     */
    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', 'failed');
    }

    /**
     * Scope: In progress
     */
    public function scopeInProgress(Builder $query): Builder
    {
        return $query->where('status', 'in_progress');
    }

    /**
     * Get sync duration in minutes
     */
    protected function syncDuration(): Attribute
    {
        return Attribute::make(
            get: function () {
                if (! $this->sync_started_at || ! $this->sync_completed_at) {
                    return null;
                }

                return $this->sync_started_at->diffInMinutes($this->sync_completed_at);
            }
        );
    }

    /**
     * Get success rate
     */
    protected function successRate(): Attribute
    {
        return Attribute::make(
            get: function () {
                if ($this->records_synced === 0) {
                    return 0.0;
                }
                $successful = $this->records_created + $this->records_updated;

                return round(($successful / $this->records_synced) * 100, 2);
            }
        );
    }

    /**
     * Check if sync was successful
     */
    protected function isSuccessful(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->status === 'completed' && $this->records_failed === 0
        );
    }
}
