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
        'linnworks_order_id',
        'order_id',
        'order_number',
        'channel_name',
        'channel_reference_number',
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
        'notes',
        'raw_data',
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
            'cancelled_at' => 'datetime',
            'dispatched_at' => 'datetime',
            'is_cancelled' => 'boolean',
            'is_paid' => 'boolean',
            'raw_data' => 'array',
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
            get: function (): int {
                /** @phpstan-ignore-next-line */
                if (! $this->received_date || ! $this->received_date instanceof Carbon) {
                    return 0;
                }

                /** @phpstan-ignore-next-line */
                return $this->received_date->diffInDays(now());
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
        return $query->whereRaw('total_charge > (SELECT SUM(cost_price * quantity) FROM order_items WHERE order_id = orders.id)');
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
}
