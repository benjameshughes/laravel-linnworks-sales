<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderShipping extends Model
{
    protected $table = 'order_shipping';

    protected $fillable = [
        'order_id',
        'tracking_number',
        'vendor',
        'postal_service_id',
        'postal_service_name',
        'total_weight',
        'item_weight',
        'package_category',
        'package_type',
        'postage_cost',
        'postage_cost_ex_tax',
        'label_printed',
        'label_error',
        'invoice_printed',
        'pick_list_printed',
        'partial_shipped',
        'manual_adjust',
    ];

    protected function casts(): array
    {
        return [
            'total_weight' => 'decimal:3',
            'item_weight' => 'decimal:3',
            'postage_cost' => 'decimal:2',
            'postage_cost_ex_tax' => 'decimal:2',
            'label_printed' => 'boolean',
            'invoice_printed' => 'boolean',
            'pick_list_printed' => 'boolean',
            'partial_shipped' => 'boolean',
            'manual_adjust' => 'boolean',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
