<?php

namespace App\Services\Linnworks\Contracts;

use App\Models\LinnworksConnection;
use App\ValueObjects\Linnworks\SessionToken;

interface AuthenticationServiceInterface
{
    /**
     * Generate installation URL for OAuth flow
     */
    public function generateInstallUrl(): string;

    /**
     * Exchange installation token for session token
     */
    public function exchangeInstallationToken(string $installationToken): ?SessionToken;

    /**
     * Create session token from installation token
     */
    public function createSessionToken(string $installationToken): ?SessionToken;

    /**
     * Test connection with session token
     */
    public function testConnection(SessionToken $sessionToken): bool;

    /**
     * Create or update user connection
     */
    public function createConnection(int $userId, string $installationToken): ?LinnworksConnection;

    /**
     * Validate existing connection
     */
    public function validateConnection(LinnworksConnection $connection): bool;

    /**
     * Get connection status
     */
    public function getConnectionStatus(LinnworksConnection $connection): array;
}