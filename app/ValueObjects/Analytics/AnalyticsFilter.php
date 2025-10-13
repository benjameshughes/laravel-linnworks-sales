<?php

declare(strict_types=1);

namespace App\ValueObjects\Analytics;

use Illuminate\Database\Eloquent\Builder;

readonly class AnalyticsFilter
{
    public function __construct(
        public DateRange $dateRange,
        public array $channels = [],
        public array $products = [],
        public ?bool $isProcessed = null,
        public ?string $searchTerm = null,
        public string $sortBy = 'received_date',
        public string $sortDirection = 'desc',
    ) {}

    public static function default(): self
    {
        return new self(
            dateRange: DateRange::last30Days(),
        );
    }

    public static function fromArray(array $data): self
    {
        $dateRange = isset($data['start_date']) && isset($data['end_date'])
            ? DateRange::custom($data['start_date'], $data['end_date'])
            : (isset($data['preset'])
                ? DateRange::fromPreset($data['preset'])
                : DateRange::last30Days());

        return new self(
            dateRange: $dateRange,
            channels: $data['channels'] ?? [],
            products: $data['products'] ?? [],
            isProcessed: $data['is_processed'] ?? null,
            searchTerm: $data['search'] ?? null,
            sortBy: $data['sort_by'] ?? 'received_date',
            sortDirection: $data['sort_direction'] ?? 'desc',
        );
    }

    public function applyToQuery(Builder $query): Builder
    {
        return $query
            ->when(
                $this->dateRange,
                fn (Builder $q) => $q->whereBetween('received_date', [
                    $this->dateRange->start,
                    $this->dateRange->end,
                ])
            )
            ->when(
                ! empty($this->channels),
                fn (Builder $q) => $q->whereIn('channel_name', $this->channels)
            )
            ->when(
                ! empty($this->products),
                fn (Builder $q) => $q->whereJsonContains('items', function ($item) {
                    return in_array($item['sku'] ?? null, $this->products);
                })
            )
            ->when(
                $this->isProcessed !== null,
                fn (Builder $q) => $q->where('is_processed', $this->isProcessed)
            )
            ->when(
                $this->searchTerm,
                fn (Builder $q) => $q->where(function (Builder $query) {
                    $query->where('order_number', 'like', "%{$this->searchTerm}%")
                        ->orWhere('channel_name', 'like', "%{$this->searchTerm}%");
                })
            )
            ->orderBy($this->sortBy, $this->sortDirection);
    }

    public function withDateRange(DateRange $dateRange): self
    {
        return new self(
            dateRange: $dateRange,
            channels: $this->channels,
            products: $this->products,
            isProcessed: $this->isProcessed,
            searchTerm: $this->searchTerm,
            sortBy: $this->sortBy,
            sortDirection: $this->sortDirection,
        );
    }

    public function withChannels(array $channels): self
    {
        return new self(
            dateRange: $this->dateRange,
            channels: $channels,
            products: $this->products,
            isProcessed: $this->isProcessed,
            searchTerm: $this->searchTerm,
            sortBy: $this->sortBy,
            sortDirection: $this->sortDirection,
        );
    }

    public function withProducts(array $products): self
    {
        return new self(
            dateRange: $this->dateRange,
            channels: $this->channels,
            products: $products,
            isProcessed: $this->isProcessed,
            searchTerm: $this->searchTerm,
            sortBy: $this->sortBy,
            sortDirection: $this->sortDirection,
        );
    }

    public function withSort(string $sortBy, string $sortDirection = 'desc'): self
    {
        return new self(
            dateRange: $this->dateRange,
            channels: $this->channels,
            products: $this->products,
            isProcessed: $this->isProcessed,
            searchTerm: $this->searchTerm,
            sortBy: $sortBy,
            sortDirection: $sortDirection,
        );
    }

    public function toArray(): array
    {
        return [
            'date_range' => $this->dateRange->toArray(),
            'channels' => $this->channels,
            'products' => $this->products,
            'is_processed' => $this->isProcessed,
            'search' => $this->searchTerm,
            'sort_by' => $this->sortBy,
            'sort_direction' => $this->sortDirection,
        ];
    }

    public function hasActiveFilters(): bool
    {
        return ! empty($this->channels)
            || ! empty($this->products)
            || $this->isProcessed !== null
            || $this->searchTerm !== null;
    }
}
