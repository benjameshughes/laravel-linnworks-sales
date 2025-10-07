<?php

namespace App\Services\Api;

use App\Services\ProductSearchService;
use App\ValueObjects\Api\SearchRequest;
use App\ValueObjects\Api\ApiResponse;
use App\Http\Resources\ProductSearchResource;
use Illuminate\Support\Collection;

readonly class ProductSearchApiService
{
    public function __construct(
        private ProductSearchService $searchService
    ) {}

    public function quickSearch(SearchRequest $request): ApiResponse
    {
        $results = $this->searchService->advancedSearch($request->toSearchCriteria());
        
        return ApiResponse::success(
            data: $this->transformProducts($results),
            meta: $this->buildSearchMeta($request, $results->count())
        );
    }

    public function findBySku(string $sku): ApiResponse
    {
        $product = $this->searchService->findBySku($sku);

        if (!$product) {
            return ApiResponse::notFound(
                message: 'Product not found',
                meta: collect(['sku' => $sku])
            );
        }

        return ApiResponse::success(
            data: collect(['product' => ProductSearchResource::detailed($product)->toArray(request())]),
            meta: collect([
                'sku' => $sku,
                'found_at' => now()->toISOString(),
            ])
        );
    }

    private function transformProducts(Collection $products): Collection
    {
        return $products->map(fn($product) => 
            ProductSearchResource::basic($product)->toArray(request())
        );
    }

    private function buildSearchMeta(SearchRequest $request, int $count): Collection
    {
        return collect([
            'query' => $request->query,
            'type' => $request->type->value,
            'count' => $count,
            'limit' => $request->limit,
            'include_inactive' => $request->includeInactive,
            'include_out_of_stock' => $request->includeOutOfStock,
        ]);
    }
}