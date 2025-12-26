<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;

/**
 * @property-read Collection $items_collection
 * @property-read float $net_profit
 * @property-read int $age_in_days
 */
class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        // Linnworks identifiers
        'order_id',
        'number',

        // Order dates
        'received_at',
        'processed_at',
        'paid_at',
        'despatch_by_at',

        // Channel information
        'source',
        'subsource',

        // Financial information
        'currency',
        'total_charge',
        'postage_cost',
        'postage_cost_ex_tax',
        'tax',
        'profit_margin',
        'total_discount',
        'country_tax_rate',
        'conversion_rate',

        // Order status
        'status',
        'is_paid',
        'is_cancelled',

        // Location
        'location_id',

        // Payment information
        'payment_method',
        'payment_method_id',

        // Reference numbers
        'channel_reference_number',
        'secondary_reference',
        'external_reference_num',

        // Order flags
        'marker',
        'is_parked',
        'label_printed',
        'label_error',
        'invoice_printed',
        'pick_list_printed',
        'is_rule_run',
        'part_shipped',
        'has_scheduled_delivery',
        'pickwave_ids',
        'num_items',
    ];

    protected function casts(): array
    {
        return [
            // Dates
            'received_at' => 'datetime',
            'processed_at' => 'datetime',
            'paid_at' => 'datetime',
            'despatch_by_at' => 'datetime',

            // Financial
            'total_charge' => 'decimal:2',
            'postage_cost' => 'decimal:2',
            'postage_cost_ex_tax' => 'decimal:2',
            'tax' => 'decimal:2',
            'profit_margin' => 'decimal:2',
            'total_discount' => 'decimal:2',
            'country_tax_rate' => 'decimal:4',
            'conversion_rate' => 'decimal:6',

            // Booleans
            'is_paid' => 'boolean',
            'is_cancelled' => 'boolean',
            'is_parked' => 'boolean',
            'label_printed' => 'boolean',
            'invoice_printed' => 'boolean',
            'pick_list_printed' => 'boolean',
            'is_rule_run' => 'boolean',
            'part_shipped' => 'boolean',
            'has_scheduled_delivery' => 'boolean',

            // JSON
            'pickwave_ids' => 'array',
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
            'status' => 1,
            'processed_at' => $processedDate ?? now(),
        ]);
    }

    /**
     * Check if this order can be found in processed orders by matching identifiers
     */
    public function matchesProcessedOrder(array $processedOrderData): bool
    {
        return $this->order_id === $processedOrderData['order_id'] ||
               $this->number == $processedOrderData['number'];
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
            get: fn () => $this->source ?? 'Unknown'
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
     * Check if order is open/pending (modern accessor)
     */
    protected function isOpen(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->status === 0
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
            get: function (): int {
                if (! $this->received_at || ! $this->received_at instanceof Carbon) {
                    return 0;
                }

                return $this->received_at->diffInDays(now());
            }
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
        return $query->where('source', $channelName);
    }

    public function scopeByStatus(Builder $query, int $status): Builder
    {
        return $query->where('status', $status);
    }

    public function scopeByDateRange(Builder $query, Carbon|string $startDate, Carbon|string $endDate): Builder
    {
        return $query->whereBetween('received_at', [$startDate, $endDate]);
    }

    public function scopeProcessed(Builder $query): Builder
    {
        return $query->where('status', 1);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 0);
    }

    public function scopeCancelled(Builder $query): Builder
    {
        return $query->where('status', 2);
    }

    public function scopeRecent(Builder $query, int $days = 7): Builder
    {
        return $query->where('received_at', '>=', now()->subDays($days));
    }

    public function scopeProfitable(Builder $query): Builder
    {
        return $query->whereRaw('total_charge > (SELECT SUM(cost * quantity) FROM order_items WHERE order_items.order_id = orders.id)');
    }

    public function scopeHighValue(Builder $query, float $threshold = 100): Builder
    {
        return $query->where('total_charge', '>=', $threshold);
    }

    public function isProcessed(): bool
    {
        return $this->status === 1;
    }
}
