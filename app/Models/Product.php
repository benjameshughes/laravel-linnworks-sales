<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Scout\Searchable;

final class Product extends Model
{
    use HasFactory, Searchable;

    protected $fillable = [
        'linnworks_id',
        'sku',
        'title',
        'description',
        'category_id',
        'category_name',
        'brand',
        'purchase_price',
        'retail_price',
        'weight',
        'dimensions',
        'barcode',
        'stock_level',
        'stock_minimum',
        'stock_in_orders',
        'stock_due',
        'stock_available',
        'is_active',
        'created_date',
        'metadata',
        'last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'purchase_price' => 'decimal:4',
            'retail_price' => 'decimal:4',
            'weight' => 'decimal:3',
            'dimensions' => 'array',
            'stock_level' => 'integer',
            'stock_minimum' => 'integer',
            'stock_in_orders' => 'integer',
            'stock_due' => 'integer',
            'stock_available' => 'integer',
            'is_active' => 'boolean',
            'created_date' => 'datetime',
            'metadata' => 'array',
            'last_synced_at' => 'datetime',
        ];
    }

    /**
     * Get the indexable data array for the model.
     */
    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'sku' => $this->sku,
            'title' => $this->title,
            'description' => $this->description,
            'category_name' => $this->category_name,
            'brand' => $this->brand,
            'barcode' => $this->barcode,
            'is_active' => $this->is_active,
            'stock_level' => $this->stock_level,
            'retail_price' => $this->retail_price,
            'purchase_price' => $this->purchase_price,
            // Searchable text content combined
            'searchable_content' => collect([
                $this->sku,
                $this->title,
                $this->description,
                $this->category_name,
                $this->brand,
                $this->barcode,
            ])->filter()->implode(' '),
        ];
    }

    /**
     * Determine if the model should be searchable.
     */
    public function shouldBeSearchable(): bool
    {
        return $this->is_active && ! empty($this->sku);
    }

    /**
     * Get the value used to index the model.
     */
    public function getScoutKey(): mixed
    {
        return $this->id;
    }

    /**
     * Get the key name used to index the model.
     */
    public function getScoutKeyName(): mixed
    {
        return $this->getKeyName();
    }

    /**
     * Get all order items for this product.
     */
    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class, 'sku', 'sku');
    }

    /**
     * Get all orders that contain this product through order items.
     */
    public function orders(): BelongsToMany
    {
        return $this->belongsToMany(Order::class, 'order_items', 'sku', 'order_id', 'sku', 'id')
            ->withPivot('quantity', 'price_per_unit', 'line_total', 'unit_cost');
    }

    /**
     * Get total quantity sold (modern accessor)
     */
    protected function totalSold(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->orderItems->sum('quantity')
        );
    }

    /**
     * Get total revenue (modern accessor)
     */
    protected function totalRevenue(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->orderItems->sum(fn ($item) => $item->line_total > 0 ? $item->line_total : ($item->quantity * $item->price_per_unit)
            )
        );
    }

    /**
     * Get average selling price (modern accessor)
     */
    protected function averageSellingPrice(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->orderItems->avg('price_per_unit') ?: 0
        );
    }

    /**
     * Get profit margin percentage (modern accessor)
     */
    protected function profitMargin(): Attribute
    {
        return Attribute::make(
            get: function () {
                if ($this->average_selling_price === 0 || $this->purchase_price === null) {
                    return 0;
                }

                return (($this->average_selling_price - $this->purchase_price) / $this->average_selling_price) * 100;
            }
        );
    }

    /**
     * Get total profit (modern accessor)
     */
    protected function totalProfit(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->total_revenue - ($this->total_sold * ($this->purchase_price ?: 0))
        );
    }

    /**
     * Check if product is low stock (modern accessor)
     */
    protected function isLowStock(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->stock_available <= $this->stock_minimum
        );
    }

    /**
     * Check if product is out of stock (modern accessor)
     */
    protected function isOutOfStock(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->stock_available <= 0
        );
    }

    /**
     * Get stock status (modern accessor)
     */
    protected function stockStatus(): Attribute
    {
        return Attribute::make(
            get: fn () => match (true) {
                $this->is_out_of_stock => 'out_of_stock',
                $this->is_low_stock => 'low_stock',
                default => 'in_stock'
            }
        );
    }

    /**
     * Get formatted price (modern accessor)
     */
    protected function formattedRetailPrice(): Attribute
    {
        return Attribute::make(
            get: fn () => '£'.number_format((float) ($this->retail_price ?: 0), 2)
        );
    }

    /**
     * Get formatted purchase price (modern accessor)
     */
    protected function formattedPurchasePrice(): Attribute
    {
        return Attribute::make(
            get: fn () => '£'.number_format((float) ($this->purchase_price ?: 0), 2)
        );
    }

    /**
     * Get display name combining title and SKU (modern accessor)
     */
    protected function displayName(): Attribute
    {
        return Attribute::make(
            get: fn () => "{$this->title} ({$this->sku})"
        );
    }

    /**
     * Check if product has been sold recently (modern accessor)
     */
    protected function hasSoldRecently(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->orderItems
                ->filter(fn ($item) => $item->order && $item->order->received_at >= now()->subDays(30)
                )->isNotEmpty()
        );
    }

    public static function fromLinnworksInventory(array $stockItem): self
    {
        return new self([
            'linnworks_id' => $stockItem['StockItemId'] ?? null,
            'sku' => $stockItem['ItemNumber'] ?? null,
            'title' => $stockItem['ItemTitle'] ?? null,
            'description' => $stockItem['MetaData'] ?? null,
            'category_id' => $stockItem['CategoryId'] ?? null,
            'category_name' => $stockItem['CategoryName'] ?? null,
            'brand' => null, // Not available in GetStockItems response
            'purchase_price' => $stockItem['PurchasePrice'] ?? null,
            'retail_price' => $stockItem['RetailPrice'] ?? null,
            'weight' => $stockItem['Weight'] ?? null,
            'dimensions' => [
                'height' => $stockItem['Height'] ?? null,
                'width' => $stockItem['Width'] ?? null,
                'depth' => $stockItem['Depth'] ?? null,
            ],
            'barcode' => $stockItem['BarcodeNumber'] ?? null,
            'stock_level' => $stockItem['Quantity'] ?? 0,
            'stock_minimum' => $stockItem['MinimumLevel'] ?? 0,
            'stock_in_orders' => $stockItem['InOrder'] ?? 0,
            'stock_due' => $stockItem['Due'] ?? 0,
            'stock_available' => $stockItem['Available'] ?? 0,
            'is_active' => true, // GetStockItems doesn't return archived items
            'created_date' => isset($stockItem['CreationDate'])
                ? Carbon::parse($stockItem['CreationDate'])
                : null,
            'metadata' => [
                'stock_item_int_id' => $stockItem['StockItemIntId'] ?? null,
                'tax_rate' => $stockItem['TaxRate'] ?? null,
                'postal_service' => [
                    'id' => $stockItem['PostalServiceId'] ?? null,
                    'name' => $stockItem['PostalServiceName'] ?? null,
                ],
                'package_group' => [
                    'id' => $stockItem['PackageGroupId'] ?? null,
                    'name' => $stockItem['PackageGroupName'] ?? null,
                ],
                'tracking_type' => $stockItem['InventoryTrackingType'] ?? null,
            ],
            'last_synced_at' => now(),
        ]);
    }

    /**
     * Create Product from detailed Linnworks stock item (GetStockItemsFull response)
     */
    public static function fromLinnworksDetailedInventory(array $stockItem): self
    {
        // Handle nested structure from GetStockItemsFull
        $generalInfo = $stockItem['GeneralInfo'] ?? $stockItem;
        $variations = $stockItem['Variations'] ?? [];
        $images = $stockItem['Images'] ?? [];

        // StockLevels is an array - get first location or aggregate
        $stockLevels = [];
        if (isset($stockItem['StockLevels']) && is_array($stockItem['StockLevels']) && ! empty($stockItem['StockLevels'])) {
            $stockLevels = $stockItem['StockLevels'][0]; // Use first location
        }

        // Extract pricing - PurchasePrice at root level, RetailPrice from channel prices
        $purchasePrice = $stockItem['PurchasePrice'] ?? $generalInfo['PurchasePrice'] ?? null;
        $retailPrice = null;

        // Try to get retail price from channel prices (use first available)
        if (isset($stockItem['ItemChannelPrices']) && is_array($stockItem['ItemChannelPrices']) && ! empty($stockItem['ItemChannelPrices'])) {
            $retailPrice = $stockItem['ItemChannelPrices'][0]['Price'] ?? null;
        }

        // Fallback to any RetailPrice field found
        $retailPrice = $retailPrice ?? $stockItem['RetailPrice'] ?? $generalInfo['RetailPrice'] ?? null;

        // Only store non-zero purchase price
        if ($purchasePrice !== null && $purchasePrice <= 0) {
            $purchasePrice = null;
        }

        return new self([
            'linnworks_id' => $generalInfo['StockItemId'] ?? $stockItem['StockItemId'] ?? null,
            'sku' => $generalInfo['ItemNumber'] ?? $stockItem['ItemNumber'] ?? null,
            'title' => $generalInfo['ItemTitle'] ?? $stockItem['ItemTitle'] ?? null,
            'description' => $generalInfo['ItemDescription'] ?? $stockItem['ItemDescription'] ?? $generalInfo['MetaData'] ?? $stockItem['MetaData'] ?? null,
            'category_id' => $generalInfo['CategoryId'] ?? $stockItem['CategoryId'] ?? null,
            'category_name' => $generalInfo['CategoryName'] ?? $stockItem['CategoryName'] ?? null,
            'brand' => $generalInfo['BrandName'] ?? $stockItem['BrandName'] ?? null,
            'purchase_price' => $purchasePrice,
            'retail_price' => $retailPrice,
            'weight' => $generalInfo['Weight'] ?? $stockItem['Weight'] ?? null,
            'dimensions' => [
                'height' => $generalInfo['Height'] ?? $stockItem['Height'] ?? null,
                'width' => $generalInfo['Width'] ?? $stockItem['Width'] ?? null,
                'depth' => $generalInfo['Depth'] ?? $stockItem['Depth'] ?? null,
                'dimension_unit' => $generalInfo['DimensionUnit'] ?? $stockItem['DimensionUnit'] ?? 'cm',
            ],
            'barcode' => $generalInfo['BarcodeNumber'] ?? $stockItem['BarcodeNumber'] ?? null,
            'stock_level' => $stockLevels['Level'] ?? $stockItem['Quantity'] ?? 0,
            'stock_minimum' => $stockLevels['MinimumLevel'] ?? $stockItem['MinimumLevel'] ?? 0,
            'stock_in_orders' => $stockLevels['InOrder'] ?? $stockItem['InOrder'] ?? 0,
            'stock_due' => $stockLevels['Due'] ?? $stockItem['Due'] ?? 0,
            'stock_available' => $stockLevels['Available'] ?? $stockItem['Available'] ?? 0,
            'is_active' => ! ($generalInfo['IsArchived'] ?? $stockItem['IsArchived'] ?? false),
            'created_date' => isset($generalInfo['CreationDate']) || isset($stockItem['CreationDate'])
                ? Carbon::parse($generalInfo['CreationDate'] ?? $stockItem['CreationDate'])
                : null,
            'metadata' => [
                'stock_item_int_id' => $generalInfo['StockItemIntId'] ?? $stockItem['StockItemIntId'] ?? null,
                'tax_rate' => $generalInfo['TaxRate'] ?? $stockItem['TaxRate'] ?? null,
                'postal_service' => [
                    'id' => $generalInfo['PostalServiceId'] ?? $stockItem['PostalServiceId'] ?? null,
                    'name' => $generalInfo['PostalServiceName'] ?? $stockItem['PostalServiceName'] ?? null,
                ],
                'package_group' => [
                    'id' => $generalInfo['PackageGroupId'] ?? $stockItem['PackageGroupId'] ?? null,
                    'name' => $generalInfo['PackageGroupName'] ?? $stockItem['PackageGroupName'] ?? null,
                ],
                'tracking_type' => $generalInfo['InventoryTrackingType'] ?? $stockItem['InventoryTrackingType'] ?? null,
                'weight_unit' => $generalInfo['WeightUnit'] ?? $stockItem['WeightUnit'] ?? 'kg',
                'variations' => $variations,
                'images' => collect($images)->map(fn ($img) => [
                    'url' => $img['Source'] ?? null,
                    'is_main' => $img['IsMain'] ?? false,
                    'sort_order' => $img['SortOrder'] ?? 0,
                ])->toArray(),
                'supplier_info' => [
                    'supplier_id' => $generalInfo['SupplierId'] ?? $stockItem['SupplierId'] ?? null,
                    'supplier_code' => $generalInfo['SupplierPartNumber'] ?? $stockItem['SupplierPartNumber'] ?? null,
                ],
                'fulfillment' => [
                    'location_id' => $generalInfo['DefaultLocationId'] ?? $stockItem['DefaultLocationId'] ?? null,
                    'location_name' => $generalInfo['DefaultLocationName'] ?? $stockItem['DefaultLocationName'] ?? null,
                ],
                'sync_type' => 'detailed', // Track which sync method was used
            ],
            'last_synced_at' => now(),
        ]);
    }

    /**
     * Modern query scopes
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeInStock(Builder $query): Builder
    {
        return $query->where('stock_available', '>', 0);
    }

    public function scopeLowStock(Builder $query): Builder
    {
        return $query->whereColumn('stock_available', '<=', 'stock_minimum');
    }

    public function scopeOutOfStock(Builder $query): Builder
    {
        return $query->where('stock_available', '<=', 0);
    }

    public function scopeByCategory(Builder $query, string $category): Builder
    {
        return $query->where('category_name', $category);
    }

    public function scopeByBrand(Builder $query, string $brand): Builder
    {
        return $query->where('brand', $brand);
    }

    public function scopeByPriceRange(Builder $query, float $min, float $max): Builder
    {
        return $query->whereBetween('retail_price', [$min, $max]);
    }

    public function scopeRecentlyUpdated(Builder $query, int $days = 7): Builder
    {
        return $query->where('updated_at', '>=', now()->subDays($days));
    }

    public function scopeWithSales(Builder $query): Builder
    {
        return $query->whereHas('orderItems');
    }

    public function scopeWithoutSales(Builder $query): Builder
    {
        return $query->whereDoesntHave('orderItems');
    }
}
