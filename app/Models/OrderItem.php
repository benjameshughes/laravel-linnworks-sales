<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'item_id',
        'sku',
        'quantity',
        'unit_cost',
        'price_per_unit',
        'line_total',
        'discount_amount',
        'tax_amount',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_cost' => 'decimal:4',
            'price_per_unit' => 'decimal:4',
            'line_total' => 'decimal:4',
            'discount_amount' => 'decimal:4',
            'tax_amount' => 'decimal:4',
            'metadata' => 'array',
        ];
    }

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
     * Calculate profit for this order item (modern accessor)
     */
    protected function profit(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->line_total - ($this->unit_cost * $this->quantity)
        );
    }

    /**
     * Calculate profit margin percentage (modern accessor)
     */
    protected function profitMargin(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->line_total == 0 ? 0 : ($this->profit / $this->line_total) * 100
        );
    }

    /**
     * Get formatted line total (modern accessor)
     */
    protected function formattedLineTotal(): Attribute
    {
        return Attribute::make(
            get: fn () => '£' . number_format($this->line_total, 2)
        );
    }

    /**
     * Get formatted unit cost (modern accessor)
     */
    protected function formattedUnitCost(): Attribute
    {
        return Attribute::make(
            get: fn () => '£' . number_format($this->unit_cost, 2)
        );
    }

    /**
     * Check if item is profitable (modern accessor)
     */
    protected function isProfitable(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->profit > 0
        );
    }

    /**
     * Get product title through relationship (modern accessor)
     */
    protected function productTitle(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->product?->title ?? 'Unknown Product'
        );
    }

    /**
     * Get product category through relationship (modern accessor)
     */
    protected function productCategory(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->product?->category_name ?? 'Unknown Category'
        );
    }

    /**
     * Get product brand through relationship (modern accessor)
     */
    protected function productBrand(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->product?->brand ?? 'Unknown Brand'
        );
    }

    /**
     * Modern query scopes
     */
    public function scopeBySku(Builder $query, string $sku): Builder
    {
        return $query->where('sku', $sku);
    }

    public function scopeByCategory(Builder $query, string $category): Builder
    {
        return $query->whereHas('product', fn (Builder $q) => $q->where('category_name', $category));
    }

    public function scopeByDateRange(Builder $query, Carbon|string $startDate, Carbon|string $endDate): Builder
    {
        return $query->whereHas('order', function (Builder $q) use ($startDate, $endDate) {
            $q->whereBetween('received_date', [$startDate, $endDate]);
        });
    }

    public function scopeProfitable(Builder $query): Builder
    {
        return $query->whereRaw('line_total > (unit_cost * quantity)');
    }

    public function scopeHighVolume(Builder $query, int $threshold = 10): Builder
    {
        return $query->where('quantity', '>=', $threshold);
    }
}