<?php

namespace App\Services\Linnworks\Core;

use App\ValueObjects\Linnworks\RateLimitConfig;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class RateLimiter
{
    private const CACHE_PREFIX = 'linnworks_rate_limit:';
    private const CACHE_TTL = 60; // 1 minute

    public function __construct(
        private readonly RateLimitConfig $config = new RateLimitConfig(150, 1, 400, 3, 1000)
    ) {}

    /**
     * Check if we can make a request within rate limits
     */
    public function canMakeRequest(): bool
    {
        $key = $this->getCacheKey();
        $currentRequests = Cache::get($key, 0);
        
        return $currentRequests < $this->config->maxRequests;
    }

    /**
     * Record a request and apply rate limiting
     */
    public function recordRequest(): void
    {
        $key = $this->getCacheKey();
        $currentRequests = Cache::get($key, 0);
        
        // Increment request count
        Cache::put($key, $currentRequests + 1, self::CACHE_TTL);
        
        // Apply delay if configured
        if ($this->config->delayBetweenRequests > 0) {
            usleep($this->config->delayBetweenRequests * 1000); // Convert to microseconds
        }
        
        Log::debug('Linnworks rate limiter: Request recorded', [
            'requests_made' => $currentRequests + 1,
            'max_requests' => $this->config->maxRequests,
            'delay_ms' => $this->config->delayBetweenRequests,
        ]);
    }

    /**
     * Wait for rate limit to reset
     */
    public function waitForReset(): void
    {
        // Just wait for the full TTL period since we can't get exact TTL from database cache
        $waitSeconds = self::CACHE_TTL;

        Log::info('Linnworks rate limiter: Waiting for reset', [
            'wait_seconds' => $waitSeconds,
            'max_requests' => $this->config->maxRequests,
        ]);

        sleep($waitSeconds + 1); // Wait for TTL + 1 second buffer
    }

    /**
     * Get current request count
     */
    public function getCurrentRequestCount(): int
    {
        return Cache::get($this->getCacheKey(), 0);
    }

    /**
     * Get remaining requests
     */
    public function getRemainingRequests(): int
    {
        return max(0, $this->config->maxRequests - $this->getCurrentRequestCount());
    }

    /**
     * Check if we're approaching rate limit
     */
    public function isApproachingLimit(int $threshold = 10): bool
    {
        return $this->getRemainingRequests() <= $threshold;
    }

    /**
     * Reset rate limit counter (for testing)
     */
    public function reset(): void
    {
        Cache::forget($this->getCacheKey());
    }

    /**
     * Get retry delay in milliseconds
     */
    public function getRetryDelay(): int
    {
        return $this->config->retryDelay;
    }

    /**
     * Get maximum retries
     */
    public function getMaxRetries(): int
    {
        return $this->config->maxRetries;
    }

    /**
     * Get cache key for rate limiting
     */
    private function getCacheKey(): string
    {
        return self::CACHE_PREFIX . $this->config->getCacheKey();
    }

    /**
     * Get rate limit statistics
     */
    public function getStats(): array
    {
        return [
            'current_requests' => $this->getCurrentRequestCount(),
            'remaining_requests' => $this->getRemainingRequests(),
            'max_requests' => $this->config->maxRequests,
            'per_minutes' => $this->config->perMinutes,
            'delay_between_requests' => $this->config->delayBetweenRequests,
            'is_approaching_limit' => $this->isApproachingLimit(),
        ];
    }
}