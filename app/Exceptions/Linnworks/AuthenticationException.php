<?php

declare(strict_types=1);

namespace App\Exceptions\Linnworks;

/**
 * Thrown when Linnworks authentication fails
 */
final class AuthenticationException extends LinnworksException
{
    public static function noActiveConnection(): self
    {
        return new self(
            'No active Linnworks connection configured',
            ['reason' => 'no_connection']
        );
    }

    public static function invalidSession(int $userId): self
    {
        return new self(
            'Invalid or expired Linnworks session',
            ['user_id' => $userId, 'reason' => 'invalid_session']
        );
    }

    public static function sessionRefreshFailed(int $userId, string $reason): self
    {
        return new self(
            'Failed to refresh Linnworks session',
            ['user_id' => $userId, 'reason' => $reason]
        );
    }
}
