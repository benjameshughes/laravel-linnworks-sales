<?php

namespace App\DataTransferObjects;

use Carbon\Carbon;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Collection;

readonly class LinnworksOrder implements Arrayable
{
    public function __construct(
        public ?string $orderId,
        public ?int $number,
        public ?Carbon $receivedDate,
        public ?Carbon $processedDate,
        public ?string $source,
        public ?string $subsource,
        public string $currency,
        public float $totalCharge,
        public float $postageCost,
        public float $postageCostExTax,
        public float $tax,
        public float $profitMargin,
        public float $totalDiscount,
        public int $status,
        public ?string $locationId,
        public bool $isPaid,
        public ?Carbon $paidDate,
        public bool $isCancelled,
        public ?string $channelReferenceNumber,
        public ?string $secondaryReference,
        public ?string $externalReferenceNum,
        public Collection $items,
        // Extended order fields - GeneralInfo
        public int $marker,
        public bool $isParked,
        public bool $labelPrinted,
        public ?string $labelError,
        public bool $invoicePrinted,
        public bool $pickListPrinted,
        public bool $isRuleRun,
        public bool $partShipped,
        public bool $hasScheduledDelivery,
        public ?array $pickwaveIds,
        public ?Carbon $despatchByDate,
        public ?int $numItems,
        // Payment
        public ?string $paymentMethod,
        public ?string $paymentMethodId,
        // Tax & Currency - TotalsInfo
        public ?float $countryTaxRate,
        public float $conversionRate,
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
            number: isset($data['NumOrderId']) ? (int) $data['NumOrderId'] : (
                isset($data['ReferenceNum']) ? (int) $data['ReferenceNum'] : (      // ProcessedOrders endpoint
                    isset($data['nOrderId']) ? (int) $data['nOrderId'] : (
                        isset($data['order_number']) ? (int) $data['order_number'] : null
                    )
                )
            ),
            receivedDate: self::parseDate($generalInfo['ReceivedDate'] ?? $data['dReceivedDate'] ?? $data['received_date'] ?? null),
            processedDate: self::determineProcessedDate($data, $generalInfo),
            source: $generalInfo['Source'] ?? $data['Source'] ?? $data['order_source'] ?? null,
            subsource: $generalInfo['SubSource'] ?? $data['SubSource'] ?? $data['subsource'] ?? null,
            currency: $totalsInfo['Currency'] ?? $data['cCurrency'] ?? $data['currency'] ?? 'GBP',
            totalCharge: (float) ($totalsInfo['TotalCharge'] ?? $data['fTotalCharge'] ?? $data['total_charge'] ?? 0),
            postageCost: (float) ($totalsInfo['PostageCost'] ?? $data['fPostageCost'] ?? $data['postage_cost'] ?? 0),
            postageCostExTax: (float) ($totalsInfo['PostageCostExTax'] ?? $data['postage_cost_ex_tax'] ?? 0),
            tax: (float) ($totalsInfo['Tax'] ?? $data['fTax'] ?? $data['tax'] ?? 0),
            profitMargin: (float) ($totalsInfo['ProfitMargin'] ?? $data['ProfitMargin'] ?? $data['profit_margin'] ?? 0),
            totalDiscount: (float) ($totalsInfo['TotalDiscount'] ?? $data['total_discount'] ?? 0),
            status: (int) ($generalInfo['Status'] ?? $data['nStatus'] ?? $data['order_status'] ?? 0),
            locationId: $data['FulfilmentLocationId'] ?? $data['fkOrderLocationID'] ?? $data['location_id'] ?? null,
            // Check PaidDateTime first (source of truth), then fall back to nStatus === 1
            isPaid: $paidDate !== null || ($generalInfo['Status'] ?? $data['nStatus'] ?? $data['order_status'] ?? 0) === 1,
            paidDate: $paidDate,
            isCancelled: (bool) ($generalInfo['HoldOrCancel'] ?? $data['HoldOrCancel'] ?? $data['is_cancelled'] ?? false),
            channelReferenceNumber: $generalInfo['ReferenceNum'] ?? $data['channel_reference_number'] ?? null,
            secondaryReference: $generalInfo['SecondaryReference'] ?? $data['secondary_reference'] ?? null,
            externalReferenceNum: $generalInfo['ExternalReferenceNum'] ?? $data['external_reference_num'] ?? null,
            items: $items,
            // Extended order fields - GeneralInfo
            marker: (int) ($generalInfo['Marker'] ?? $data['Marker'] ?? 0),
            isParked: (bool) ($generalInfo['IsParked'] ?? $data['IsParked'] ?? false),
            labelPrinted: (bool) ($generalInfo['LabelPrinted'] ?? $data['label_printed'] ?? false),
            labelError: $generalInfo['LabelError'] ?? $data['label_error'] ?? null,
            invoicePrinted: (bool) ($generalInfo['InvoicePrinted'] ?? $data['invoice_printed'] ?? false),
            pickListPrinted: (bool) ($generalInfo['PickListPrinted'] ?? $data['pick_list_printed'] ?? false),
            isRuleRun: (bool) ($generalInfo['IsRuleRun'] ?? $data['is_rule_run'] ?? false),
            partShipped: (bool) ($generalInfo['PartShipped'] ?? $data['part_shipped'] ?? false),
            hasScheduledDelivery: (bool) ($generalInfo['HasScheduledDelivery'] ?? $data['has_scheduled_delivery'] ?? false),
            pickwaveIds: $generalInfo['PickwaveIds'] ?? $data['pickwave_ids'] ?? null,
            despatchByDate: self::parseDate($generalInfo['DespatchByDate'] ?? $data['DespatchByDate'] ?? null),
            numItems: isset($items) ? $items->sum('quantity') : null,
            // Payment
            paymentMethod: $totalsInfo['PaymentMethod'] ?? $generalInfo['PaymentMethod'] ?? $data['PaymentMethod'] ?? null,
            paymentMethodId: $totalsInfo['PaymentMethodId'] ?? $data['payment_method_id'] ?? null,
            // Tax & Currency - TotalsInfo
            countryTaxRate: isset($totalsInfo['CountryTaxRate']) ? (float) $totalsInfo['CountryTaxRate'] : null,
            conversionRate: (float) ($totalsInfo['ConversionRate'] ?? $data['conversion_rate'] ?? 1),
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
            'id' => $this->orderId,
            'number' => $this->orderNumber,
            'received_date' => $this->receivedDate?->toISOString(),
            'processed_date' => $this->processedDate?->toISOString(),
            'source' => $this->orderSource,
            'subsource' => $this->subsource,
            'currency' => $this->currency,
            'total_charge' => $this->totalCharge,
            'postage_cost' => $this->postageCost,
            'tax' => $this->tax,
            'profit_margin' => $this->profitMargin,
            'status' => $this->orderStatus,
            'location_id' => $this->locationId,
            'is_paid' => $this->isPaid,
            'paid_date' => $this->paidDate?->toISOString(),
            'is_cancelled' => $this->isCancelled,
            'channel_reference_number' => $this->channelReferenceNumber,
            'items' => $this->items->toArray(),
        ];
    }

    /**
     * Convert to database-ready format for bulk insert/update
     */
    public function toDatabaseFormat(): array
    {
        return [
            // Linnworks identifiers
            'order_id' => $this->orderId,
            'number' => $this->number,

            // Order dates
            'received_at' => $this->receivedDate?->setTimezone(config('app.timezone'))->toDateTimeString(),
            'processed_at' => $this->processedDate?->setTimezone(config('app.timezone'))->toDateTimeString(),
            'paid_at' => $this->paidDate?->setTimezone(config('app.timezone'))->toDateTimeString(),
            'despatch_by_at' => $this->despatchByDate?->setTimezone(config('app.timezone'))->toDateTimeString(),

            // Channel information
            'source' => $this->source,
            'subsource' => $this->subsource,

            // Financial information
            'currency' => $this->currency,
            'total_charge' => $this->totalCharge,
            'postage_cost' => $this->postageCost,
            'postage_cost_ex_tax' => $this->postageCostExTax,
            'tax' => $this->tax,
            'profit_margin' => $this->profitMargin,
            'total_discount' => $this->totalDiscount,
            'country_tax_rate' => $this->countryTaxRate,
            'conversion_rate' => $this->conversionRate,

            // Order status
            'status' => $this->status,
            'is_paid' => $this->isPaid,
            'is_cancelled' => $this->isCancelled,

            // Location
            'location_id' => $this->locationId,

            // Payment information
            'payment_method' => $this->paymentMethod,
            'payment_method_id' => $this->paymentMethodId,

            // Reference numbers
            'channel_reference_number' => $this->channelReferenceNumber,
            'secondary_reference' => $this->secondaryReference,
            'external_reference_num' => $this->externalReferenceNum,

            // Order flags
            'marker' => $this->marker,
            'is_parked' => $this->isParked,
            'label_printed' => $this->labelPrinted,
            'label_error' => $this->labelError,
            'invoice_printed' => $this->invoicePrinted,
            'pick_list_printed' => $this->pickListPrinted,
            'is_rule_run' => $this->isRuleRun,
            'part_shipped' => $this->partShipped,
            'has_scheduled_delivery' => $this->hasScheduledDelivery,
            'pickwave_ids' => $this->pickwaveIds ? json_encode($this->pickwaveIds) : null,
            'num_items' => $this->numItems,

            // Laravel timestamps
            'created_at' => now()->toDateTimeString(),
            'updated_at' => now()->toDateTimeString(),
        ];
    }

    private static function parseDate(?string $date): ?Carbon
    {
        if (! $date) {
            return null;
        }

        try {
            $parsed = Carbon::parse($date);

            // Filter out Unix epoch dates
            if ($parsed->year <= 1970) {
                return null;
            }

            // Return as-is. Linnworks sends UTC, conversion to local timezone
            // happens later in OrderImportDTO when preparing for MySQL
            return $parsed;

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
