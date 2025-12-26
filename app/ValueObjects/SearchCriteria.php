<?php

namespace App\ValueObjects;

use App\Enums\SearchType;
use Illuminate\Support\Collection;
use JsonSerializable;

readonly class SearchCriteria implements JsonSerializable
{
    public function __construct(
        public string $query,
        public SearchType $type = SearchType::COMBINED,
        public bool $fuzzySearch = true,
        public bool $exactMatch = false,
        public ?int $limit = null,
        public array $filters = [],
        public ?string $sortBy = null,
        public string $sortDirection = 'desc',
        public bool $includeInactive = false,
        public bool $includeOutOfStock = true,
    ) {}

    public function isEmpty(): bool
    {
        return trim($this->query) === '';
    }

    public function isAdvanced(): bool
    {
        return ! empty($this->filters) || $this->type !== SearchType::COMBINED;
    }

    public function placeholder(): string
    {
        return $this->type->getPlaceholder();
    }

    public function icon(): string
    {
        return $this->type->getIcon();
    }

    public function searchFields(): array
    {
        return $this->type->getSearchFields();
    }

    public function getProcessedQuery(): string
    {
        $query = trim($this->query);

        if ($this->isEmpty()) {
            return '';
        }

        // Handle exact match
        if ($this->exactMatch) {
            return '"'.$query.'"';
        }

        // Handle wildcards for specific search types
        if ($this->type->supportsWildcards() && ! str_contains($query, '*')) {
            return "*{$query}*";
        }

        // Handle fuzzy search
        if ($this->fuzzySearch && $this->type->supportsFuzzySearch()) {
            return $this->processFuzzyQuery($query);
        }

        return $query;
    }

    private function processFuzzyQuery(string $query): string
    {
        // Split query into words and add fuzzy matching
        $words = collect(explode(' ', $query))
            ->filter(fn ($word) => strlen(trim($word)) > 2)
            ->map(fn ($word) => trim($word).'~')
            ->implode(' ');

        return $words ?: $query;
    }

    public function getScoutQuery(): array
    {
        $scoutOptions = [];

        if ($this->limit) {
            $scoutOptions['limit'] = $this->limit;
        }

        // Add filters for Scout
        if (! empty($this->filters)) {
            $scoutOptions['filters'] = $this->filters;
        }

        // Add field-specific search
        if ($this->type !== SearchType::COMBINED) {
            $scoutOptions['fields'] = $this->searchFields();
        }

        return $scoutOptions;
    }

    public function getEloquentConstraints(): callable
    {
        return function ($query) {
            // Apply activity filter
            if (! $this->includeInactive) {
                $query->where('is_active', true);
            }

            // Apply stock filter
            if (! $this->includeOutOfStock) {
                $query->where('stock_level', '>', 0);
            }

            // Apply additional filters
            foreach ($this->filters as $field => $value) {
                if (is_array($value)) {
                    $query->whereIn($field, $value);
                } else {
                    $query->where($field, $value);
                }
            }

            // Apply sorting
            if ($this->sortBy) {
                $query->orderBy($this->sortBy, $this->sortDirection);
            }
        };
    }

    public function getSuggestions(Collection $products): Collection
    {
        if ($this->isEmpty() || $this->query === '*') {
            return collect();
        }

        $query = strtolower($this->query);

        return $products
            ->flatMap(function ($product) use ($query) {
                $suggestions = collect();

                // Add exact matches first
                if (str_contains(strtolower($product->sku ?? ''), $query)) {
                    $suggestions->push([
                        'type' => 'sku',
                        'value' => $product->sku,
                        'label' => $product->sku,
                        'context' => $product->title,
                        'priority' => 1,
                    ]);
                }

                if (str_contains(strtolower($product->title ?? ''), $query)) {
                    $suggestions->push([
                        'type' => 'title',
                        'value' => $product->title,
                        'label' => $product->title,
                        'context' => $product->sku,
                        'priority' => 2,
                    ]);
                }

                if ($product->category_name && str_contains(strtolower($product->category_name), $query)) {
                    $suggestions->push([
                        'type' => 'category',
                        'value' => $product->category_name,
                        'label' => $product->category_name,
                        'context' => 'Category',
                        'priority' => 3,
                    ]);
                }

                if ($product->brand && str_contains(strtolower($product->brand), $query)) {
                    $suggestions->push([
                        'type' => 'brand',
                        'value' => $product->brand,
                        'label' => $product->brand,
                        'context' => 'Brand',
                        'priority' => 4,
                    ]);
                }

                return $suggestions;
            })
            ->unique('value')
            ->sortBy('priority')
            ->take(8)
            ->values();
    }

    public function toArray(): array
    {
        return [
            'query' => $this->query,
            'type' => $this->type->value,
            'fuzzy_search' => $this->fuzzySearch,
            'exact_match' => $this->exactMatch,
            'limit' => $this->limit,
            'filters' => $this->filters,
            'sort_by' => $this->sortBy,
            'sort_direction' => $this->sortDirection,
            'include_inactive' => $this->includeInactive,
            'include_out_of_stock' => $this->includeOutOfStock,
            'is_empty' => $this->isEmpty(),
            'is_advanced' => $this->isAdvanced(),
            'processed_query' => $this->getProcessedQuery(),
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function withQuery(string $query): self
    {
        return new self(
            query: $query,
            type: $this->type,
            fuzzySearch: $this->fuzzySearch,
            exactMatch: $this->exactMatch,
            limit: $this->limit,
            filters: $this->filters,
            sortBy: $this->sortBy,
            sortDirection: $this->sortDirection,
            includeInactive: $this->includeInactive,
            includeOutOfStock: $this->includeOutOfStock,
        );
    }

    public function withType(SearchType $type): self
    {
        return new self(
            query: $this->query,
            type: $type,
            fuzzySearch: $this->fuzzySearch,
            exactMatch: $this->exactMatch,
            limit: $this->limit,
            filters: $this->filters,
            sortBy: $this->sortBy,
            sortDirection: $this->sortDirection,
            includeInactive: $this->includeInactive,
            includeOutOfStock: $this->includeOutOfStock,
        );
    }

    public function withFilters(array $filters): self
    {
        return new self(
            query: $this->query,
            type: $this->type,
            fuzzySearch: $this->fuzzySearch,
            exactMatch: $this->exactMatch,
            limit: $this->limit,
            filters: array_merge($this->filters, $filters),
            sortBy: $this->sortBy,
            sortDirection: $this->sortDirection,
            includeInactive: $this->includeInactive,
            includeOutOfStock: $this->includeOutOfStock,
        );
    }

    public function withSort(string $sortBy, string $direction = 'desc'): self
    {
        return new self(
            query: $this->query,
            type: $this->type,
            fuzzySearch: $this->fuzzySearch,
            exactMatch: $this->exactMatch,
            limit: $this->limit,
            filters: $this->filters,
            sortBy: $sortBy,
            sortDirection: $direction,
            includeInactive: $this->includeInactive,
            includeOutOfStock: $this->includeOutOfStock,
        );
    }

    public function withLimit(int $limit): self
    {
        return new self(
            query: $this->query,
            type: $this->type,
            fuzzySearch: $this->fuzzySearch,
            exactMatch: $this->exactMatch,
            limit: $limit,
            filters: $this->filters,
            sortBy: $this->sortBy,
            sortDirection: $this->sortDirection,
            includeInactive: $this->includeInactive,
            includeOutOfStock: $this->includeOutOfStock,
        );
    }
}
