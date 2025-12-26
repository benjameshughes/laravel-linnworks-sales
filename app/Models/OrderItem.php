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

        // Linnworks identifiers
        'item_id',
        'stock_item_id',
        'stock_item_int_id',
        'row_id',
        'item_number',

        // SKU & Titles
        'sku',
        'item_title',
        'item_source',
        'channel_sku',
        'channel_title',
        'barcode_number',

        // Quantity
        'quantity',
        'part_shipped_qty',

        // Category
        'category_name',

        // Pricing
        'price_per_unit',
        'unit_cost',
        'line_total',
        'cost',
        'cost_inc_tax',
        'despatch_stock_unit_cost',
        'discount',
        'discount_value',

        // Tax
        'tax',
        'tax_rate',
        'sales_tax',
        'tax_cost_inclusive',

        // Stock levels
        'stock_levels_specified',
        'stock_level',
        'available_stock',
        'on_order',
        'stock_level_indicator',

        // Inventory tracking
        'inventory_tracking_type',
        'is_batched_stock_item',
        'is_warehouse_managed',
        'is_unlinked',
        'batch_number_scan_required',
        'serial_number_scan_required',

        // Shipping
        'part_shipped',
        'weight',
        'shipping_cost',
        'bin_rack',
        'bin_racks',

        // Product attributes
        'is_service',
        'has_image',
        'image_id',
        'market',

        // Composite items & additional data
        'composite_sub_items',
        'additional_info',

        // Metadata
        'added_at',
    ];

    protected function casts(): array
    {
        return [
            // Quantities
            'quantity' => 'integer',
            'part_shipped_qty' => 'integer',
            'stock_level' => 'integer',
            'available_stock' => 'integer',
            'on_order' => 'integer',
            'stock_level_indicator' => 'integer',

            // Pricing
            'price_per_unit' => 'decimal:2',
            'unit_cost' => 'decimal:2',
            'line_total' => 'decimal:2',
            'cost' => 'decimal:2',
            'cost_inc_tax' => 'decimal:2',
            'despatch_stock_unit_cost' => 'decimal:2',
            'discount' => 'decimal:2',
            'discount_value' => 'decimal:2',

            // Tax
            'tax' => 'decimal:2',
            'tax_rate' => 'decimal:4',
            'sales_tax' => 'decimal:2',

            // Weight & shipping
            'weight' => 'decimal:3',
            'shipping_cost' => 'decimal:2',

            // Booleans
            'tax_cost_inclusive' => 'boolean',
            'stock_levels_specified' => 'boolean',
            'is_batched_stock_item' => 'boolean',
            'is_warehouse_managed' => 'boolean',
            'is_unlinked' => 'boolean',
            'batch_number_scan_required' => 'boolean',
            'serial_number_scan_required' => 'boolean',
            'part_shipped' => 'boolean',
            'is_service' => 'boolean',
            'has_image' => 'boolean',

            // JSON
            'bin_racks' => 'array',
            'composite_sub_items' => 'array',
            'additional_info' => 'array',

            // Dates
            'added_at' => 'datetime',
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
     * Get formatted total price (modern accessor)
     */
    protected function formattedTotalPrice(): Attribute
    {
        return Attribute::make(
            get: fn () => '£'.number_format($this->line_total, 2)
        );
    }

    /**
     * Get formatted cost price (modern accessor)
     */
    protected function formattedCostPrice(): Attribute
    {
        return Attribute::make(
            get: fn () => '£'.number_format($this->unit_cost, 2)
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
        return $query->whereRaw('line_total > (unit_cost * quantity)');
    }

    public function scopeHighVolume(Builder $query, int $threshold = 10): Builder
    {
        return $query->where('quantity', '>=', $threshold);
    }
}
