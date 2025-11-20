<?php

namespace App\DataTransferObjects;

use Illuminate\Contracts\Support\Arrayable;

readonly class LinnworksOrderItem implements Arrayable
{
    public function __construct(
        // Core identification
        public ?string $itemId,
        public ?string $stockItemId,
        public ?int $stockItemIntId,
        public ?string $rowId,
        public ?string $itemNumber,

        // SKU & Titles
        public ?string $sku,
        public ?string $itemTitle,
        public ?string $itemSource,
        public ?string $channelSku,
        public ?string $channelTitle,
        public ?string $barcodeNumber,

        // Quantity
        public int $quantity,
        public ?int $partShippedQty,

        // Category
        public ?string $categoryName,

        // Pricing
        public float $pricePerUnit,
        public float $unitCost,
        public float $lineTotal,
        public float $cost,
        public float $costIncTax,
        public float $despatchStockUnitCost,
        public float $discount,
        public float $discountValue,

        // Tax
        public float $tax,
        public float $taxRate,
        public float $salesTax,
        public bool $taxCostInclusive,

        // Stock levels
        public bool $stockLevelsSpecified,
        public ?int $stockLevel,
        public ?int $availableStock,
        public ?int $onOrder,
        public ?int $stockLevelIndicator,

        // Inventory tracking
        public ?int $inventoryTrackingType,
        public bool $isBatchedStockItem,
        public bool $isWarehouseManaged,
        public bool $isUnlinked,
        public bool $batchNumberScanRequired,
        public bool $serialNumberScanRequired,

        // Shipping
        public bool $partShipped,
        public float $weight,
        public float $shippingCost,
        public ?string $binRack,
        public ?array $binRacks,

        // Product attributes
        public bool $isService,
        public bool $hasImage,
        public ?string $imageId,
        public ?int $market,

        // Composite items & additional data
        public ?array $compositeSubItems,
        public ?array $additionalInfo,

        // Metadata
        public ?string $addedDate,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            // Core identification
            itemId: $data['ItemId'] ?? $data['item_id'] ?? null,
            stockItemId: $data['StockItemId'] ?? $data['stock_item_id'] ?? null,
            stockItemIntId: isset($data['StockItemIntId']) ? (int) $data['StockItemIntId'] : null,
            rowId: $data['RowId'] ?? $data['row_id'] ?? null,
            itemNumber: $data['ItemNumber'] ?? $data['item_number'] ?? null,

            // SKU & Titles (FIX: Use 'Title' from API, not 'ItemTitle')
            sku: $data['SKU'] ?? $data['sku'] ?? null,
            itemTitle: $data['Title'] ?? $data['ItemTitle'] ?? $data['item_title'] ?? null,
            itemSource: $data['ItemSource'] ?? $data['item_source'] ?? null,
            channelSku: $data['ChannelSKU'] ?? $data['channel_sku'] ?? null,
            channelTitle: $data['ChannelTitle'] ?? $data['channel_title'] ?? null,
            barcodeNumber: $data['BarcodeNumber'] ?? $data['barcode_number'] ?? null,

            // Quantity
            quantity: (int) ($data['Quantity'] ?? $data['quantity'] ?? 0),
            partShippedQty: isset($data['PartShippedQty']) ? (int) $data['PartShippedQty'] : null,

            // Category
            categoryName: $data['CategoryName'] ?? $data['category_name'] ?? null,

            // Pricing (FIX: Use 'Cost' from API, not 'LineTotal')
            pricePerUnit: (float) ($data['PricePerUnit'] ?? $data['price_per_unit'] ?? 0),
            unitCost: (float) ($data['UnitCost'] ?? $data['unit_cost'] ?? 0),
            lineTotal: (float) ($data['Cost'] ?? $data['LineTotal'] ?? $data['line_total'] ?? 0),
            cost: (float) ($data['Cost'] ?? $data['cost'] ?? 0),
            costIncTax: (float) ($data['CostIncTax'] ?? $data['cost_inc_tax'] ?? 0),
            despatchStockUnitCost: (float) ($data['DespatchStockUnitCost'] ?? $data['despatch_stock_unit_cost'] ?? 0),
            discount: (float) ($data['Discount'] ?? $data['discount'] ?? 0),
            discountValue: (float) ($data['DiscountValue'] ?? $data['discount_value'] ?? 0),

            // Tax
            tax: (float) ($data['Tax'] ?? $data['tax'] ?? 0),
            taxRate: (float) ($data['TaxRate'] ?? $data['tax_rate'] ?? 0),
            salesTax: (float) ($data['SalesTax'] ?? $data['sales_tax'] ?? 0),
            taxCostInclusive: (bool) ($data['TaxCostInclusive'] ?? $data['tax_cost_inclusive'] ?? false),

            // Stock levels
            stockLevelsSpecified: (bool) ($data['StockLevelsSpecified'] ?? $data['stock_levels_specified'] ?? false),
            stockLevel: isset($data['Level']) ? (int) $data['Level'] : null,
            availableStock: isset($data['AvailableStock']) ? (int) $data['AvailableStock'] : null,
            onOrder: isset($data['OnOrder']) ? (int) $data['OnOrder'] : null,
            stockLevelIndicator: isset($data['StockLevelIndicator']) ? (int) $data['StockLevelIndicator'] : null,

            // Inventory tracking
            inventoryTrackingType: isset($data['InventoryTrackingType']) ? (int) $data['InventoryTrackingType'] : null,
            isBatchedStockItem: (bool) ($data['isBatchedStockItem'] ?? $data['is_batched_stock_item'] ?? false),
            isWarehouseManaged: (bool) ($data['IsWarehouseManaged'] ?? $data['is_warehouse_managed'] ?? false),
            isUnlinked: (bool) ($data['IsUnlinked'] ?? $data['is_unlinked'] ?? false),
            batchNumberScanRequired: (bool) ($data['BatchNumberScanRequired'] ?? $data['batch_number_scan_required'] ?? false),
            serialNumberScanRequired: (bool) ($data['SerialNumberScanRequired'] ?? $data['serial_number_scan_required'] ?? false),

            // Shipping
            partShipped: (bool) ($data['PartShipped'] ?? $data['part_shipped'] ?? false),
            weight: (float) ($data['Weight'] ?? $data['weight'] ?? 0),
            shippingCost: (float) ($data['ShippingCost'] ?? $data['shipping_cost'] ?? 0),
            binRack: $data['BinRack'] ?? $data['bin_rack'] ?? null,
            binRacks: $data['BinRacks'] ?? $data['bin_racks'] ?? null,

            // Product attributes
            isService: (bool) ($data['IsService'] ?? $data['is_service'] ?? false),
            hasImage: (bool) ($data['HasImage'] ?? $data['has_image'] ?? false),
            imageId: $data['ImageId'] ?? $data['image_id'] ?? null,
            market: isset($data['Market']) ? (int) $data['Market'] : null,

            // Composite items & additional data
            compositeSubItems: $data['CompositeSubItems'] ?? $data['composite_sub_items'] ?? null,
            additionalInfo: $data['AdditionalInfo'] ?? $data['additional_info'] ?? null,

            // Metadata
            addedDate: $data['AddedDate'] ?? $data['added_date'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'item_id' => $this->itemId,
            'stock_item_id' => $this->stockItemId,
            'stock_item_int_id' => $this->stockItemIntId,
            'row_id' => $this->rowId,
            'item_number' => $this->itemNumber,
            'sku' => $this->sku,
            'item_title' => $this->itemTitle,
            'item_source' => $this->itemSource,
            'channel_sku' => $this->channelSku,
            'channel_title' => $this->channelTitle,
            'barcode_number' => $this->barcodeNumber,
            'quantity' => $this->quantity,
            'part_shipped_qty' => $this->partShippedQty,
            'category_name' => $this->categoryName,
            'price_per_unit' => $this->pricePerUnit,
            'unit_cost' => $this->unitCost,
            'line_total' => $this->lineTotal,
            'cost' => $this->cost,
            'cost_inc_tax' => $this->costIncTax,
            'despatch_stock_unit_cost' => $this->despatchStockUnitCost,
            'discount' => $this->discount,
            'discount_value' => $this->discountValue,
            'tax' => $this->tax,
            'tax_rate' => $this->taxRate,
            'sales_tax' => $this->salesTax,
            'tax_cost_inclusive' => $this->taxCostInclusive,
            'stock_levels_specified' => $this->stockLevelsSpecified,
            'stock_level' => $this->stockLevel,
            'available_stock' => $this->availableStock,
            'on_order' => $this->onOrder,
            'stock_level_indicator' => $this->stockLevelIndicator,
            'inventory_tracking_type' => $this->inventoryTrackingType,
            'is_batched_stock_item' => $this->isBatchedStockItem,
            'is_warehouse_managed' => $this->isWarehouseManaged,
            'is_unlinked' => $this->isUnlinked,
            'batch_number_scan_required' => $this->batchNumberScanRequired,
            'serial_number_scan_required' => $this->serialNumberScanRequired,
            'part_shipped' => $this->partShipped,
            'weight' => $this->weight,
            'shipping_cost' => $this->shippingCost,
            'bin_rack' => $this->binRack,
            'bin_racks' => $this->binRacks,
            'is_service' => $this->isService,
            'has_image' => $this->hasImage,
            'image_id' => $this->imageId,
            'market' => $this->market,
            'composite_sub_items' => $this->compositeSubItems,
            'additional_info' => $this->additionalInfo,
            'added_date' => $this->addedDate,
        ];
    }

    /**
     * Convert to database-ready format for bulk insert/update
     */
    public function toDatabaseFormat(): array
    {
        return [
            // Note: order_id will be set by BulkImportOrders
            'item_id' => $this->itemId,
            'stock_item_id' => $this->stockItemId,
            'stock_item_int_id' => $this->stockItemIntId,
            'row_id' => $this->rowId,
            'item_number' => $this->itemNumber,
            'sku' => $this->sku,
            'item_title' => $this->itemTitle,
            'item_source' => $this->itemSource,
            'channel_sku' => $this->channelSku,
            'channel_title' => $this->channelTitle,
            'barcode_number' => $this->barcodeNumber,
            'quantity' => $this->quantity,
            'part_shipped_qty' => $this->partShippedQty,
            'category_name' => $this->categoryName,
            'price_per_unit' => $this->pricePerUnit,
            'unit_cost' => $this->unitCost,
            'line_total' => $this->lineTotal,
            'cost' => $this->cost,
            'cost_inc_tax' => $this->costIncTax,
            'despatch_stock_unit_cost' => $this->despatchStockUnitCost,
            'discount' => $this->discount,
            'discount_value' => $this->discountValue,
            'tax' => $this->tax,
            'tax_rate' => $this->taxRate,
            'sales_tax' => $this->salesTax,
            'tax_cost_inclusive' => $this->taxCostInclusive,
            'stock_levels_specified' => $this->stockLevelsSpecified,
            'stock_level' => $this->stockLevel,
            'available_stock' => $this->availableStock,
            'on_order' => $this->onOrder,
            'stock_level_indicator' => $this->stockLevelIndicator,
            'inventory_tracking_type' => $this->inventoryTrackingType,
            'is_batched_stock_item' => $this->isBatchedStockItem,
            'is_warehouse_managed' => $this->isWarehouseManaged,
            'is_unlinked' => $this->isUnlinked,
            'batch_number_scan_required' => $this->batchNumberScanRequired,
            'serial_number_scan_required' => $this->serialNumberScanRequired,
            'part_shipped' => $this->partShipped,
            'weight' => $this->weight,
            'shipping_cost' => $this->shippingCost,
            'bin_rack' => $this->binRack,
            'bin_racks' => $this->binRacks ? json_encode($this->binRacks) : null,
            'is_service' => $this->isService,
            'has_image' => $this->hasImage,
            'image_id' => $this->imageId,
            'market' => $this->market,
            'composite_sub_items' => $this->compositeSubItems ? json_encode($this->compositeSubItems) : null,
            'additional_info' => $this->additionalInfo ? json_encode($this->additionalInfo) : null,
            'added_at' => $this->addedDate ? \Carbon\Carbon::parse($this->addedDate)->setTimezone(config('app.timezone'))->toDateTimeString() : null,
            'created_at' => now()->toDateTimeString(),
            'updated_at' => now()->toDateTimeString(),
        ];
    }

    public function totalValue(): float
    {
        return $this->quantity * $this->pricePerUnit;
    }

    public function profit(): float
    {
        return ($this->pricePerUnit - $this->unitCost) * $this->quantity;
    }

    public function profitMargin(): float
    {
        return $this->pricePerUnit > 0
            ? (($this->pricePerUnit - $this->unitCost) / $this->pricePerUnit) * 100
            : 0;
    }
}
