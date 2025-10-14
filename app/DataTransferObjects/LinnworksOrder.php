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
        public bool $isPaid,
        public ?Carbon $paidDate,
        public bool $isCancelled,
        public ?string $channelReferenceNumber,
        public Collection $items,
        // Extended order fields
        public int $marker,
        public bool $isParked,
        public ?Carbon $despatchByDate,
        public ?int $numItems,
        public ?string $paymentMethod,
        // Shipping information (1-to-1)
        public ?array $shippingInfo,
        // Notes array (1-to-many)
        public Collection $notes,
        // Extended properties array (1-to-many)
        public Collection $extendedProperties,
        // Identifiers/tags array (1-to-many)
        public Collection $identifiers,
    ) {}

    public static function fromArray(array $data): self
    {
        $items = collect($data['Items'] ?? $data['items'] ?? [])
            ->map(fn (array $item) => LinnworksOrderItem::fromArray($item));

        // Handle new nested structure from GetOrdersById
        $generalInfo = $data['GeneralInfo'] ?? [];
        $totalsInfo = $data['TotalsInfo'] ?? [];
        $shippingInfo = $data['ShippingInfo'] ?? [];
        $notes = collect($data['Notes'] ?? []);
        $extendedProperties = collect($data['ExtendedProperties'] ?? []);
        $identifiers = collect($data['OrderIdentifiers'] ?? []);

        // Parse paid date first so we can use it to determine isPaid
        $paidDate = self::parseDate($data['PaidDateTime'] ?? $data['PaidDate'] ?? $data['dPaidDate'] ?? $data['paid_date'] ?? null);

        return new self(
            orderId: $data['OrderId'] ?? $data['pkOrderID'] ?? $data['order_id'] ?? null,
            orderNumber: isset($data['NumOrderId']) ? (int) $data['NumOrderId'] : (
                isset($data['ReferenceNum']) ? (int) $data['ReferenceNum'] : (      // ProcessedOrders endpoint
                    isset($data['nOrderId']) ? (int) $data['nOrderId'] : (
                        isset($data['order_number']) ? (int) $data['order_number'] : null
                    )
                )
            ),
            receivedDate: self::parseDate($generalInfo['ReceivedDate'] ?? $data['dReceivedDate'] ?? $data['received_date'] ?? null),
            processedDate: self::determineProcessedDate($data, $generalInfo),
            orderSource: $generalInfo['Source'] ?? $data['Source'] ?? $data['order_source'] ?? null,
            subsource: $generalInfo['SubSource'] ?? $data['SubSource'] ?? $data['subsource'] ?? null,
            currency: $totalsInfo['Currency'] ?? $data['cCurrency'] ?? $data['currency'] ?? 'GBP',
            totalCharge: (float) ($totalsInfo['TotalCharge'] ?? $data['fTotalCharge'] ?? $data['total_charge'] ?? 0),
            postageCost: (float) ($totalsInfo['PostageCost'] ?? $data['fPostageCost'] ?? $data['postage_cost'] ?? 0),
            tax: (float) ($totalsInfo['Tax'] ?? $data['fTax'] ?? $data['tax'] ?? 0),
            profitMargin: (float) ($totalsInfo['ProfitMargin'] ?? $data['ProfitMargin'] ?? $data['profit_margin'] ?? 0),
            orderStatus: (int) ($generalInfo['Status'] ?? $data['nStatus'] ?? $data['order_status'] ?? 0),
            locationId: $data['FulfilmentLocationId'] ?? $data['fkOrderLocationID'] ?? $data['location_id'] ?? null,
            // Check PaidDateTime first (source of truth), then fall back to nStatus === 1
            isPaid: $paidDate !== null || ($generalInfo['Status'] ?? $data['nStatus'] ?? $data['order_status'] ?? 0) === 1,
            paidDate: $paidDate,
            isCancelled: (bool) ($generalInfo['HoldOrCancel'] ?? $data['HoldOrCancel'] ?? $data['is_cancelled'] ?? false),
            channelReferenceNumber: $generalInfo['ReferenceNum'] ?? $generalInfo['ExternalReferenceNum'] ?? $data['channel_reference_number'] ?? null,
            items: $items,
            // Extended order fields
            marker: (int) ($generalInfo['Marker'] ?? $data['Marker'] ?? 0),
            isParked: (bool) ($generalInfo['IsParked'] ?? $data['IsParked'] ?? false),
            despatchByDate: self::parseDate($generalInfo['DespatchByDate'] ?? $data['DespatchByDate'] ?? null),
            numItems: isset($items) ? $items->sum('quantity') : null,
            paymentMethod: $generalInfo['PaymentMethod'] ?? $data['PaymentMethod'] ?? null,
            // Shipping information
            shippingInfo: ! empty($shippingInfo) ? [
                'tracking_number' => $shippingInfo['TrackingNumber'] ?? null,
                'vendor' => $shippingInfo['Vendor'] ?? null,
                'postal_service_id' => $shippingInfo['PostalServiceId'] ?? null,
                'postal_service_name' => $shippingInfo['PostalServiceName'] ?? null,
                'total_weight' => $shippingInfo['TotalWeight'] ?? null,
                'item_weight' => $shippingInfo['ItemWeight'] ?? null,
                'package_category' => $shippingInfo['PackageCategory'] ?? null,
                'package_type' => $shippingInfo['PackageType'] ?? null,
                'postage_cost' => $shippingInfo['PostageCost'] ?? null,
                'postage_cost_ex_tax' => $shippingInfo['PostageCostExTax'] ?? null,
                'label_printed' => (bool) ($shippingInfo['LabelPrinted'] ?? false),
                'label_error' => $shippingInfo['LabelError'] ?? null,
                'invoice_printed' => (bool) ($shippingInfo['InvoicePrinted'] ?? false),
                'pick_list_printed' => (bool) ($shippingInfo['PickListPrinted'] ?? false),
                'partial_shipped' => (bool) ($shippingInfo['PartialShipped'] ?? false),
                'manual_adjust' => (bool) ($shippingInfo['ManualAdjust'] ?? false),
            ] : null,
            // Notes (strip any customer PII)
            notes: $notes,
            // Extended properties
            extendedProperties: $extendedProperties,
            // Identifiers/tags
            identifiers: $identifiers,
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
            'is_paid' => $this->isPaid,
            'paid_date' => $this->paidDate?->toISOString(),
            'is_cancelled' => $this->isCancelled,
            'channel_reference_number' => $this->channelReferenceNumber,
            'items' => $this->items->toArray(),
        ];
    }

    private static function parseDate(?string $date): ?Carbon
    {
        if (! $date) {
            return null;
        }

        try {
            return Carbon::parse($date);
        } catch (\Exception) {
            return null;
        }
    }

    /**
     * Safely determine processed date from various API response structures
     */
    private static function determineProcessedDate(array $data, array $generalInfo): ?Carbon
    {
        // Try direct processed date fields first (from ProcessedOrders API)
        $processedDate = $data['dProcessedOn'] ??           // ProcessedOrders endpoint
                        $data['ProcessedDate'] ??
                        $data['dProcessedDate'] ??
                        $generalInfo['ProcessedDate'] ??
                        $generalInfo['dProcessedDate'] ??
                        null;

        // If we have an explicit processed date, use it
        if ($processedDate) {
            return self::parseDate($processedDate);
        }

        // Otherwise check if order is marked as processed
        $isProcessed = $data['Processed'] ??
                      $data['bProcessed'] ??
                      $generalInfo['Processed'] ??
                      $generalInfo['bProcessed'] ??
                      false;

        if (! $isProcessed) {
            return null;
        }

        // If processed but no date given, use received date as fallback
        $fallbackDate = $generalInfo['ReceivedDate'] ??
                       $data['dReceivedDate'] ??
                       $data['received_date'] ??
                       null;

        return self::parseDate($fallbackDate);
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
