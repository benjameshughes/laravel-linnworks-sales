<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Channel extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'display_name',
        'type',
        'currency',
        'is_active',
        'settings',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'settings' => 'array',
    ];

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'channel_name', 'name');
    }

    public function getTotalOrdersAttribute(): int
    {
        return $this->orders()->count();
    }

    public function getTotalRevenueAttribute(): float
    {
        return $this->orders()->sum('total_paid');
    }

    public function getAverageOrderValueAttribute(): float
    {
        $totalOrders = $this->total_orders;

        if ($totalOrders === 0) {
            return 0;
        }

        return $this->total_revenue / $totalOrders;
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }
}
