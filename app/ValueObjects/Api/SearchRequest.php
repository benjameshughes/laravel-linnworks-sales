<?php

namespace App\ValueObjects\Api;

use App\Enums\SearchType;
use Illuminate\Http\Request;

readonly class SearchRequest
{
    public function __construct(
        public string $query,
        public SearchType $type,
        public int $limit,
        public bool $includeInactive,
        public bool $includeOutOfStock,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $validated = $request->validate([
            'query' => ['required', 'string', 'min:1', 'max:200'],
            'type' => ['sometimes', 'string'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'include_inactive' => ['sometimes', 'boolean'],
            'include_out_of_stock' => ['sometimes', 'boolean'],
        ]);

        return new self(
            query: trim($validated['query']),
            type: SearchType::tryFrom($validated['type'] ?? 'combined') ?? SearchType::COMBINED,
            limit: $validated['limit'] ?? 10,
            includeInactive: $validated['include_inactive'] ?? false,
            includeOutOfStock: $validated['include_out_of_stock'] ?? true,
        );
    }

    public function toSearchCriteria(): array
    {
        return [
            'query' => $this->query,
            'type' => $this->type->value,
            'limit' => $this->limit,
            'include_inactive' => $this->includeInactive,
            'include_out_of_stock' => $this->includeOutOfStock,
        ];
    }
}