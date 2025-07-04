<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'linnworks_order_id',
        'order_number',
        'channel_name',
        'channel_reference_number',
        'source',
        'sub_source',
        'external_reference',
        'total_value',
        'total_discount',
        'postage_cost',
        'total_paid',
        'profit_margin',
        'currency',
        'status',
        'addresses',
        'received_date',
        'processed_date',
        'dispatched_date',
        'is_resend',
        'is_exchange',
        'notes',
        'raw_data',
    ];

    protected $casts = [
        'total_value' => 'decimal:2',
        'total_discount' => 'decimal:2',
        'postage_cost' => 'decimal:2',
        'total_paid' => 'decimal:2',
        'profit_margin' => 'decimal:2',
        'addresses' => 'array',
        'received_date' => 'datetime',
        'processed_date' => 'datetime',
        'dispatched_date' => 'datetime',
        'is_resend' => 'boolean',
        'is_exchange' => 'boolean',
        'raw_data' => 'array',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class, 'channel_name', 'name');
    }

    public function getTotalItemsAttribute(): int
    {
        return $this->items->sum('quantity');
    }

    public function getNetProfitAttribute(): float
    {
        return $this->total_paid - $this->items->sum(function ($item) {
            return $item->cost_price * $item->quantity;
        });
    }

    public function getProfitMarginPercentageAttribute(): float
    {
        if ($this->total_paid == 0) {
            return 0;
        }
        
        return ($this->net_profit / $this->total_paid) * 100;
    }

    public function scopeByChannel($query, string $channelName)
    {
        return $query->where('channel_name', $channelName);
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('received_date', [$startDate, $endDate]);
    }

    public function scopeProcessed($query)
    {
        return $query->where('status', 'processed');
    }
}
