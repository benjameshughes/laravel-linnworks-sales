<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class FailedOrderSync extends Model
{
    protected $fillable = [
        'order_id',
        'order_number',
        'order_type',
        'failure_reason',
        'error_message',
        'order_data',
        'exception_context',
        'attempt_count',
        'last_attempted_at',
        'next_retry_at',
        'is_resolved',
        'resolved_at',
    ];

    protected $casts = [
        'order_data' => 'array',
        'exception_context' => 'array',
        'attempt_count' => 'integer',
        'last_attempted_at' => 'datetime',
        'next_retry_at' => 'datetime',
        'is_resolved' => 'boolean',
        'resolved_at' => 'datetime',
    ];

    /**
     * Record a failed order sync
     */
    public static function recordFailure(
        string $orderType,
        string $failureReason,
        ?string $orderId = null,
        ?string $orderNumber = null,
        ?string $errorMessage = null,
        ?array $orderData = null,
        ?array $exceptionContext = null
    ): self {
        return self::create([
            'order_id' => $orderId,
            'order_number' => $orderNumber,
            'order_type' => $orderType,
            'failure_reason' => $failureReason,
            'error_message' => $errorMessage,
            'order_data' => $orderData,
            'exception_context' => $exceptionContext,
            'attempt_count' => 1,
            'last_attempted_at' => now(),
            'next_retry_at' => now()->addHour(),
        ]);
    }

    /**
     * Mark this sync as resolved
     */
    public function markResolved(): bool
    {
        return $this->update([
            'is_resolved' => true,
            'resolved_at' => now(),
        ]);
    }

    /**
     * Record another failure attempt with exponential backoff
     */
    public function recordRetryFailure(): void
    {
        $backoffHours = match ($this->attempt_count) {
            1 => 1,
            2 => 6,
            default => 24,
        };

        $this->increment('attempt_count');
        $this->update([
            'last_attempted_at' => now(),
            'next_retry_at' => now()->addHours($backoffHours),
        ]);
    }

    /**
     * Scope: Unresolved failures
     */
    public function scopeUnresolved(Builder $query): Builder
    {
        return $query->where('is_resolved', false);
    }

    /**
     * Scope: Ready for retry
     */
    public function scopeReadyForRetry(Builder $query): Builder
    {
        return $this->scopeUnresolved($query)
            ->where('next_retry_at', '<=', now())
            ->orderBy('next_retry_at');
    }

    /**
     * Scope: By order type
     */
    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('order_type', $type);
    }

    /**
     * Scope: Failed multiple times
     */
    public function scopeFailedMultipleTimes(Builder $query, int $minAttempts = 3): Builder
    {
        return $query->where('attempt_count', '>=', $minAttempts);
    }

    /**
     * Check if this has exceeded maximum retry attempts
     */
    protected function hasExceededMaxRetries(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->attempt_count >= 3
        );
    }

    /**
     * Get order identifier (ID or number)
     */
    protected function orderIdentifier(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->order_id ?? $this->order_number ?? 'unknown'
        );
    }
}
