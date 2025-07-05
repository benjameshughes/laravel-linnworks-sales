<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Carbon\Carbon;

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

    protected $casts = [
        'session_expires_at' => 'datetime',
        'is_active' => 'boolean',
        'application_data' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isSessionValid(): bool
    {
        return $this->session_token &&
               $this->session_expires_at &&
               $this->session_expires_at->isFuture();
    }

    public function needsNewSession(): bool
    {
        return !$this->session_token ||
               !$this->session_expires_at ||
               $this->session_expires_at->isPast();
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }
}
