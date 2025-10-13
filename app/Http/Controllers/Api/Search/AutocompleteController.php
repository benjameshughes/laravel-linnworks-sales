<?php

namespace App\Http\Controllers\Api\Search;

use App\Http\Controllers\Controller;
use App\Services\Api\SearchAutocompleteService;
use App\ValueObjects\Api\AutocompleteRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AutocompleteController extends Controller
{
    public function __construct(
        private readonly SearchAutocompleteService $autocompleteService
    ) {}

    /**
     * Get autocomplete suggestions for product search
     */
    public function __invoke(Request $request): JsonResponse
    {
        $autocompleteRequest = AutocompleteRequest::fromRequest($request);
        $response = $this->autocompleteService->getAutocomplete($autocompleteRequest);

        return $response->toJsonResponse();
    }
}
