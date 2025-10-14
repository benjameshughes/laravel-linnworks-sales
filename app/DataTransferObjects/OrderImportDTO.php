<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

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
            'received_date' => $linnworks->receivedDate?->toDateTimeString(),
            'processed_date' => $linnworks->processedDate?->toDateTimeString(),
            'currency' => $linnworks->currency,
            'total_charge' => $linnworks->totalCharge,
            'total_paid' => $linnworks->totalCharge, // Assume total charge = total paid
            'postage_cost' => $linnworks->postageCost,
            'tax' => $linnworks->tax,
            'profit_margin' => $linnworks->profitMargin,
            'status' => self::mapOrderStatus($linnworks->orderStatus, $isProcessed),
            'order_source' => $linnworks->orderSource,
            'subsource' => $linnworks->subsource,
            'order_status' => $linnworks->orderStatus,
            'location_id' => $linnworks->locationId,
            'is_open' => ! $isProcessed,
            'is_paid' => $linnworks->isPaid,
            'paid_date' => $linnworks->paidDate?->toDateTimeString(),
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
            // Extended order fields
            'marker' => $linnworks->marker,
            'is_parked' => $linnworks->isParked,
            'despatch_by_date' => $linnworks->despatchByDate?->toDateTimeString(),
            'dispatched_at' => null, // Set when order is actually dispatched
            'num_items' => $linnworks->numItems,
            'payment_method' => $linnworks->paymentMethod,
            'created_at' => now()->toDateTimeString(),
            'updated_at' => now()->toDateTimeString(),
        ];

        // Order items (flat array for DB::insert)
        $itemsData = $linnworks->items->map(fn ($item) => [
            'order_id' => null, // Will be set by OrderBulkWriter
            'item_id' => $item->itemId,
            'linnworks_item_id' => $item->stockItemId ?? null,
            'sku' => $item->sku,
            'title' => $item->itemTitle,
            'description' => null,
            'category' => $item->categoryName,
            'quantity' => $item->quantity,
            'unit_price' => $item->pricePerUnit,
            'total_price' => $item->lineTotal,
            'cost_price' => $item->unitCost,
            'profit_margin' => null, // Calculated by SalesMetrics service
            'tax_rate' => 0.00,
            'discount_amount' => 0.00,
            'bin_rack' => null,
            'is_service' => false,
            'item_attributes' => json_encode([
                'original_title' => $item->itemTitle,
                'original_category' => $item->categoryName,
            ]),
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
                ? \Carbon\Carbon::parse($note['NoteDate'] ?? $note['note_date'])->toDateTimeString()
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
