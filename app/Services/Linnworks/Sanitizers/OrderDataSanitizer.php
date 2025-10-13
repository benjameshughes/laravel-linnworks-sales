<?php

declare(strict_types=1);

namespace App\Services\Linnworks\Sanitizers;

use Carbon\Carbon;

/**
 * Sanitizes order data from Linnworks API responses
 */
class OrderDataSanitizer
{
    /**
     * Sanitize complete order data
     */
    public function sanitize(array $orderData): array
    {
        // Handle different response structures
        $generalInfo = $orderData['GeneralInfo'] ?? [];
        $totalsInfo = $orderData['TotalsInfo'] ?? [];
        $items = $orderData['Items'] ?? $orderData['OrderItems'] ?? [];

        // If flat structure (from GetOpenOrders)
        $isFlatStructure = ! isset($orderData['GeneralInfo']);

        return [
            // Order identifiers
            'order_id' => $this->sanitizeOrderId($orderData),
            'order_number' => $this->sanitizeString($orderData['OrderNumber'] ?? $orderData['nOrderId'] ?? null),
            'linnworks_order_id' => $this->sanitizeString($orderData['pkOrderID'] ?? $orderData['OrderId'] ?? null),

            // Dates
            'received_date' => $this->sanitizeDateTime($orderData['ReceivedDate'] ?? $generalInfo['ReceivedDate'] ?? null),
            'processed_date' => $this->sanitizeDateTime($orderData['ProcessedDateTime'] ?? $orderData['ProcessedOn'] ?? null),

            // Channel information
            'channel_name' => $this->sanitizeString($orderData['Source'] ?? $generalInfo['Source'] ?? null),
            'channel_reference_id' => $this->sanitizeString($orderData['ReferenceNum'] ?? $generalInfo['ReferenceNum'] ?? null),
            'sub_source' => $this->sanitizeString($orderData['SubSource'] ?? $generalInfo['SubSource'] ?? null),

            // Totals
            'total_charge' => $this->sanitizeDecimal($orderData['TotalCharge'] ?? $totalsInfo['TotalCharge'] ?? 0),
            'postage_cost' => $this->sanitizeDecimal($orderData['PostageCost'] ?? $totalsInfo['PostageCost'] ?? 0),
            'tax_amount' => $this->sanitizeDecimal($orderData['Tax'] ?? $totalsInfo['TotalTax'] ?? 0),
            'profit_margin' => $this->sanitizeDecimal($orderData['ProfitMargin'] ?? $totalsInfo['Profit'] ?? 0),
            'item_cost' => $this->sanitizeDecimal($orderData['ItemCost'] ?? $totalsInfo['ItemCost'] ?? 0),

            // Status
            'is_open' => $this->sanitizeBoolean($orderData['IsOpen'] ?? ! isset($orderData['ProcessedDateTime'])),
            'is_processed' => $this->sanitizeBoolean($orderData['IsProcessed'] ?? isset($orderData['ProcessedDateTime'])),

            // Location
            'location_id' => $this->sanitizeString($orderData['LocationId'] ?? $generalInfo['Location'] ?? null),

            // Items
            'items' => $this->sanitizeItems($items),

            // Counts
            'items_count' => $this->sanitizeInteger($orderData['ItemsCount'] ?? count($items)),

            // Notes
            'notes' => $this->sanitizeText($orderData['Notes'] ?? $generalInfo['Notes'] ?? null),

            // Tags
            'tags' => $this->sanitizeTags($orderData['Tags'] ?? []),
        ];
    }

    /**
     * Sanitize order ID (handles multiple field variations)
     */
    private function sanitizeOrderId(array $data): ?string
    {
        $possibleIds = [
            $data['OrderId'] ?? null,
            $data['pkOrderID'] ?? null,
            $data['nOrderId'] ?? null,
            $data['order_id'] ?? null,
        ];

        foreach ($possibleIds as $id) {
            if ($id !== null && $id !== '') {
                return $this->sanitizeString($id);
            }
        }

        return null;
    }

    /**
     * Sanitize string value
     */
    private function sanitizeString(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return trim((string) $value);
    }

    /**
     * Sanitize text (multiline)
     */
    private function sanitizeText(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return trim(strip_tags((string) $value));
    }

    /**
     * Sanitize decimal value
     */
    private function sanitizeDecimal(mixed $value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        // Handle string decimals with commas
        if (is_string($value)) {
            $value = str_replace(',', '', $value);
        }

        return round((float) $value, 2);
    }

    /**
     * Sanitize integer value
     */
    private function sanitizeInteger(mixed $value): int
    {
        if ($value === null || $value === '') {
            return 0;
        }

        return (int) $value;
    }

    /**
     * Sanitize boolean value
     */
    private function sanitizeBoolean(mixed $value): bool
    {
        if ($value === null) {
            return false;
        }

        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            return in_array(strtolower($value), ['true', '1', 'yes', 'on'], true);
        }

        return (bool) $value;
    }

    /**
     * Sanitize DateTime value
     */
    private function sanitizeDateTime(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof Carbon) {
            return $value;
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value);
        }

        try {
            // Handle Linnworks datetime formats
            // Common formats: "2024-01-15T10:30:00", "/Date(1234567890000)/"

            if (is_string($value)) {
                // .NET JSON date format: /Date(1234567890000)/
                if (preg_match('/\/Date\((\d+)\)\//', $value, $matches)) {
                    return Carbon::createFromTimestampMs((int) $matches[1]);
                }

                return Carbon::parse($value);
            }

            return null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Sanitize items array
     */
    private function sanitizeItems(array $items): array
    {
        return array_map(function ($item) {
            $itemData = is_array($item) ? $item : (array) $item;

            return [
                'sku' => $this->sanitizeString($itemData['SKU'] ?? $itemData['ItemNumber'] ?? null),
                'title' => $this->sanitizeString($itemData['ItemTitle'] ?? $itemData['Title'] ?? null),
                'quantity' => $this->sanitizeInteger($itemData['Quantity'] ?? $itemData['Qty'] ?? 1),
                'price_per_unit' => $this->sanitizeDecimal($itemData['PricePerUnit'] ?? $itemData['UnitPrice'] ?? 0),
                'tax_rate' => $this->sanitizeDecimal($itemData['TaxRate'] ?? 0),
                'cost' => $this->sanitizeDecimal($itemData['Cost'] ?? $itemData['ItemCost'] ?? 0),
                'weight' => $this->sanitizeDecimal($itemData['Weight'] ?? 0),
                'linnworks_product_id' => $this->sanitizeString($itemData['StockItemId'] ?? $itemData['ProductId'] ?? null),
            ];
        }, $items);
    }

    /**
     * Sanitize tags
     */
    private function sanitizeTags(mixed $tags): array
    {
        if ($tags === null || $tags === '') {
            return [];
        }

        if (is_string($tags)) {
            // Comma-separated tags
            $tags = explode(',', $tags);
        }

        if (! is_array($tags)) {
            return [];
        }

        return array_values(array_filter(array_map('trim', $tags)));
    }

    /**
     * Validate sanitized data meets minimum requirements
     */
    public function validate(array $sanitizedData): bool
    {
        // Must have either order_id or order_number
        if (empty($sanitizedData['order_id']) && empty($sanitizedData['order_number'])) {
            return false;
        }

        // Total charge should be non-negative
        if ($sanitizedData['total_charge'] < 0) {
            return false;
        }

        // Must have at least one item
        if (empty($sanitizedData['items'])) {
            return false;
        }

        return true;
    }

    /**
     * Remove null values from sanitized data
     */
    public function removeNulls(array $data): array
    {
        return array_filter($data, fn ($value) => $value !== null);
    }

    /**
     * Get sanitization summary/stats
     */
    public function getSanitizationSummary(array $original, array $sanitized): array
    {
        return [
            'original_fields' => count($original),
            'sanitized_fields' => count($sanitized),
            'null_fields' => count(array_filter($sanitized, fn ($v) => $v === null)),
            'items_sanitized' => count($sanitized['items'] ?? []),
            'has_order_id' => ! empty($sanitized['order_id']),
            'has_order_number' => ! empty($sanitized['order_number']),
            'is_valid' => $this->validate($sanitized),
        ];
    }
}
