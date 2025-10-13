<?php

namespace App\Models;

use App\DataTransferObjects\LinnworksOrder;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;

class Order extends Model
{
    use HasFactory;

    /**
     * Temporary storage for pending data during order creation
     */
    public ?Collection $pendingItems = null;

    public ?array $pendingShipping = null;

    public ?Collection $pendingNotes = null;

    public ?Collection $pendingProperties = null;

    public ?Collection $pendingIdentifiers = null;

    protected $fillable = [
        'linnworks_order_id',
        'order_id',
        'order_number',
        'channel_name',
        'channel_reference_number',
        'source',
        'sub_source',
        'external_reference',
        'total_charge',
        'total_discount',
        'postage_cost',
        'total_paid',
        'profit_margin',
        'currency',
        'status',
        'addresses',
        'received_date',
        'processed_date',
        'dispatched_date',
        'is_resend',
        'is_exchange',
        'notes',
        'raw_data',
        'items', // Store as JSON array
        'order_source',
        'subsource',
        'tax',
        'order_status',
        'location_id',
        'last_synced_at',
        'is_paid',
        'paid_date',
        'is_open',
        'is_processed',
        'has_refund',
        'is_cancelled',
        'status_reason',
        'cancelled_at',
        'dispatched_at',
        'sync_status',
        'sync_metadata',
        // Extended order fields
        'marker',
        'is_parked',
        'despatch_by_date',
        'num_items',
        'payment_method',
    ];

    protected function casts(): array
    {
        return [
            'total_charge' => 'decimal:2',
            'total_discount' => 'decimal:2',
            'postage_cost' => 'decimal:2',
            'total_paid' => 'decimal:2',
            'profit_margin' => 'decimal:2',
            'tax' => 'decimal:2',
            'addresses' => 'array',
            'received_date' => 'datetime',
            'processed_date' => 'datetime',
            'dispatched_date' => 'datetime',
            'cancelled_at' => 'datetime',
            'dispatched_at' => 'datetime',
            'is_resend' => 'boolean',
            'is_exchange' => 'boolean',
            'is_cancelled' => 'boolean',
            'raw_data' => 'array',
            'items' => 'array',
            'last_synced_at' => 'datetime',
            'is_open' => 'boolean',
            'is_processed' => 'boolean',
            'has_refund' => 'boolean',
            'sync_metadata' => 'array',
            // Extended order field casts
            'is_parked' => 'boolean',
            'despatch_by_date' => 'datetime',
            'paid_date' => 'datetime',
        ];
    }

    /**
     * Get the order items for this order.
     */
    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Get the shipping information for this order.
     */
    public function shipping(): HasOne
    {
        return $this->hasOne(OrderShipping::class);
    }

    /**
     * Get the notes for this order.
     */
    public function notes(): HasMany
    {
        return $this->hasMany(OrderNote::class);
    }

    /**
     * Get the extended properties for this order.
     */
    public function properties(): HasMany
    {
        return $this->hasMany(OrderProperty::class);
    }

    /**
     * Get the identifiers/tags for this order.
     */
    public function identifiers(): HasMany
    {
        return $this->hasMany(OrderIdentifier::class);
    }

    /**
     * Mark this order as processed (transition from open to processed)
     * This only updates our local database, never touches Linnworks
     */
    public function markAsProcessed(?Carbon $processedDate = null): bool
    {
        return $this->update([
            'is_open' => false,
            'is_processed' => true,
            'status' => 'processed',
            'processed_date' => $processedDate ?? now(),
            'sync_status' => 'synced',
            'last_synced_at' => now(),
        ]);
    }

    /**
     * Check if this order can be found in processed orders by matching identifiers
     */
    public function matchesProcessedOrder(array $processedOrderData): bool
    {
        // Match by order_id (from processed orders API) or linnworks_order_id
        return $this->order_id === $processedOrderData['order_id'] ||
               $this->linnworks_order_id === $processedOrderData['order_id'] ||
               $this->order_number == $processedOrderData['order_number'];
    }

    /**
     * Get items as a Collection (modern accessor)
     */
    protected function itemsCollection(): Attribute
    {
        return Attribute::make(
            get: fn () => collect($this->items ?? [])
        );
    }

    /**
     * Get channel name with fallback (modern accessor)
     */
    protected function channelDisplay(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->channel_name ?? 'Unknown'
        );
    }

    /**
     * Get total items quantity (modern accessor)
     */
    protected function totalItems(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->items_collection->sum('quantity')
        );
    }

    /**
     * Get net profit (modern accessor)
     */
    protected function netProfit(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->total_charge - $this->items_collection->sum(fn ($item) => ($item['unit_cost'] ?? 0) * ($item['quantity'] ?? 0)
            )
        );
    }

    /**
     * Get profit margin percentage (modern accessor)
     */
    protected function profitMarginPercentage(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->total_charge == 0 ? 0 : ($this->net_profit / $this->total_charge) * 100
        );
    }

    /**
     * Get formatted total charge (modern accessor)
     */
    protected function formattedTotal(): Attribute
    {
        return Attribute::make(
            get: fn () => '£'.number_format($this->total_charge, 2)
        );
    }

    /**
     * Get order age in days (modern accessor)
     */
    protected function ageInDays(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->received_date ? $this->received_date->diffInDays(now()) : 0
        );
    }

    /**
     * Check if order is recent (modern accessor)
     */
    protected function isRecent(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->age_in_days <= 7
        );
    }

    /**
     * Get order status badge color (modern accessor)
     */
    protected function statusColor(): Attribute
    {
        return Attribute::make(
            get: fn () => match ($this->status) {
                'processed' => 'green',
                'pending' => 'yellow',
                'cancelled' => 'red',
                default => 'gray'
            }
        );
    }

    /**
     * Check if order is profitable (modern accessor)
     */
    protected function isProfitable(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->net_profit > 0
        );
    }

    /**
     * Modern query scopes
     */
    public function scopeByChannel(Builder $query, string $channelName): Builder
    {
        return $query->where('channel_name', $channelName);
    }

    public function scopeByStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    public function scopeByDateRange(Builder $query, Carbon|string $startDate, Carbon|string $endDate): Builder
    {
        return $query->whereBetween('received_date', [$startDate, $endDate]);
    }

    public function scopeProcessed(Builder $query): Builder
    {
        return $query->where('status', 'processed');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('is_open', true)->where('has_refund', false);
    }

    public function scopeNotRefunded(Builder $query): Builder
    {
        return $query->where('has_refund', false);
    }

    public function scopeNeedingSync(Builder $query): Builder
    {
        return $query->where('is_open', true)
            ->where(function (Builder $q) {
                $q->whereNull('last_synced_at')
                    ->orWhere('last_synced_at', '<', now()->subMinutes(15));
            });
    }

    public function scopeRecent(Builder $query, int $days = 7): Builder
    {
        return $query->where('received_date', '>=', now()->subDays($days));
    }

    public function scopeProfitable(Builder $query): Builder
    {
        return $query->whereRaw('total_charge > (SELECT SUM(unit_cost * quantity) FROM order_items WHERE order_id = orders.id)');
    }

    public function scopeHighValue(Builder $query, float $threshold = 100): Builder
    {
        return $query->where('total_charge', '>=', $threshold);
    }

    public function isProcessed(): bool
    {
        return $this->status === 'processed';
    }

    public function markAsSynced(): void
    {
        $this->update([
            'last_synced_at' => now(),
            'sync_status' => 'synced',
        ]);
    }

    public static function fromLinnworksOrder(LinnworksOrder $linnworksOrder): self
    {
        $status = self::mapOrderStatus($linnworksOrder->orderStatus);
        $isProcessed = $linnworksOrder->isProcessed();

        if ($isProcessed && $status !== 'cancelled') {
            $status = 'processed';
        }

        $order = new self([
            'linnworks_order_id' => $linnworksOrder->orderId,
            'order_id' => $linnworksOrder->orderId,
            'order_number' => $linnworksOrder->orderNumber,
            'channel_name' => $linnworksOrder->orderSource
                ? \Illuminate\Support\Str::lower(str_replace(' ', '_', $linnworksOrder->orderSource))
                : null,
            'channel_reference_number' => $linnworksOrder->channelReferenceNumber,
            'source' => $linnworksOrder->orderSource,
            'sub_source' => $linnworksOrder->subsource
                ? \Illuminate\Support\Str::lower(str_replace(' ', '_', $linnworksOrder->subsource))
                : null,
            'received_date' => $linnworksOrder->receivedDate,
            'processed_date' => $linnworksOrder->processedDate,
            'currency' => $linnworksOrder->currency,
            'total_charge' => $linnworksOrder->totalCharge,
            'total_paid' => $linnworksOrder->totalCharge, // Assume total charge is total paid
            'postage_cost' => $linnworksOrder->postageCost,
            'tax' => $linnworksOrder->tax,
            'profit_margin' => $linnworksOrder->profitMargin,
            'status' => $status,
            'order_source' => $linnworksOrder->orderSource,
            'subsource' => $linnworksOrder->subsource,
            'order_status' => $linnworksOrder->orderStatus,
            'location_id' => $linnworksOrder->locationId,
            'is_open' => ! $isProcessed,
            'is_paid' => $linnworksOrder->isPaid,
            'paid_date' => $linnworksOrder->paidDate,
            'is_cancelled' => $linnworksOrder->isCancelled,
            'is_processed' => $isProcessed,
            'last_synced_at' => now(),
            'sync_status' => 'synced',
            'raw_data' => [
                'linnworks_order_id' => $linnworksOrder->orderId,
                'order_number' => $linnworksOrder->orderNumber,
                'order_status' => $linnworksOrder->orderStatus,
                'location_id' => $linnworksOrder->locationId,
            ],
            'items' => $linnworksOrder->items?->map(fn ($item) => [
                'item_id' => $item->itemId,
                'sku' => $item->sku,
                'quantity' => $item->quantity,
                'unit_cost' => $item->unitCost,
                'price_per_unit' => $item->pricePerUnit,
                'line_total' => $item->lineTotal,
                'item_title' => $item->itemTitle,
                'category_name' => $item->categoryName,
            ])->toArray(),
            // Extended order fields
            'marker' => $linnworksOrder->marker,
            'is_parked' => $linnworksOrder->isParked,
            'despatch_by_date' => $linnworksOrder->despatchByDate,
            'num_items' => $linnworksOrder->numItems,
            'payment_method' => $linnworksOrder->paymentMethod,
        ]);

        // Store all pending data for later processing
        $order->setPendingItems($linnworksOrder->items);
        $order->setPendingShipping($linnworksOrder->shippingInfo);
        $order->setPendingNotes($linnworksOrder->notes);
        $order->setPendingProperties($linnworksOrder->extendedProperties);
        $order->setPendingIdentifiers($linnworksOrder->identifiers);

        return $order;
    }

    /**
     * Sync order items from JSON to OrderItem table
     */
    public function syncOrderItems(): void
    {
        // Clear existing order items
        $this->orderItems()->delete();

        $items = $this->pendingItems ?? collect($this->items ?? []);
        $unlinkedItems = [];

        if ($items instanceof \Illuminate\Support\Collection) {
            foreach ($items as $item) {
                // Handle both DTO objects and arrays
                $isObject = is_object($item);

                $sku = $isObject ? $item->sku : ($item['sku'] ?? null);
                $itemId = $isObject ? $item->itemId : ($item['item_id'] ?? null);
                $quantity = $isObject ? $item->quantity : ($item['quantity'] ?? 0);
                $pricePerUnit = $isObject ? $item->pricePerUnit : ($item['price_per_unit'] ?? 0);
                $itemTitle = $isObject ? $item->itemTitle : ($item['item_title'] ?? null);

                // Skip items with empty SKU - store in metadata for later action
                if (empty($sku)) {
                    $unlinkedItems[] = [
                        'item_id' => $itemId,
                        'item_title' => $itemTitle,
                        'quantity' => $quantity,
                        'price_per_unit' => $pricePerUnit,
                        'line_total' => $isObject ? $item->lineTotal : ($item['line_total'] ?? 0),
                        'reason' => 'No SKU from marketplace (unlinked item)',
                        'detected_at' => now()->toISOString(),
                    ];

                    \Log::warning('Unlinked order item detected (no SKU)', [
                        'order_id' => $this->linnworks_order_id,
                        'order_number' => $this->order_number,
                        'channel' => $this->channel_name,
                        'item_id' => $itemId,
                        'price' => $pricePerUnit,
                    ]);

                    continue;
                }

                // Ensure product exists - create placeholder if needed
                Product::firstOrCreate(
                    ['sku' => $sku],
                    [
                        'linnworks_id' => $isObject ? ($item->stockItemId ?? 'UNKNOWN') : ($item['stock_item_id'] ?? 'UNKNOWN'),
                        'title' => $itemTitle ?? 'Unknown Product',
                        'category_name' => $isObject ? $item->categoryName : ($item['category_name'] ?? null),
                        'stock_level' => 0,
                        'is_active' => true,
                    ]
                );

                $this->orderItems()->create([
                    'item_id' => $itemId,
                    'sku' => $sku,
                    'quantity' => $quantity,
                    'unit_cost' => $isObject ? $item->unitCost : ($item['unit_cost'] ?? 0),
                    'price_per_unit' => $pricePerUnit,
                    'line_total' => $isObject ? $item->lineTotal : ($item['line_total'] ?? 0),
                    'metadata' => array_filter([
                        'item_title' => $itemTitle,
                        'category_name' => $isObject ? $item->categoryName : ($item['category_name'] ?? null),
                    ]),
                ]);
            }
        } elseif (is_array($items)) {
            foreach ($items as $item) {
                $sku = $item['sku'] ?? null;
                $itemId = $item['item_id'] ?? null;
                $quantity = $item['quantity'] ?? 0;
                $pricePerUnit = $item['price_per_unit'] ?? 0;
                $itemTitle = $item['item_title'] ?? null;

                // Skip items with empty SKU - store in metadata for later action
                if (empty($sku)) {
                    $unlinkedItems[] = [
                        'item_id' => $itemId,
                        'item_title' => $itemTitle,
                        'quantity' => $quantity,
                        'price_per_unit' => $pricePerUnit,
                        'line_total' => $item['line_total'] ?? 0,
                        'reason' => 'No SKU from marketplace (unlinked item)',
                        'detected_at' => now()->toISOString(),
                    ];

                    \Log::warning('Unlinked order item detected (no SKU)', [
                        'order_id' => $this->linnworks_order_id,
                        'order_number' => $this->order_number,
                        'channel' => $this->channel_name,
                        'item_id' => $itemId,
                        'price' => $pricePerUnit,
                    ]);

                    continue;
                }

                // Ensure product exists - create placeholder if needed
                Product::firstOrCreate(
                    ['sku' => $sku],
                    [
                        'linnworks_id' => $item['stock_item_id'] ?? 'UNKNOWN',
                        'title' => $itemTitle ?? 'Unknown Product',
                        'category_name' => $item['category_name'] ?? null,
                        'stock_level' => 0,
                        'is_active' => true,
                    ]
                );

                $this->orderItems()->create([
                    'item_id' => $itemId,
                    'sku' => $sku,
                    'quantity' => $quantity,
                    'unit_cost' => $item['unit_cost'] ?? 0,
                    'price_per_unit' => $pricePerUnit,
                    'line_total' => $item['line_total'] ?? 0,
                    'metadata' => array_filter([
                        'item_title' => $itemTitle,
                        'category_name' => $item['category_name'] ?? null,
                    ]),
                ]);
            }
        }

        // Store unlinked items in order metadata if any were found
        if (! empty($unlinkedItems)) {
            $currentMetadata = $this->sync_metadata ?? [];
            $currentMetadata['unlinked_items'] = $unlinkedItems;
            $this->update(['sync_metadata' => $currentMetadata]);
        }

        // Clear pending items
        $this->pendingItems = null;
    }

    /**
     * Sync shipping information
     */
    public function syncShipping(): void
    {
        if (! $this->pendingShipping) {
            return;
        }

        // Delete existing shipping info
        $this->shipping()->delete();

        // Create new shipping record
        $this->shipping()->create($this->pendingShipping);

        // Clear pending shipping
        $this->pendingShipping = null;
    }

    /**
     * Sync order notes (strips customer PII)
     */
    public function syncNotes(): void
    {
        if (! $this->pendingNotes || $this->pendingNotes->isEmpty()) {
            return;
        }

        // Delete existing notes
        $this->notes()->delete();

        // Create new notes (strip any customer PII)
        foreach ($this->pendingNotes as $note) {
            $this->notes()->create([
                'linnworks_note_id' => $note['NoteId'] ?? $note['note_id'] ?? null,
                'note_date' => $note['NoteDate'] ?? $note['note_date'] ?? null,
                'is_internal' => (bool) ($note['IsInternal'] ?? $note['is_internal'] ?? false),
                'note_text' => $note['Note'] ?? $note['note_text'] ?? '',
                'created_by' => $note['CreatedBy'] ?? $note['created_by'] ?? null,
            ]);
        }

        // Clear pending notes
        $this->pendingNotes = null;
    }

    /**
     * Sync extended properties
     */
    public function syncProperties(): void
    {
        if (! $this->pendingProperties || $this->pendingProperties->isEmpty()) {
            return;
        }

        // Delete existing properties
        $this->properties()->delete();

        // Create new properties
        foreach ($this->pendingProperties as $property) {
            $this->properties()->create([
                'property_type' => $property['PropertyType'] ?? $property['property_type'] ?? '',
                'property_name' => $property['PropertyName'] ?? $property['property_name'] ?? '',
                'property_value' => $property['PropertyValue'] ?? $property['property_value'] ?? '',
            ]);
        }

        // Clear pending properties
        $this->pendingProperties = null;
    }

    /**
     * Sync order identifiers/tags
     */
    public function syncIdentifiers(): void
    {
        if (! $this->pendingIdentifiers || $this->pendingIdentifiers->isEmpty()) {
            return;
        }

        // Delete existing identifiers
        $this->identifiers()->delete();

        // Create new identifiers
        foreach ($this->pendingIdentifiers as $identifier) {
            $this->identifiers()->create([
                'identifier_id' => $identifier['OrderIdentifierId'] ?? $identifier['identifier_id'] ?? 0,
                'tag' => $identifier['Tag'] ?? $identifier['tag'] ?? '',
                'name' => $identifier['TagDisplayText'] ?? $identifier['name'] ?? null,
                'is_custom' => (bool) ($identifier['IsCustom'] ?? $identifier['is_custom'] ?? false),
            ]);
        }

        // Clear pending identifiers
        $this->pendingIdentifiers = null;
    }

    /**
     * Sync all related data (items, shipping, notes, properties, identifiers)
     */
    public function syncAllRelatedData(): void
    {
        $this->syncOrderItems();
        $this->syncShipping();
        $this->syncNotes();
        $this->syncProperties();
        $this->syncIdentifiers();
    }

    /**
     * Set pending items for processing
     */
    public function setPendingItems(?Collection $items): void
    {
        $this->pendingItems = $items;
    }

    /**
     * Get pending items
     */
    public function getPendingItems(): ?Collection
    {
        return $this->pendingItems;
    }

    /**
     * Set pending shipping info
     */
    public function setPendingShipping(?array $shippingInfo): void
    {
        $this->pendingShipping = $shippingInfo;
    }

    /**
     * Set pending notes
     */
    public function setPendingNotes(?Collection $notes): void
    {
        $this->pendingNotes = $notes;
    }

    /**
     * Set pending properties
     */
    public function setPendingProperties(?Collection $properties): void
    {
        $this->pendingProperties = $properties;
    }

    /**
     * Set pending identifiers
     */
    public function setPendingIdentifiers(?Collection $identifiers): void
    {
        $this->pendingIdentifiers = $identifiers;
    }

    private static function mapOrderStatus(int $status): string
    {
        return match ($status) {
            0 => 'pending',
            1 => 'processed',
            2 => 'cancelled',
            default => 'pending'
        };
    }
}
