<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderNote extends Model
{
    protected $fillable = [
        'order_id',
        'linnworks_note_id',
        'note_date',
        'is_internal',
        'note_text',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'note_date' => 'datetime',
            'is_internal' => 'boolean',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
