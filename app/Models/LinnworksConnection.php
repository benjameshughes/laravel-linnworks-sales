<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LinnworksConnection extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'application_id',
        'application_secret',
        'access_token',
        'session_token',
        'server_location',
        'session_expires_at',
        'is_active',
        'status',
        'application_data',
    ];

    protected function casts(): array
    {
        return [
            'application_id' => 'encrypted',
            'application_secret' => 'encrypted',
            'access_token' => 'encrypted',
            'session_token' => 'encrypted',
            'session_expires_at' => 'datetime',
            'is_active' => 'boolean',
            'application_data' => 'array',
        ];
    }

    protected function preferredOpenOrdersViewId(): Attribute
    {
        return Attribute::make(
            get: fn () => data_get($this->application_data, 'open_orders.view_id')
        );
    }

    protected function preferredOpenOrdersLocationId(): Attribute
    {
        return Attribute::make(
            get: fn () => data_get($this->application_data, 'open_orders.location_id')
        );
    }

    public function updateOpenOrdersPreferences(?int $viewId, ?string $locationId): void
    {
        $data = $this->application_data ?? [];
        data_set($data, 'open_orders.view_id', $viewId);
        data_set($data, 'open_orders.location_id', $locationId);

        $this->application_data = $data;
        $this->save();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if session is valid (modern accessor)
     */
    protected function isSessionValid(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->session_token &&
                         $this->session_expires_at &&
                         $this->session_expires_at->isFuture()
        );
    }

    /**
     * Check if needs new session (modern accessor)
     */
    protected function needsNewSession(): Attribute
    {
        return Attribute::make(
            get: fn () => !$this->session_token ||
                         !$this->session_expires_at ||
                         $this->session_expires_at->isPast()
        );
    }

    /**
     * Get time until session expires (modern accessor)
     */
    protected function sessionExpiresIn(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->session_expires_at ?
                         $this->session_expires_at->diffForHumans() :
                         'No session'
        );
    }

    /**
     * Get connection status (modern accessor)
     */
    protected function connectionStatus(): Attribute
    {
        return Attribute::make(
            get: fn () => match (true) {
                !$this->is_active => 'inactive',
                $this->needs_new_session => 'expired',
                $this->is_session_valid => 'active',
                default => 'unknown'
            }
        );
    }

    /**
     * Get status color for UI (modern accessor)
     */
    protected function statusColor(): Attribute
    {
        return Attribute::make(
            get: fn () => match ($this->connection_status) {
                'active' => 'green',
                'expired' => 'yellow',
                'inactive' => 'red',
                default => 'gray'
            }
        );
    }

    /**
     * Modern query scopes
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function scopeWithValidSession(Builder $query): Builder
    {
        return $query->where('is_active', true)
                    ->whereNotNull('session_token')
                    ->where('session_expires_at', '>', now());
    }

    public function scopeExpired(Builder $query): Builder
    {
        return $query->where('session_expires_at', '<=', now())
                    ->orWhereNull('session_expires_at');
    }
}
