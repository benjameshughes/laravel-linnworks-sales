<?php

namespace App\DataTransferObjects;

use Carbon\Carbon;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Collection;

readonly class LinnworksOrder implements Arrayable
{
    public function __construct(
        public ?string $orderId,
        public ?int $orderNumber,
        public ?Carbon $receivedDate,
        public ?Carbon $processedDate,
        public ?string $orderSource,
        public ?string $subsource,
        public string $currency,
        public float $totalCharge,
        public float $postageCost,
        public float $tax,
        public float $profitMargin,
        public int $orderStatus,
        public ?string $locationId,
        public Collection $items,
    ) {}

    public static function fromArray(array $data): self
    {
        $items = collect($data['Items'] ?? $data['items'] ?? [])
            ->map(fn (array $item) => LinnworksOrderItem::fromArray($item));

        // Handle new nested structure from GetOrdersById
        $generalInfo = $data['GeneralInfo'] ?? [];
        $totalsInfo = $data['TotalsInfo'] ?? [];

        return new self(
            orderId: $data['OrderId'] ?? $data['pkOrderID'] ?? $data['order_id'] ?? null,
            orderNumber: $data['NumOrderId'] ?? (isset($data['nOrderId']) ? (int) $data['nOrderId'] : null) 
                ?? (isset($data['order_number']) ? (int) $data['order_number'] : null),
            receivedDate: self::parseDate($generalInfo['ReceivedDate'] ?? $data['dReceivedDate'] ?? $data['received_date'] ?? null),
            processedDate: ($data['Processed'] ?? false) === true ? self::parseDate($generalInfo['ReceivedDate'] ?? null) : null, // Use received date if processed
            orderSource: $generalInfo['Source'] ?? $data['Source'] ?? $data['order_source'] ?? null,
            subsource: $generalInfo['SubSource'] ?? $data['SubSource'] ?? $data['subsource'] ?? null,
            currency: $totalsInfo['Currency'] ?? $data['cCurrency'] ?? $data['currency'] ?? 'GBP',
            totalCharge: (float) ($totalsInfo['TotalCharge'] ?? $data['fTotalCharge'] ?? $data['total_charge'] ?? 0),
            postageCost: (float) ($totalsInfo['PostageCost'] ?? $data['fPostageCost'] ?? $data['postage_cost'] ?? 0),
            tax: (float) ($totalsInfo['Tax'] ?? $data['fTax'] ?? $data['tax'] ?? 0),
            profitMargin: (float) ($totalsInfo['ProfitMargin'] ?? $data['ProfitMargin'] ?? $data['profit_margin'] ?? 0),
            orderStatus: (int) ($generalInfo['Status'] ?? $data['nStatus'] ?? $data['order_status'] ?? 0),
            locationId: $data['FulfilmentLocationId'] ?? $data['fkOrderLocationID'] ?? $data['location_id'] ?? null,
            items: $items,
        );
    }

    public function toArray(): array
    {
        return [
            'order_id' => $this->orderId,
            'order_number' => $this->orderNumber,
            'received_date' => $this->receivedDate?->toISOString(),
            'processed_date' => $this->processedDate?->toISOString(),
            'order_source' => $this->orderSource,
            'subsource' => $this->subsource,
            'currency' => $this->currency,
            'total_charge' => $this->totalCharge,
            'postage_cost' => $this->postageCost,
            'tax' => $this->tax,
            'profit_margin' => $this->profitMargin,
            'order_status' => $this->orderStatus,
            'location_id' => $this->locationId,
            'items' => $this->items->toArray(),
        ];
    }

    private static function parseDate(?string $date): ?Carbon
    {
        if (!$date) {
            return null;
        }

        try {
            return Carbon::parse($date);
        } catch (\Exception) {
            return null;
        }
    }

    // Convenience methods for calculations
    public function itemsValue(): float
    {
        return $this->items->sum(fn (LinnworksOrderItem $item) => $item->totalValue());
    }

    public function totalProfit(): float
    {
        return $this->items->sum(fn (LinnworksOrderItem $item) => $item->profit());
    }

    public function itemCount(): int
    {
        return $this->items->sum('quantity');
    }

    public function netRevenue(): float
    {
        return $this->totalCharge - $this->postageCost - $this->tax;
    }

    public function isProcessed(): bool
    {
        return $this->processedDate !== null;
    }

    public function daysSinceReceived(): ?int
    {
        return $this->receivedDate?->diffInDays(now());
    }

    public function channel(): string
    {
        return match (true) {
            str_contains(strtolower($this->orderSource ?? ''), 'amazon') => 'Amazon',
            str_contains(strtolower($this->orderSource ?? ''), 'ebay') => 'eBay',
            str_contains(strtolower($this->orderSource ?? ''), 'web') => 'Website',
            default => $this->orderSource ?? 'Unknown'
        };
    }
}