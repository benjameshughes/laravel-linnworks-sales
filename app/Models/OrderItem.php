<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read float $profit
 */
class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'item_id',
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

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'decimal:4',
            'total_price' => 'decimal:4',
            'cost_price' => 'decimal:4',
            'profit_margin' => 'decimal:4',
            'tax_rate' => 'decimal:4',
            'discount_amount' => 'decimal:4',
            'is_service' => 'boolean',
            'item_attributes' => 'array',
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
            get: fn () => $this->total_price - ($this->cost_price * $this->quantity)
        );
    }

    /**
     * Calculate profit margin percentage (modern accessor)
     */
    protected function profitMargin(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->total_price == 0 ? 0 : ($this->profit / $this->total_price) * 100
        );
    }

    /**
     * Get formatted total price (modern accessor)
     */
    protected function formattedTotalPrice(): Attribute
    {
        return Attribute::make(
            get: fn () => '£'.number_format($this->total_price, 2)
        );
    }

    /**
     * Get formatted cost price (modern accessor)
     */
    protected function formattedCostPrice(): Attribute
    {
        return Attribute::make(
            get: fn () => '£'.number_format($this->cost_price, 2)
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
            get: fn () => $this->product->title ?? 'Unknown Product'
        );
    }

    /**
     * Get product category through relationship (modern accessor)
     */
    protected function productCategory(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->product->category_name ?? 'Unknown Category'
        );
    }

    /**
     * Get product brand through relationship (modern accessor)
     */
    protected function productBrand(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->product->brand ?? 'Unknown Brand'
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
        return $query->whereRaw('total_price > (cost_price * quantity)');
    }

    public function scopeHighVolume(Builder $query, int $threshold = 10): Builder
    {
        return $query->where('quantity', '>=', $threshold);
    }
}
