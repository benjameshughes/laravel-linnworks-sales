<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class LinnworksLocation extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'location_id',
        'name',
        'is_default',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }
}
