<?php

namespace App\Models;

use App\Models\LinnworksConnection;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Get the user's initials (modern accessor)
     */
    public  function initials(): Attribute
    {
        return Attribute::make(
            get: fn () => Str::of($this->name)
                ->explode(' ')
                ->take(2)
                ->map(fn (string $word) => Str::upper(Str::substr($word, 0, 1)))
                ->implode('')
        );
    }

    /**
     * Get the user's display name (first name only)
     */
    protected function firstName(): Attribute
    {
        return Attribute::make(
            get: fn () => Str::of($this->name)->explode(' ')->first()
        );
    }

    /**
     * Get the user's last name
     */
    protected function lastName(): Attribute
    {
        return Attribute::make(
            get: fn () => Str::of($this->name)
                ->explode(' ')
                ->skip(1)
                ->implode(' ')
        );
    }

    /**
     * Check if user has verified email
     */
    protected function isEmailVerified(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->email_verified_at !== null
        );
    }

    /**
     * Get user's avatar URL (placeholder for future implementation)
     */
    protected function avatarUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => "https://ui-avatars.com/api/?name={$this->initials}&color=7F9CF5&background=EBF4FF"
        );
    }

    /**
     * Get the user's Linnworks connection
     */
    public function linnworksConnection(): HasOne
    {
        return $this->hasOne(LinnworksConnection::class);
    }

    /**
     * Check if user has active Linnworks connection
     */
    protected function hasLinnworksConnection(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->linnworksConnection()->where('is_active', true)->exists()
        );
    }

    /**
     * Get user's timezone (default to UTC, can be extended)
     */
    protected function timezone(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->attributes['timezone'] ?? 'UTC'
        );
    }
}
