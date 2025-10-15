<?php

namespace App\ValueObjects\Linnworks;

use JsonSerializable;

readonly class RateLimitConfig implements JsonSerializable
{
    public function __construct(
        public int $maxRequests,
        public int $perMinutes,
        public int $delayBetweenRequests = 0,
        public int $maxRetries = 3,
        public int $retryDelay = 1000,
    ) {}

    public static function standard(): self
    {
        // Maximum aggression - Linnworks supports 250 req/min for GetOrdersById
        // Minimal delay allows bursting to API limit, rate limiter enforces 250/min cap
        return new self(
            maxRequests: 250,
            perMinutes: 1,
            delayBetweenRequests: 50, // Allow ~20 req/sec attempts, rate limiter caps at 250/min
            maxRetries: 3,
            retryDelay: 1000,
        );
    }

    public static function high(): self
    {
        return new self(
            maxRequests: 250,
            perMinutes: 1,
            delayBetweenRequests: 250,
            maxRetries: 5,
            retryDelay: 500,
        );
    }

    public static function conservative(): self
    {
        return new self(
            maxRequests: 100,
            perMinutes: 1,
            delayBetweenRequests: 600,
            maxRetries: 2,
            retryDelay: 2000,
        );
    }

    public function getDelayInMilliseconds(): int
    {
        return $this->delayBetweenRequests;
    }

    public function getRetryDelayInMilliseconds(): int
    {
        return $this->retryDelay;
    }

    public function getCacheKey(): string
    {
        return "rate_limit_{$this->maxRequests}_{$this->perMinutes}";
    }

    public function jsonSerialize(): array
    {
        return [
            'max_requests' => $this->maxRequests,
            'per_minutes' => $this->perMinutes,
            'delay_between_requests' => $this->delayBetweenRequests,
            'max_retries' => $this->maxRetries,
            'retry_delay' => $this->retryDelay,
        ];
    }
}
