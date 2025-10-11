<?php

namespace App\Models;

use App\DataTransferObjects\LinnworksOrder;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class Order extends Model
{
    use HasFactory;

    /**
     * Temporary storage for pending items during order creation
     */
    private ?Collection $pendingItems = null;

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
            get: fn () => $this->total_charge - $this->items_collection->sum(fn ($item) => 
                ($item['unit_cost'] ?? 0) * ($item['quantity'] ?? 0)
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
            get: fn () => '£' . number_format($this->total_charge, 2)
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
            'is_open' => !$isProcessed,
            'is_paid' => $linnworksOrder->isPaid,
            'paid_date' => $linnworksOrder->paidDate,
            'is_processed' => $isProcessed,
            'last_synced_at' => now(),
            'sync_status' => 'synced',
            'raw_data' => [
                'linnworks_order_id' => $linnworksOrder->orderId,
                'order_number' => $linnworksOrder->orderNumber,
                'order_status' => $linnworksOrder->orderStatus,
                'location_id' => $linnworksOrder->locationId,
            ],
            'items' => $linnworksOrder->items?->map(fn($item) => [
                'item_id' => $item->itemId,
                'sku' => $item->sku,
                'quantity' => $item->quantity,
                'unit_cost' => $item->unitCost,
                'price_per_unit' => $item->pricePerUnit,
                'line_total' => $item->lineTotal,
                'item_title' => $item->itemTitle,
                'category_name' => $item->categoryName,
            ])->toArray(),
        ]);

        // Store the items data for later processing in a protected property
        $order->setPendingItems($linnworksOrder->items);

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

        if ($items instanceof \Illuminate\Support\Collection) {
            foreach ($items as $item) {
                // Handle both DTO objects and arrays
                $isObject = is_object($item);

                $this->orderItems()->create([
                    'item_id' => $isObject ? $item->itemId : ($item['item_id'] ?? null),
                    'sku' => $isObject ? $item->sku : ($item['sku'] ?? null),
                    'quantity' => $isObject ? $item->quantity : ($item['quantity'] ?? 0),
                    'unit_cost' => $isObject ? $item->unitCost : ($item['unit_cost'] ?? 0),
                    'price_per_unit' => $isObject ? $item->pricePerUnit : ($item['price_per_unit'] ?? 0),
                    'line_total' => $isObject ? $item->lineTotal : ($item['line_total'] ?? 0),
                    'metadata' => array_filter([
                        'item_title' => $isObject ? $item->itemTitle : ($item['item_title'] ?? null),
                        'category_name' => $isObject ? $item->categoryName : ($item['category_name'] ?? null),
                    ]),
                ]);
            }
        } elseif (is_array($items)) {
            foreach ($items as $item) {
                $this->orderItems()->create([
                    'item_id' => $item['item_id'] ?? null,
                    'sku' => $item['sku'] ?? null,
                    'quantity' => $item['quantity'] ?? 0,
                    'unit_cost' => $item['unit_cost'] ?? 0,
                    'price_per_unit' => $item['price_per_unit'] ?? 0,
                    'line_total' => $item['line_total'] ?? 0,
                    'metadata' => array_filter([
                        'item_title' => $item['item_title'] ?? null,
                        'category_name' => $item['category_name'] ?? null,
                    ]),
                ]);
            }
        }

        // Clear pending items
        $this->pendingItems = null;
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
