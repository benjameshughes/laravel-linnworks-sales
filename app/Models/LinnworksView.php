<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LinnworksView extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'view_id',
        'name',
        'is_default',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'view_id' => 'integer',
            'is_default' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }
}
