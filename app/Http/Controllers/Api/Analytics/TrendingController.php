<?php

namespace App\Http\Controllers\Api\Analytics;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use App\Services\Api\SearchAnalyticsService;

final class TrendingController extends Controller
{
    public function __construct(
        private readonly SearchAnalyticsService $analyticsService
    ) {}

    /**
     * Get trending searches and popular products
     */
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'limit' => ['sometimes', 'integer', 'min:1', 'max:20'],
        ]);

        $limit = $validated['limit'] ?? 10;
        $response = $this->analyticsService->getTrending($limit);

        return $response->toJsonResponse();
    }
}
