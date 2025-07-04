<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'linnworks_item_id',
        'sku',
        'title',
        'description',
        'category',
        'quantity',
        'unit_price',
        'total_price',
        'cost_price',
        'profit_margin',
        'tax_rate',
        'discount_amount',
        'bin_rack',
        'is_service',
        'item_attributes',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'total_price' => 'decimal:2',
        'cost_price' => 'decimal:2',
        'profit_margin' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'is_service' => 'boolean',
        'item_attributes' => 'array',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function getTotalCostAttribute(): float
    {
        return $this->cost_price * $this->quantity;
    }

    public function getNetProfitAttribute(): float
    {
        return $this->total_price - $this->total_cost;
    }

    public function getProfitMarginPercentageAttribute(): float
    {
        if ($this->total_price == 0) {
            return 0;
        }
        
        return ($this->net_profit / $this->total_price) * 100;
    }

    public function scopeBySku($query, string $sku)
    {
        return $query->where('sku', $sku);
    }

    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    public function scopeServices($query)
    {
        return $query->where('is_service', true);
    }

    public function scopeProducts($query)
    {
        return $query->where('is_service', false);
    }
}
