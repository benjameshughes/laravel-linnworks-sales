<?php

namespace App\Services\Linnworks\Contracts;

use App\ValueObjects\Linnworks\ApiRequest;
use App\ValueObjects\Linnworks\ApiResponse;
use App\ValueObjects\Linnworks\SessionToken;

interface LinnworksServiceInterface
{
    /**
     * Make an API request to Linnworks
     */
    public function makeRequest(ApiRequest $request, ?SessionToken $sessionToken = null): ApiResponse;

    /**
     * Get service statistics
     */
    public function getStats(): array;
}