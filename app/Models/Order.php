<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'linnworks_order_id',
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
        'is_open',
        'has_refund',
        'sync_status',
        'sync_metadata',
    ];

    protected $casts = [
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
        'is_resend' => 'boolean',
        'is_exchange' => 'boolean',
        'raw_data' => 'array',
        'items' => 'array',
        'last_synced_at' => 'datetime',
        'is_open' => 'boolean',
        'has_refund' => 'boolean',
        'sync_metadata' => 'array',
    ];

    public function getItemsCollectionAttribute()
    {
        return collect($this->items ?? []);
    }

    public function getChannelAttribute(): string
    {
        return $this->channel_name ?? 'Unknown';
    }

    public function getTotalItemsAttribute(): int
    {
        return $this->items_collection->sum('quantity');
    }

    public function getNetProfitAttribute(): float
    {
        return $this->total_charge - $this->items_collection->sum(function ($item) {
            return ($item['unit_cost'] ?? 0) * ($item['quantity'] ?? 0);
        });
    }

    public function getProfitMarginPercentageAttribute(): float
    {
        if ($this->total_charge == 0) {
            return 0;
        }
        
        return ($this->net_profit / $this->total_charge) * 100;
    }

    public function scopeByChannel($query, string $channelName)
    {
        return $query->where('channel_name', $channelName);
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('received_date', [$startDate, $endDate]);
    }

    public function scopeProcessed($query)
    {
        return $query->where('status', 'processed');
    }

    public function scopeOpen($query)
    {
        return $query->where('is_open', true)->where('has_refund', false);
    }

    public function scopeNotRefunded($query)
    {
        return $query->where('has_refund', false);
    }

    public function scopeNeedingSync($query)
    {
        return $query->where('is_open', true)
            ->where(function ($q) {
                $q->whereNull('last_synced_at')
                  ->orWhere('last_synced_at', '<', now()->subMinutes(15));
            });
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

    public static function fromLinnworksOrder(\App\DataTransferObjects\LinnworksOrder $linnworksOrder): self
    {
        return new self([
            'linnworks_order_id' => $linnworksOrder->orderId,
            'order_number' => $linnworksOrder->orderNumber,
            'channel_name' => $linnworksOrder->orderSource, // Map to correct field
            'source' => $linnworksOrder->orderSource,
            'sub_source' => $linnworksOrder->subsource,
            'received_date' => $linnworksOrder->receivedDate,
            'processed_date' => $linnworksOrder->processedDate,
            'currency' => $linnworksOrder->currency,
            'total_charge' => $linnworksOrder->totalCharge,
            'total_paid' => $linnworksOrder->totalCharge, // Assume total charge is total paid
            'postage_cost' => $linnworksOrder->postageCost,
            'tax' => $linnworksOrder->tax,
            'profit_margin' => $linnworksOrder->profitMargin,
            'status' => self::mapOrderStatus($linnworksOrder->orderStatus),
            'order_source' => $linnworksOrder->orderSource,
            'subsource' => $linnworksOrder->subsource,
            'order_status' => $linnworksOrder->orderStatus,
            'location_id' => $linnworksOrder->locationId,
            'is_open' => true, // Always open if coming from open orders API
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
                'item_title' => $item->itemTitle,
                'quantity' => $item->quantity,
                'unit_cost' => $item->unitCost,
                'price_per_unit' => $item->pricePerUnit,
                'line_total' => $item->lineTotal,
                'category_name' => $item->categoryName,
            ])->toArray(),
        ]);
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
