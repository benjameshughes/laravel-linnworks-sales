<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderProperty extends Model
{
    protected $fillable = [
        'order_id',
        'property_type',
        'property_name',
        'property_value',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
