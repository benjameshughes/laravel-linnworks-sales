<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\Api\SearchAnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchMaintenanceController extends Controller
{
    public function __construct(
        private readonly SearchAnalyticsService $analyticsService
    ) {}

    /**
     * Refresh search index
     */
    public function reindex(): JsonResponse
    {
        // This would typically be protected by admin middleware
        $response = $this->analyticsService->reindexAll();
        
        return $response->toJsonResponse();
    }

    /**
     * Clear search cache
     */
    public function clearCache(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'pattern' => ['sometimes', 'string', 'max:100'],
        ]);

        $pattern = $validated['pattern'] ?? null;
        $response = $this->analyticsService->clearCache($pattern);
        
        return $response->toJsonResponse();
    }
}