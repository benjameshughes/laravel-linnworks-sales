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
        'item_id',
        'sku',
        'item_title',
        'quantity',
        'unit_cost',
        'price_per_unit',
        'line_total',
        'discount_amount',
        'tax_amount',
        'category_name',
        'metadata',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_cost' => 'decimal:4',
        'price_per_unit' => 'decimal:4',
        'line_total' => 'decimal:4',
        'discount_amount' => 'decimal:4',
        'tax_amount' => 'decimal:4',
        'metadata' => 'array',
    ];

    /**
     * Get the order that owns this item.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Get the product for this order item.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'sku', 'sku');
    }

    /**
     * Calculate profit for this order item.
     */
    public function getProfit(): float
    {
        return $this->line_total - ($this->unit_cost * $this->quantity);
    }

    /**
     * Calculate profit margin percentage.
     */
    public function getProfitMargin(): float
    {
        if ($this->line_total == 0) {
            return 0;
        }
        
        return ($this->getProfit() / $this->line_total) * 100;
    }

    /**
     * Scope to filter by SKU.
     */
    public function scopeBySku($query, string $sku)
    {
        return $query->where('sku', $sku);
    }

    /**
     * Scope to filter by category.
     */
    public function scopeByCategory($query, string $category)
    {
        return $query->where('category_name', $category);
    }

    /**
     * Scope to filter by date range.
     */
    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereHas('order', function($q) use ($startDate, $endDate) {
            $q->whereBetween('received_date', [$startDate, $endDate]);
        });
    }
}