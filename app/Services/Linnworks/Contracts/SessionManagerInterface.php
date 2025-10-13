<?php

namespace App\Services\Linnworks\Contracts;

use App\ValueObjects\Linnworks\SessionToken;

interface SessionManagerInterface
{
    /**
     * Get a valid session token for the user
     */
    public function getValidSessionToken(int $userId): ?SessionToken;

    /**
     * Refresh session token for the user
     */
    public function refreshSessionToken(int $userId): ?SessionToken;

    /**
     * Clear cached session token
     */
    public function clearSessionToken(int $userId): void;

    /**
     * Check if user has a valid session
     */
    public function hasValidSession(int $userId): bool;

    /**
     * Get session token info without refreshing
     */
    public function getSessionInfo(int $userId): ?array;

    /**
     * Get session statistics
     */
    public function getStats(): array;
}
