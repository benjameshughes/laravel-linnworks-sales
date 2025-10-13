<?php

namespace App\Http\Controllers\Api\Search;

use App\Http\Controllers\Controller;
use App\Services\Api\ProductSearchApiService;
use Illuminate\Http\JsonResponse;

class ProductLookupController extends Controller
{
    public function __construct(
        private readonly ProductSearchApiService $searchApiService
    ) {}

    /**
     * Find product by SKU (exact match)
     */
    public function __invoke(string $sku): JsonResponse
    {
        $response = $this->searchApiService->findBySku($sku);

        return $response->toJsonResponse();
    }
}
