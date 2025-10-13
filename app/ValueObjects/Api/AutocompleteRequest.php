<?php

namespace App\ValueObjects\Api;

use App\Enums\SearchType;
use Illuminate\Http\Request;

readonly class AutocompleteRequest
{
    public function __construct(
        public string $query,
        public SearchType $type,
        public int $limit,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $validated = $request->validate([
            'query' => ['required', 'string', 'min:1', 'max:100'],
            'type' => ['sometimes', 'string'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:20'],
        ]);

        return new self(
            query: trim($validated['query']),
            type: SearchType::tryFrom($validated['type'] ?? 'combined') ?? SearchType::COMBINED,
            limit: $validated['limit'] ?? 8,
        );
    }

    public function isValid(): bool
    {
        return strlen($this->query) >= 2;
    }

    public function isEmpty(): bool
    {
        return strlen($this->query) < 2;
    }
}
