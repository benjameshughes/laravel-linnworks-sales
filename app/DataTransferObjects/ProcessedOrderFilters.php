<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

use App\Enums\Linnworks\ProcessedOrderDateField;

/**
 * Filters for searching processed orders
 *
 * Encapsulates all filter options for the ProcessedOrders/SearchProcessedOrders endpoint.
 * Provides type safety and clear documentation of available filters.
 */
final readonly class ProcessedOrderFilters
{
    public function __construct(
        public ProcessedOrderDateField $dateField = ProcessedOrderDateField::RECEIVED,
        public ?string $channel = null,
        public ?string $orderNumber = null,
        public ?string $referenceNumber = null,
        public ?int $paymentStatus = null,
    ) {}

    /**
     * Create filters for historical imports
     * Uses 'processed' date field by default
     */
    public static function forHistoricalImport(): self
    {
        return new self(
            dateField: ProcessedOrderDateField::PROCESSED,
        );
    }

    /**
     * Create filters for recent orders sync
     * Uses 'received' date field by default
     */
    public static function forRecentSync(): self
    {
        return new self(
            dateField: ProcessedOrderDateField::RECEIVED,
        );
    }

    /**
     * Create filters with custom date field
     */
    public static function withDateField(ProcessedOrderDateField $dateField): self
    {
        return new self(dateField: $dateField);
    }

    /**
     * Create filters for a specific channel
     */
    public function forChannel(string $channel): self
    {
        return new self(
            dateField: $this->dateField,
            channel: $channel,
            orderNumber: $this->orderNumber,
            referenceNumber: $this->referenceNumber,
            paymentStatus: $this->paymentStatus,
        );
    }

    /**
     * Create filters with order number search
     */
    public function withOrderNumber(string $orderNumber): self
    {
        return new self(
            dateField: $this->dateField,
            channel: $this->channel,
            orderNumber: $orderNumber,
            referenceNumber: $this->referenceNumber,
            paymentStatus: $this->paymentStatus,
        );
    }

    /**
     * Create filters with payment status
     */
    public function withPaymentStatus(int $paymentStatus): self
    {
        return new self(
            dateField: $this->dateField,
            channel: $this->channel,
            orderNumber: $this->orderNumber,
            referenceNumber: $this->referenceNumber,
            paymentStatus: $paymentStatus,
        );
    }

    /**
     * Convert to array for legacy code compatibility
     */
    public function toArray(): array
    {
        return array_filter([
            'dateField' => $this->dateField->value,
            'channel' => $this->channel,
            'orderNumber' => $this->orderNumber,
            'referenceNumber' => $this->referenceNumber,
            'paymentStatus' => $this->paymentStatus,
        ], fn ($value) => $value !== null);
    }

    /**
     * Create from legacy array format
     */
    public static function fromArray(array $filters): self
    {
        return new self(
            dateField: isset($filters['dateField'])
                ? ProcessedOrderDateField::from($filters['dateField'])
                : ProcessedOrderDateField::RECEIVED,
            channel: $filters['channel'] ?? null,
            orderNumber: $filters['orderNumber'] ?? null,
            referenceNumber: $filters['referenceNumber'] ?? null,
            paymentStatus: $filters['paymentStatus'] ?? null,
        );
    }
}
