<?php

namespace App\Http\Controllers\Api\Analytics;

use App\Http\Controllers\Controller;
use App\Services\Api\SearchAnalyticsService;
use Illuminate\Http\JsonResponse;

class SearchStatsController extends Controller
{
    public function __construct(
        private readonly SearchAnalyticsService $analyticsService
    ) {}

    /**
     * Get search analytics and statistics
     */
    public function __invoke(): JsonResponse
    {
        $response = $this->analyticsService->getAnalytics();
        
        return $response->toJsonResponse();
    }
}