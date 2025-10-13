<?php

namespace App\Http\Controllers\Api\Search;

use App\Http\Controllers\Controller;
use App\Services\Api\ProductSearchApiService;
use App\ValueObjects\Api\SearchRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QuickSearchController extends Controller
{
    public function __construct(
        private readonly ProductSearchApiService $searchApiService
    ) {}

    /**
     * Quick search endpoint for simple queries
     */
    public function __invoke(Request $request): JsonResponse
    {
        $searchRequest = SearchRequest::fromRequest($request);
        $response = $this->searchApiService->quickSearch($searchRequest);

        return $response->toJsonResponse();
    }
}
