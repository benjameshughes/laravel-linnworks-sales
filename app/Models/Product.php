<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Carbon\Carbon;

final class Product extends Model
{
    use HasFactory;

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

    protected $casts = [
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
    public function orders()
    {
        return $this->belongsToMany(Order::class, 'order_items', 'sku', 'order_id', 'sku', 'id')
            ->withPivot('quantity', 'price_per_unit', 'line_total', 'unit_cost');
    }

    /**
     * Get all order items for this product across all orders (backward compatibility)
     */
    public function getOrderItems(): Collection
    {
        return $this->orderItems;
    }

    /**
     * Get order items with order context (includes order information)
     */
    public function getOrderItemsWithContext(): Collection
    {
        return Order::all()
            ->map(function($order) {
                $orderItems = collect($order->items ?? [])->where('sku', $this->sku);
                return $orderItems->map(function($item) use ($order) {
                    return array_merge($item, [
                        'order_id' => $order->id,
                        'order_number' => $order->order_number,
                        'channel_name' => $order->channel_name,
                        'received_date' => $order->received_date,
                        'order_total' => $order->total_charge,
                    ]);
                });
            })
            ->flatten(1)
            ->values();
    }

    public function getTotalSold(): int
    {
        return $this->getOrderItems()->sum('quantity');
    }

    public function getTotalRevenue(): float
    {
        return $this->getOrderItems()->sum(function($item) {
            // Use line_total if available and > 0, otherwise calculate from quantity * price
            $lineTotal = $item['line_total'] ?? 0;
            return $lineTotal > 0 ? $lineTotal : ($item['quantity'] ?? 0) * ($item['price_per_unit'] ?? 0);
        });
    }

    public function getAverageSellingPrice(): float
    {
        $items = $this->getOrderItems();
        return $items->isEmpty() ? 0 : $items->avg('price_per_unit');
    }

    public function getProfitMargin(): float
    {
        $avgSellingPrice = $this->getAverageSellingPrice();
        if ($avgSellingPrice === 0 || $this->purchase_price === null) {
            return 0;
        }
        return (($avgSellingPrice - $this->purchase_price) / $avgSellingPrice) * 100;
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
        $stockLevels = $stockItem['StockLevel'] ?? [];
        $pricing = $stockItem['PriceInfo'] ?? [];
        $variations = $stockItem['Variations'] ?? [];
        $images = $stockItem['Images'] ?? [];

        return new self([
            'linnworks_id' => $generalInfo['StockItemId'] ?? $stockItem['StockItemId'] ?? null,
            'sku' => $generalInfo['ItemNumber'] ?? $stockItem['ItemNumber'] ?? null,
            'title' => $generalInfo['ItemTitle'] ?? $stockItem['ItemTitle'] ?? null,
            'description' => $generalInfo['ItemDescription'] ?? $stockItem['ItemDescription'] ?? $generalInfo['MetaData'] ?? null,
            'category_id' => $generalInfo['CategoryId'] ?? $stockItem['CategoryId'] ?? null,
            'category_name' => $generalInfo['CategoryName'] ?? $stockItem['CategoryName'] ?? null,
            'brand' => $generalInfo['BrandName'] ?? $stockItem['BrandName'] ?? null,
            'purchase_price' => $pricing['PurchasePrice'] ?? $generalInfo['PurchasePrice'] ?? $stockItem['PurchasePrice'] ?? null,
            'retail_price' => $pricing['RetailPrice'] ?? $generalInfo['RetailPrice'] ?? $stockItem['RetailPrice'] ?? null,
            'weight' => $generalInfo['Weight'] ?? $stockItem['Weight'] ?? null,
            'dimensions' => [
                'height' => $generalInfo['Height'] ?? $stockItem['Height'] ?? null,
                'width' => $generalInfo['Width'] ?? $stockItem['Width'] ?? null,
                'depth' => $generalInfo['Depth'] ?? $stockItem['Depth'] ?? null,
                'dimension_unit' => $generalInfo['DimensionUnit'] ?? $stockItem['DimensionUnit'] ?? 'cm',
            ],
            'barcode' => $generalInfo['BarcodeNumber'] ?? $stockItem['BarcodeNumber'] ?? null,
            'stock_level' => $stockLevels['Quantity'] ?? $stockItem['Quantity'] ?? 0,
            'stock_minimum' => $stockLevels['MinimumLevel'] ?? $stockItem['MinimumLevel'] ?? 0,
            'stock_in_orders' => $stockLevels['InOrder'] ?? $stockItem['InOrder'] ?? 0,
            'stock_due' => $stockLevels['Due'] ?? $stockItem['Due'] ?? 0,
            'stock_available' => $stockLevels['Available'] ?? $stockItem['Available'] ?? 0,
            'is_active' => !($generalInfo['IsArchived'] ?? $stockItem['IsArchived'] ?? false),
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
                'images' => collect($images)->map(fn($img) => [
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

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeInStock($query)
    {
        return $query->where('stock_available', '>', 0);
    }

    public function scopeLowStock($query)
    {
        return $query->whereColumn('stock_available', '<=', 'stock_minimum');
    }

    public function scopeByCategory($query, string $category)
    {
        return $query->where('category_name', $category);
    }

    /**
     * Get top selling products with sales data
     */
    public static function getTopSellers(int $limit = 10): Collection
    {
        return static::all()
            ->map(function($product) {
                $orderItems = $product->getOrderItems();
                return [
                    'product' => $product,
                    'total_sold' => $orderItems->sum('quantity'),
                    'total_revenue' => $product->getTotalRevenue(), // Use the corrected method
                    'order_count' => $orderItems->count(),
                ];
            })
            ->sortByDesc('total_sold')
            ->take($limit);
    }

    /**
     * Get sales performance by channel for this product
     */
    public function getSalesPerformanceByChannel(): Collection
    {
        return $this->getOrderItemsWithContext()
            ->groupBy('channel_name')
            ->map(function($items, $channel) {
                return [
                    'channel' => $channel,
                    'quantity_sold' => $items->sum('quantity'),
                    'revenue' => $items->sum(function($item) {
                        $lineTotal = $item['line_total'] ?? 0;
                        return $lineTotal > 0 ? $lineTotal : ($item['quantity'] ?? 0) * ($item['price_per_unit'] ?? 0);
                    }),
                    'order_count' => $items->count(),
                    'avg_price' => $items->avg('price_per_unit'),
                ];
            })
            ->sortByDesc('revenue');
    }

    /**
     * Get recent sales activity for this product
     */
    public function getRecentSales(int $days = 30): Collection
    {
        return $this->getOrderItemsWithContext()
            ->filter(function($item) use ($days) {
                return $item['received_date'] >= now()->subDays($days);
            })
            ->sortByDesc('received_date');
    }

    /**
     * Check if this product has been sold recently
     */
    public function hasSoldRecently(int $days = 30): bool
    {
        return $this->getRecentSales($days)->isNotEmpty();
    }

    /**
     * Get profit analysis for this product
     */
    public function getProfitAnalysis(): array
    {
        $orderItems = $this->getOrderItems();
        $totalSold = $orderItems->sum('quantity');
        $totalRevenue = $orderItems->sum(function($item) {
            $lineTotal = $item['line_total'] ?? 0;
            return $lineTotal > 0 ? $lineTotal : ($item['quantity'] ?? 0) * ($item['price_per_unit'] ?? 0);
        });
        $avgSellingPrice = $orderItems->avg('price_per_unit');
        
        $totalCost = $totalSold * ($this->purchase_price ?? 0);
        $totalProfit = $totalRevenue - $totalCost;
        $profitMargin = $totalRevenue > 0 ? ($totalProfit / $totalRevenue) * 100 : 0;

        return [
            'total_sold' => $totalSold,
            'total_revenue' => $totalRevenue,
            'total_cost' => $totalCost,
            'total_profit' => $totalProfit,
            'profit_margin_percent' => $profitMargin,
            'avg_selling_price' => $avgSellingPrice,
            'purchase_price' => $this->purchase_price,
        ];
    }
}