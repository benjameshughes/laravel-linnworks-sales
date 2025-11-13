<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

use Carbon\Carbon;
use Illuminate\Support\Str;

/**
 * MEGA DTO for bulk order imports
 *
 * Converts LinnworksOrder into flat arrays optimized for DB::table()->insert()
 * All data is prepared upfront in a format ready for bulk database operations.
 *
 * No Eloquent, no pending data, no lazy loading - just pure DB-ready arrays.
 */
readonly class OrderImportDTO
{
    public function __construct(
        // Main order record (ready for DB::table('orders')->insert())
        public array $order,

        // Related data (ready for DB::table()->insert())
        public array $items,
        public ?array $shipping,
        public array $notes,
        public array $properties,
        public array $identifiers,

        // Metadata for tracking
        public string $linnworksOrderId,
        public int $orderNumber,
    ) {}

    /**
     * Safely convert Carbon date to datetime string, handling DST transitions
     *
     * During DST spring-forward, times in the "missing hour" don't exist.
     * Example: UK spring 2025 - March 30 at 01:00 jumps to 02:00
     * A timestamp like "2025-03-30 01:30:00" is invalid.
     *
     * This method detects such cases and adjusts the time forward.
     */
    private static function safeToDateTimeString(?Carbon $date): ?string
    {
        if (! $date) {
            return null;
        }

        // Filter out Unix epoch dates (1970-01-01)
        if ($date->year <= 1970) {
            return null;
        }

        // Linnworks sends dates in UTC. Convert to app timezone (Europe/London)
        // before converting to string. This handles DST automatically.
        // Example: 2025-03-30 01:56:29 UTC -> 2025-03-30 02:56:29 BST (valid!)
        $localTime = $date->copy()->setTimezone(config('app.timezone'));

        return $localTime->toDateTimeString();
    }

    /**
     * Convert LinnworksOrder DTO to OrderImportDTO (MEGA data format)
     *
     * This method does ALL the heavy lifting upfront:
     * - Normalizes channel names
     * - Determines open/processed status
     * - Flattens nested data structures
     * - Prepares all relationships as flat arrays
     */
    public static function fromLinnworks(LinnworksOrder $linnworks): self
    {
        $isProcessed = $linnworks->isProcessed();
        $channelName = self::normalizeChannelName($linnworks->orderSource);
        $subSource = self::normalizeChannelName($linnworks->subsource);

        // Main order record (flat array for DB::insert)
        $orderData = [
            'linnworks_order_id' => $linnworks->orderId,
            'order_id' => $linnworks->orderId,
            'order_number' => $linnworks->orderNumber,
            'channel_name' => $channelName,
            'channel_reference_number' => $linnworks->channelReferenceNumber,
            'secondary_reference' => $linnworks->secondaryReference,
            'external_reference' => $linnworks->externalReferenceNum,
            'received_date' => self::safeToDateTimeString($linnworks->receivedDate),
            'processed_date' => self::safeToDateTimeString($linnworks->processedDate),
            'currency' => $linnworks->currency,
            'total_charge' => $linnworks->totalCharge,
            'total_paid' => $linnworks->totalCharge, // Assume total charge = total paid
            'total_discount' => $linnworks->totalDiscount,
            'postage_cost' => $linnworks->postageCost,
            'postage_cost_ex_tax' => $linnworks->postageCostExTax,
            'tax' => $linnworks->tax,
            'country_tax_rate' => $linnworks->countryTaxRate,
            'conversion_rate' => $linnworks->conversionRate,
            'profit_margin' => $linnworks->profitMargin,
            'status' => self::mapOrderStatus($linnworks->orderStatus, $isProcessed),
            'order_source' => $linnworks->orderSource,
            'subsource' => $linnworks->subsource,
            'order_status' => $linnworks->orderStatus,
            'location_id' => $linnworks->locationId,
            'is_open' => ! $isProcessed,
            'is_paid' => $linnworks->isPaid,
            'paid_date' => self::safeToDateTimeString($linnworks->paidDate),
            'is_cancelled' => $linnworks->isCancelled,
            'is_processed' => $isProcessed,
            'last_synced_at' => now()->toDateTimeString(),
            'sync_status' => 'synced',
            'raw_data' => json_encode([
                'linnworks_order_id' => $linnworks->orderId,
                'order_number' => $linnworks->orderNumber,
                'order_status' => $linnworks->orderStatus,
                'location_id' => $linnworks->locationId,
            ]),
            // Extended order fields - GeneralInfo
            'marker' => $linnworks->marker,
            'is_parked' => $linnworks->isParked,
            'label_printed' => $linnworks->labelPrinted,
            'label_error' => $linnworks->labelError,
            'invoice_printed' => $linnworks->invoicePrinted,
            'pick_list_printed' => $linnworks->pickListPrinted,
            'is_rule_run' => $linnworks->isRuleRun,
            'part_shipped' => $linnworks->partShipped,
            'has_scheduled_delivery' => $linnworks->hasScheduledDelivery,
            'pickwave_ids' => $linnworks->pickwaveIds ? json_encode($linnworks->pickwaveIds) : null,
            'despatch_by_date' => self::safeToDateTimeString($linnworks->despatchByDate),
            'dispatched_at' => null, // Set when order is actually dispatched
            'num_items' => $linnworks->numItems,
            // Payment
            'payment_method' => $linnworks->paymentMethod,
            'payment_method_id' => $linnworks->paymentMethodId,
            'created_at' => now()->toDateTimeString(),
            'updated_at' => now()->toDateTimeString(),
        ];

        // Order items (flat array for DB::insert)
        $itemsData = $linnworks->items->map(fn ($item) => [
            'order_id' => null, // Will be set by OrderBulkWriter
            // Identification
            'item_id' => $item->itemId,
            'linnworks_item_id' => $item->stockItemId,
            'stock_item_int_id' => $item->stockItemIntId,
            'row_id' => $item->rowId,
            'item_number' => $item->itemNumber,
            // SKU & Titles
            'sku' => $item->sku,
            'title' => $item->itemTitle ?? $item->sku ?? 'Unknown Item', // Fallback for null titles
            'item_source' => $item->itemSource,
            'channel_sku' => $item->channelSku,
            'channel_title' => $item->channelTitle,
            'barcode_number' => $item->barcodeNumber,
            'description' => null,
            'category' => $item->categoryName,
            // Quantity
            'quantity' => $item->quantity,
            'part_shipped_qty' => $item->partShippedQty,
            // Pricing
            'unit_price' => $item->pricePerUnit,
            'total_price' => $item->lineTotal,
            'cost_price' => $item->unitCost,
            'cost' => $item->cost,
            'cost_inc_tax' => $item->costIncTax,
            'despatch_stock_unit_cost' => $item->despatchStockUnitCost,
            'discount' => $item->discount,
            'discount_amount' => $item->discountValue,
            'profit_margin' => null, // Calculated by SalesMetrics service
            'shipping_cost' => $item->shippingCost,
            // Tax
            'tax_rate' => $item->taxRate,
            'item_tax' => $item->tax,
            'sales_tax' => $item->salesTax,
            'tax_cost_inclusive' => $item->taxCostInclusive,
            // Stock & Inventory
            'stock_levels_specified' => $item->stockLevelsSpecified,
            'stock_level' => $item->stockLevel,
            'available_stock' => $item->availableStock,
            'on_order' => $item->onOrder,
            'stock_level_indicator' => $item->stockLevelIndicator,
            'inventory_tracking_type' => $item->inventoryTrackingType,
            'is_batched_stock_item' => $item->isBatchedStockItem,
            'is_warehouse_managed' => $item->isWarehouseManaged,
            'is_unlinked' => $item->isUnlinked,
            'batch_number_scan_required' => $item->batchNumberScanRequired,
            'serial_number_scan_required' => $item->serialNumberScanRequired,
            // Shipping & Physical
            'part_shipped' => $item->partShipped,
            'weight' => $item->weight,
            'bin_rack' => $item->binRack,
            'bin_racks' => $item->binRacks ? json_encode($item->binRacks) : null,
            // Product attributes
            'is_service' => $item->isService,
            'has_image' => $item->hasImage,
            'image_id' => $item->imageId,
            'market' => $item->market,
            // Composite & Additional data
            'composite_sub_items' => $item->compositeSubItems ? json_encode($item->compositeSubItems) : null,
            'additional_info' => $item->additionalInfo ? json_encode($item->additionalInfo) : null,
            'item_attributes' => json_encode([
                'original_title' => $item->itemTitle,
                'original_category' => $item->categoryName,
                'channel_title' => $item->channelTitle,
                'channel_sku' => $item->channelSku,
            ]),
            // Metadata
            'added_date' => $item->addedDate ? self::safeToDateTimeString(\Carbon\Carbon::parse($item->addedDate)) : null,
            'created_at' => now()->toDateTimeString(),
            'updated_at' => now()->toDateTimeString(),
        ])->toArray();

        // Shipping info (single record for DB::insert)
        $shippingData = $linnworks->shippingInfo ? [
            'order_id' => null, // Will be set after order is inserted
            'tracking_number' => $linnworks->shippingInfo['tracking_number'] ?? null,
            'vendor' => $linnworks->shippingInfo['vendor'] ?? null,
            'postal_service_id' => $linnworks->shippingInfo['postal_service_id'] ?? null,
            'postal_service_name' => $linnworks->shippingInfo['postal_service_name'] ?? null,
            'total_weight' => $linnworks->shippingInfo['total_weight'] ?? null,
            'item_weight' => $linnworks->shippingInfo['item_weight'] ?? null,
            'package_category' => $linnworks->shippingInfo['package_category'] ?? null,
            'package_type' => $linnworks->shippingInfo['package_type'] ?? null,
            'postage_cost' => $linnworks->shippingInfo['postage_cost'] ?? null,
            'postage_cost_ex_tax' => $linnworks->shippingInfo['postage_cost_ex_tax'] ?? null,
            'label_printed' => $linnworks->shippingInfo['label_printed'] ?? false,
            'label_error' => $linnworks->shippingInfo['label_error'] ?? null,
            'invoice_printed' => $linnworks->shippingInfo['invoice_printed'] ?? false,
            'pick_list_printed' => $linnworks->shippingInfo['pick_list_printed'] ?? false,
            'partial_shipped' => $linnworks->shippingInfo['partial_shipped'] ?? false,
            'manual_adjust' => $linnworks->shippingInfo['manual_adjust'] ?? false,
            'created_at' => now()->toDateTimeString(),
            'updated_at' => now()->toDateTimeString(),
        ] : null;

        // Notes (flat array for DB::insert)
        $notesData = $linnworks->notes->map(fn ($note) => [
            'order_id' => null, // Will be set after order is inserted
            'linnworks_note_id' => $note['NoteId'] ?? $note['note_id'] ?? null,
            'note_date' => isset($note['NoteDate']) || isset($note['note_date'])
                ? self::safeToDateTimeString(Carbon::parse($note['NoteDate'] ?? $note['note_date']))
                : null,
            'is_internal' => (bool) ($note['IsInternal'] ?? $note['is_internal'] ?? false),
            'note_text' => $note['Note'] ?? $note['note_text'] ?? '',
            'created_by' => $note['CreatedBy'] ?? $note['created_by'] ?? null,
            'created_at' => now()->toDateTimeString(),
            'updated_at' => now()->toDateTimeString(),
        ])->toArray();

        // Properties (flat array for DB::insert)
        $propertiesData = $linnworks->extendedProperties->map(fn ($property) => [
            'order_id' => null, // Will be set after order is inserted
            'property_type' => $property['PropertyType'] ?? $property['property_type'] ?? '',
            'property_name' => $property['PropertyName'] ?? $property['property_name'] ?? '',
            'property_value' => $property['PropertyValue'] ?? $property['property_value'] ?? '',
            'created_at' => now()->toDateTimeString(),
            'updated_at' => now()->toDateTimeString(),
        ])->toArray();

        // Identifiers (flat array for DB::insert)
        $identifiersData = $linnworks->identifiers->map(fn ($identifier) => [
            'order_id' => null, // Will be set after order is inserted
            'identifier_id' => $identifier['OrderIdentifierId'] ?? $identifier['identifier_id'] ?? 0,
            'tag' => $identifier['Tag'] ?? $identifier['tag'] ?? '',
            'name' => $identifier['TagDisplayText'] ?? $identifier['name'] ?? null,
            'is_custom' => (bool) ($identifier['IsCustom'] ?? $identifier['is_custom'] ?? false),
            'created_at' => now()->toDateTimeString(),
            'updated_at' => now()->toDateTimeString(),
        ])->toArray();

        return new self(
            order: $orderData,
            items: $itemsData,
            shipping: $shippingData,
            notes: $notesData,
            properties: $propertiesData,
            identifiers: $identifiersData,
            linnworksOrderId: $linnworks->orderId,
            orderNumber: $linnworks->orderNumber,
        );
    }

    /**
     * Normalize channel name to snake_case lowercase
     */
    private static function normalizeChannelName(?string $channel): ?string
    {
        if (! $channel) {
            return null;
        }

        return Str::lower(str_replace(' ', '_', $channel));
    }

    /**
     * Map Linnworks order status to our status string
     */
    private static function mapOrderStatus(int $status, bool $isProcessed): string
    {
        if ($isProcessed) {
            return 'processed';
        }

        return match ($status) {
            0 => 'pending',
            1 => 'processed',
            2 => 'cancelled',
            default => 'pending'
        };
    }
}
