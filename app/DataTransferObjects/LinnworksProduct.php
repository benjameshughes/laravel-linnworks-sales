<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

use Carbon\Carbon;
use Illuminate\Contracts\Support\Arrayable;

/**
 * Data Transfer Object for Linnworks Inventory/Product API responses.
 *
 * Handles both GetStockItems (basic) and GetStockItemFull (detailed) responses.
 */
readonly class LinnworksProduct implements Arrayable
{
    public function __construct(
        public ?string $stockItemId,
        public ?string $sku,
        public ?string $title,
        public ?string $description,
        public ?string $categoryId,
        public ?string $categoryName,
        public ?string $brand,
        public ?string $barcode,
        public ?float $purchasePrice,
        public ?float $retailPrice,
        public ?float $taxRate,
        public ?float $weight,
        public ?string $weightUnit,
        public ?float $height,
        public ?float $width,
        public ?float $depth,
        public ?string $dimensionUnit,
        public int $stockLevel,
        public int $stockMinimum,
        public int $stockInOrders,
        public int $stockDue,
        public int $stockAvailable,
        public bool $isActive,
        public ?Carbon $createdDate,
        public ?array $metadata,
    ) {}

    /**
     * Create DTO from Linnworks API response.
     *
     * Handles both basic (GetStockItems) and detailed (GetStockItemFull) responses.
     */
    public static function fromArray(array $data): self
    {
        // Handle nested structure from GetStockItemFull
        $generalInfo = $data['GeneralInfo'] ?? [];
        $stockLevels = [];

        // StockLevels is an array - get first location
        if (isset($data['StockLevels']) && is_array($data['StockLevels']) && ! empty($data['StockLevels'])) {
            $stockLevels = $data['StockLevels'][0];
        }

        // Extract pricing
        $purchasePrice = self::extractFloat($data['PurchasePrice'] ?? $generalInfo['PurchasePrice'] ?? null);
        $retailPrice = self::extractRetailPrice($data);

        // Only store non-zero purchase price
        if ($purchasePrice !== null && $purchasePrice <= 0) {
            $purchasePrice = null;
        }

        return new self(
            stockItemId: $generalInfo['StockItemId'] ?? $data['StockItemId'] ?? null,
            sku: $generalInfo['ItemNumber'] ?? $data['ItemNumber'] ?? null,
            title: $generalInfo['ItemTitle'] ?? $data['ItemTitle'] ?? null,
            description: $generalInfo['ItemDescription'] ?? $data['ItemDescription'] ?? $generalInfo['MetaData'] ?? $data['MetaData'] ?? null,
            categoryId: $generalInfo['CategoryId'] ?? $data['CategoryId'] ?? null,
            categoryName: $generalInfo['CategoryName'] ?? $data['CategoryName'] ?? null,
            brand: $generalInfo['BrandName'] ?? $data['BrandName'] ?? $data['Brand'] ?? null,
            barcode: $generalInfo['BarcodeNumber'] ?? $data['BarcodeNumber'] ?? null,
            purchasePrice: $purchasePrice,
            retailPrice: $retailPrice,
            taxRate: self::extractFloat($generalInfo['TaxRate'] ?? $data['TaxRate'] ?? null),
            weight: self::extractFloat($generalInfo['Weight'] ?? $data['Weight'] ?? null),
            weightUnit: $generalInfo['WeightUnit'] ?? $data['WeightUnit'] ?? 'kg',
            height: self::extractFloat($generalInfo['Height'] ?? $data['Height'] ?? null),
            width: self::extractFloat($generalInfo['Width'] ?? $data['Width'] ?? null),
            depth: self::extractFloat($generalInfo['Depth'] ?? $data['Depth'] ?? null),
            dimensionUnit: $generalInfo['DimensionUnit'] ?? $data['DimensionUnit'] ?? 'cm',
            stockLevel: (int) ($stockLevels['Level'] ?? $data['Quantity'] ?? $data['StockLevel'] ?? 0),
            stockMinimum: (int) ($stockLevels['MinimumLevel'] ?? $data['MinimumLevel'] ?? 0),
            stockInOrders: (int) ($stockLevels['InOrder'] ?? $data['InOrder'] ?? $data['InOrders'] ?? 0),
            stockDue: (int) ($stockLevels['Due'] ?? $data['Due'] ?? 0),
            stockAvailable: (int) ($stockLevels['Available'] ?? $data['Available'] ?? $data['StockLevel'] ?? 0),
            isActive: ! ($generalInfo['IsArchived'] ?? $data['IsArchived'] ?? false),
            createdDate: self::parseDate($generalInfo['CreationDate'] ?? $data['CreationDate'] ?? null),
            metadata: self::buildMetadata($data, $generalInfo),
        );
    }

    /**
     * Convert to database-ready format for upsert.
     *
     * @return array<string, mixed>
     */
    public function toDatabaseFormat(): array
    {
        return [
            'linnworks_id' => $this->stockItemId,
            'sku' => $this->sku,
            'title' => $this->title,
            'description' => $this->description,
            'category_id' => $this->categoryId,
            'category_name' => $this->categoryName,
            'brand' => $this->brand,
            'barcode' => $this->barcode,
            'purchase_price' => $this->purchasePrice,
            'retail_price' => $this->retailPrice,
            'weight' => $this->weight,
            'dimensions' => [
                'height' => $this->height,
                'width' => $this->width,
                'depth' => $this->depth,
                'dimension_unit' => $this->dimensionUnit,
            ],
            'stock_level' => $this->stockLevel,
            'stock_minimum' => $this->stockMinimum,
            'stock_in_orders' => $this->stockInOrders,
            'stock_due' => $this->stockDue,
            'stock_available' => $this->stockAvailable,
            'is_active' => $this->isActive,
            'created_date' => $this->createdDate?->toDateTimeString(),
            'metadata' => $this->metadata,
            'last_synced_at' => now()->toDateTimeString(),
        ];
    }

    /**
     * Convert to array representation.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'stock_item_id' => $this->stockItemId,
            'sku' => $this->sku,
            'title' => $this->title,
            'description' => $this->description,
            'category_id' => $this->categoryId,
            'category_name' => $this->categoryName,
            'brand' => $this->brand,
            'barcode' => $this->barcode,
            'purchase_price' => $this->purchasePrice,
            'retail_price' => $this->retailPrice,
            'tax_rate' => $this->taxRate,
            'weight' => $this->weight,
            'weight_unit' => $this->weightUnit,
            'height' => $this->height,
            'width' => $this->width,
            'depth' => $this->depth,
            'dimension_unit' => $this->dimensionUnit,
            'stock_level' => $this->stockLevel,
            'stock_minimum' => $this->stockMinimum,
            'stock_in_orders' => $this->stockInOrders,
            'stock_due' => $this->stockDue,
            'stock_available' => $this->stockAvailable,
            'is_active' => $this->isActive,
            'created_date' => $this->createdDate?->toISOString(),
            'metadata' => $this->metadata,
        ];
    }

    /**
     * Check if product has valid pricing data.
     */
    public function hasValidPricing(): bool
    {
        return $this->purchasePrice !== null && $this->purchasePrice > 0;
    }

    /**
     * Calculate margin percentage if pricing is available.
     */
    public function marginPercentage(): ?float
    {
        if ($this->retailPrice === null || $this->retailPrice <= 0) {
            return null;
        }

        if ($this->purchasePrice === null || $this->purchasePrice <= 0) {
            return null;
        }

        return (($this->retailPrice - $this->purchasePrice) / $this->retailPrice) * 100;
    }

    /**
     * Calculate profit per unit if pricing is available.
     */
    public function profitPerUnit(): ?float
    {
        if ($this->retailPrice === null || $this->purchasePrice === null) {
            return null;
        }

        return $this->retailPrice - $this->purchasePrice;
    }

    private static function extractFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (float) $value;
    }

    private static function extractRetailPrice(array $data): ?float
    {
        // Try to get retail price from channel prices first
        if (isset($data['ItemChannelPrices']) && is_array($data['ItemChannelPrices']) && ! empty($data['ItemChannelPrices'])) {
            $price = $data['ItemChannelPrices'][0]['Price'] ?? null;
            if ($price !== null) {
                return (float) $price;
            }
        }

        // Fallback to direct RetailPrice field
        $generalInfo = $data['GeneralInfo'] ?? [];

        return self::extractFloat(
            $data['RetailPrice'] ?? $generalInfo['RetailPrice'] ?? null
        );
    }

    private static function parseDate(?string $date): ?Carbon
    {
        if (! $date) {
            return null;
        }

        try {
            $parsed = Carbon::parse($date);

            if ($parsed->year <= 1970) {
                return null;
            }

            return $parsed;
        } catch (\Exception) {
            return null;
        }
    }

    /**
     * Build metadata array from API response.
     *
     * @return array<string, mixed>
     */
    private static function buildMetadata(array $data, array $generalInfo): array
    {
        $variations = $data['Variations'] ?? [];
        $images = $data['Images'] ?? [];

        return [
            'stock_item_int_id' => $generalInfo['StockItemIntId'] ?? $data['StockItemIntId'] ?? null,
            'tax_rate' => $generalInfo['TaxRate'] ?? $data['TaxRate'] ?? null,
            'weight_unit' => $generalInfo['WeightUnit'] ?? $data['WeightUnit'] ?? 'kg',
            'postal_service' => [
                'id' => $generalInfo['PostalServiceId'] ?? $data['PostalServiceId'] ?? null,
                'name' => $generalInfo['PostalServiceName'] ?? $data['PostalServiceName'] ?? null,
            ],
            'package_group' => [
                'id' => $generalInfo['PackageGroupId'] ?? $data['PackageGroupId'] ?? null,
                'name' => $generalInfo['PackageGroupName'] ?? $data['PackageGroupName'] ?? null,
            ],
            'tracking_type' => $generalInfo['InventoryTrackingType'] ?? $data['InventoryTrackingType'] ?? null,
            'variations' => $variations,
            'images' => collect($images)->map(fn ($img) => [
                'url' => $img['Source'] ?? null,
                'is_main' => $img['IsMain'] ?? false,
                'sort_order' => $img['SortOrder'] ?? 0,
            ])->toArray(),
            'supplier_info' => [
                'supplier_id' => $generalInfo['SupplierId'] ?? $data['SupplierId'] ?? null,
                'supplier_code' => $generalInfo['SupplierPartNumber'] ?? $data['SupplierPartNumber'] ?? null,
            ],
            'fulfillment' => [
                'location_id' => $generalInfo['DefaultLocationId'] ?? $data['DefaultLocationId'] ?? null,
                'location_name' => $generalInfo['DefaultLocationName'] ?? $data['DefaultLocationName'] ?? null,
            ],
        ];
    }
}
