<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Carbon\Carbon;

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
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'metadata' => 'array',
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
        if (!$this->completed_at) {
            return null;
        }

        return $this->completed_at->diffInSeconds($this->started_at);
    }

    /**
     * Get human-readable duration
     */
    public function getDurationForHumansAttribute(): ?string
    {
        if (!$this->duration) {
            return null;
        }

        return Carbon::now()->subSeconds($this->duration)->diffForHumans(null, true);
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
}