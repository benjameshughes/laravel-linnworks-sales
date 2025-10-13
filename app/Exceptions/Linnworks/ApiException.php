<?php

declare(strict_types=1);

namespace App\Exceptions\Linnworks;

/**
 * Thrown when Linnworks API requests fail
 */
final class ApiException extends LinnworksException
{
    public static function requestFailed(string $endpoint, string $reason, array $context = []): self
    {
        return new self(
            "Linnworks API request failed: {$endpoint}",
            array_merge($context, [
                'endpoint' => $endpoint,
                'reason' => $reason,
            ])
        );
    }

    public static function invalidResponse(string $endpoint, string $reason): self
    {
        return new self(
            "Invalid response from Linnworks API: {$endpoint}",
            ['endpoint' => $endpoint, 'reason' => $reason]
        );
    }

    public static function rateLimitExceeded(string $endpoint, ?int $retryAfter = null): self
    {
        return new self(
            'Linnworks API rate limit exceeded',
            [
                'endpoint' => $endpoint,
                'retry_after_seconds' => $retryAfter,
            ]
        );
    }
}
