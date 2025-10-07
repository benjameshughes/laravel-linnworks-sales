<?php

namespace App\Services\Api;

use App\Services\ProductSearchService;
use App\ValueObjects\Api\AutocompleteRequest;
use App\ValueObjects\Api\ApiResponse;
use App\Http\Resources\SearchSuggestionResource;
use Illuminate\Support\Collection;

readonly class SearchAutocompleteService
{
    public function __construct(
        private ProductSearchService $searchService
    ) {}

    public function getAutocomplete(AutocompleteRequest $request): ApiResponse
    {
        if ($request->isEmpty()) {
            return $this->emptyResponse($request);
        }

        $suggestions = $this->searchService->autocomplete($request->query, $request->type);
        
        return ApiResponse::success(
            data: $this->transformSuggestions($suggestions, $request->query),
            meta: $this->buildMeta($request, $suggestions->count())
        );
    }

    private function emptyResponse(AutocompleteRequest $request): ApiResponse
    {
        return ApiResponse::success(
            data: collect(),
            meta: collect([
                'query' => $request->query,
                'type' => $request->type->value,
                'count' => 0,
            ])
        );
    }

    private function transformSuggestions(Collection $suggestions, string $query): Collection
    {
        return $suggestions->map(fn($suggestion) => 
            SearchSuggestionResource::withHighlight($suggestion, $query)->toArray(request())
        );
    }

    private function buildMeta(AutocompleteRequest $request, int $count): Collection
    {
        return collect([
            'query' => $request->query,
            'type' => $request->type->value,
            'count' => $count,
            'limit' => $request->limit,
            'cache_hit' => true, // Would track actual cache hits in production
        ]);
    }
}