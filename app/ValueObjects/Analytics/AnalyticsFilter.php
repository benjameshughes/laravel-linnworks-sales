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
        public ?int $status = null,
        public ?string $searchTerm = null,
        public string $sortBy = 'received_at',
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
            status: isset($data['status']) ? (int) $data['status'] : null,
            searchTerm: $data['search'] ?? null,
            sortBy: $data['sort_by'] ?? 'received_at',
            sortDirection: $data['sort_direction'] ?? 'desc',
        );
    }

    public function applyToQuery(Builder $query): Builder
    {
        return $query
            ->when(
                $this->dateRange,
                fn (Builder $q) => $q->whereBetween('received_at', [
                    $this->dateRange->start,
                    $this->dateRange->end,
                ])
            )
            ->when(
                ! empty($this->channels),
                fn (Builder $q) => $q->whereIn('source', $this->channels)
            )
            ->when(
                ! empty($this->products),
                fn (Builder $q) => $q->whereJsonContains('items', function ($item) {
                    return in_array($item['sku'] ?? null, $this->products);
                })
            )
            ->when(
                $this->status !== null,
                fn (Builder $q) => $q->where('status', $this->status)
            )
            ->when(
                $this->searchTerm,
                fn (Builder $q) => $q->where(function (Builder $query) {
                    $query->where('number', 'like', "%{$this->searchTerm}%")
                        ->orWhere('source', 'like', "%{$this->searchTerm}%");
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
            status: $this->status,
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
            status: $this->status,
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
            status: $this->status,
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
            status: $this->status,
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
            'status' => $this->status,
            'search' => $this->searchTerm,
            'sort_by' => $this->sortBy,
            'sort_direction' => $this->sortDirection,
        ];
    }

    public function hasActiveFilters(): bool
    {
        return ! empty($this->channels)
            || ! empty($this->products)
            || $this->status !== null
            || $this->searchTerm !== null;
    }
}
