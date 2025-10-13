<?php

namespace App\Services\Linnworks\Contracts;

use App\Services\Linnworks\Core\RateLimiter;

interface RateLimitedServiceInterface
{
    /**
     * Get the rate limiter instance
     */
    public function getRateLimiter(): RateLimiter;

    /**
     * Check if we can make a request within rate limits
     */
    public function canMakeRequest(): bool;

    /**
     * Clear cache for the service
     */
    public function clearCache(?string $pattern = null): void;
}
