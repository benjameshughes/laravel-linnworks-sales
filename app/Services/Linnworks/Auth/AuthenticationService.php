<?php

namespace App\Services\Linnworks\Auth;

use App\Models\LinnworksConnection;
use Illuminate\Support\Facades\Log;
use App\ValueObjects\Linnworks\SessionToken;
use BenHughes\Linnworks\Exceptions\LinnworksException;
use BenHughes\Linnworks\LinnworksClient as PackageClient;

final class AuthenticationService
{
    private const AUTH_BASE_URL = 'https://api.linnworks.net/api/';

    private const INSTALL_URL = 'https://api.linnworks.net/api/Auth/AuthorizeByApplication';

    private const TOKEN_URL = 'https://api.linnworks.net/api/Auth/AuthorizeByApplication';

    /**
     * Generate installation URL for OAuth flow
     */
    public function generateInstallUrl(string $applicationId, string $applicationSecret, string $redirectUri): string
    {
        $params = http_build_query([
            'ApplicationId' => $applicationId,
            'ApplicationSecret' => $applicationSecret,
            'RedirectUri' => $redirectUri,
        ]);

        return self::INSTALL_URL.'?'.$params;
    }

    /**
     * Exchange installation token for session token
     *
     * @param  string  $applicationId  Application ID from database
     * @param  string  $applicationSecret  Application Secret from database
     * @param  string  $installationToken  Installation/Access token from database
     */
    public function exchangeInstallationToken(
        string $applicationId,
        string $applicationSecret,
        string $installationToken
    ): ?SessionToken {
        Log::info('Exchanging installation token for session token', [
            'token_length' => strlen($installationToken),
        ]);

        // A one-off client for the credentials being installed - the shared
        // singleton is bound to whatever connection is already active.
        $client = new PackageClient(
            clientId: $applicationId,
            clientSecret: $applicationSecret,
            appToken: $installationToken,
            baseUrl: config('linnworks.base_url'),
            authUrl: config('linnworks.auth_url'),
        );

        $token = $client->authenticate();

        Log::info('Installation token exchanged successfully');

        return new SessionToken(
            token: $token,
            server: $client->baseUrl(),
            expiresAt: now()->addMinutes((int) config('linnworks.cache.token_ttl', 1440)),
        );
    }

    /**
     * Create session token from installation token
     *
     * @param  string  $applicationId  Application ID from database
     * @param  string  $applicationSecret  Application Secret from database
     * @param  string  $installationToken  Installation/Access token from database
     */
    public function createSessionToken(
        string $applicationId,
        string $applicationSecret,
        string $installationToken
    ): ?SessionToken {
        return $this->exchangeInstallationToken($applicationId, $applicationSecret, $installationToken);
    }

    /**
     * Test connection with session token
     */
    public function testConnection(SessionToken $sessionToken): bool
    {
        Log::info('Testing Linnworks connection', [
            'server' => $sessionToken->server,
            'expires_at' => $sessionToken->expiresAt->toISOString(),
        ]);

        // Cheapest authenticated call that proves the token is live. A thrown
        // ApiException means the credentials are no longer good.
        try {
            app(PackageClient::class)->locations()->all();
        } catch (LinnworksException $e) {
            Log::warning('Connection test failed', ['error' => $e->getMessage()]);

            return false;
        }

        Log::info('Connection test successful');

        return true;
    }

    /**
     * Create or update user connection
     *
     * @deprecated Use LinnworksOAuthService::createConnection() instead
     */
    public function createConnection(
        int $userId,
        string $applicationId,
        string $applicationSecret,
        string $installationToken
    ): ?LinnworksConnection {
        // First exchange the installation token
        $sessionToken = $this->exchangeInstallationToken($applicationId, $applicationSecret, $installationToken);

        if (! $sessionToken) {
            Log::error('Cannot create connection: failed to exchange installation token', [
                'user_id' => $userId,
            ]);

            return null;
        }

        // Test the connection
        if (! $this->testConnection($sessionToken)) {
            Log::error('Cannot create connection: connection test failed', [
                'user_id' => $userId,
                'server' => $sessionToken->server,
            ]);

            return null;
        }

        try {
            // Create or update connection
            $connection = LinnworksConnection::updateOrCreate(
                ['user_id' => $userId],
                [
                    'application_id' => $applicationId,
                    'application_secret' => $applicationSecret,
                    'access_token' => $installationToken,
                    'session_token' => $sessionToken->token,
                    'server_location' => $sessionToken->server,
                    'session_expires_at' => $sessionToken->expiresAt,
                    'status' => 'active',
                    'is_active' => true,
                    'application_data' => [
                        'tracked_user_id' => $userId,
                        'session_expires_at' => $sessionToken->expiresAt->toISOString(),
                    ],
                ]
            );

            Log::info('Linnworks connection created/updated', [
                'user_id' => $userId,
                'connection_id' => $connection->id,
                'server' => $connection->server_location,
            ]);

            return $connection;

        } catch (\Exception $e) {
            Log::error('Error creating connection', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Validate existing connection
     */
    public function validateConnection(LinnworksConnection $connection): bool
    {
        try {
            // Credentials are automatically decrypted from database by model
            $sessionToken = $this->createSessionToken(
                $connection->application_id,
                $connection->application_secret,
                $connection->access_token
            );

            if (! $sessionToken) {
                Log::warning('Connection validation failed: cannot create session token', [
                    'connection_id' => $connection->id,
                    'user_id' => $connection->user_id,
                ]);

                return false;
            }

            $isValid = $this->testConnection($sessionToken);

            if (! $isValid) {
                Log::warning('Connection validation failed: connection test failed', [
                    'connection_id' => $connection->id,
                    'user_id' => $connection->user_id,
                ]);
            }

            return $isValid;

        } catch (\Exception $e) {
            Log::error('Error validating connection', [
                'connection_id' => $connection->id,
                'user_id' => $connection->user_id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Deactivate connection
     */
    public function deactivateConnection(LinnworksConnection $connection): bool
    {
        try {
            $connection->update(['is_active' => false]);

            Log::info('Connection deactivated', [
                'connection_id' => $connection->id,
                'user_id' => $connection->user_id,
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Error deactivating connection', [
                'connection_id' => $connection->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Get connection status
     */
    public function getConnectionStatus(LinnworksConnection $connection): array
    {
        // Credentials are automatically decrypted from database by model
        $sessionToken = $this->createSessionToken(
            $connection->application_id,
            $connection->application_secret,
            $connection->access_token
        );
        $isValid = $sessionToken ? $this->testConnection($sessionToken) : false;

        return [
            'connection_id' => $connection->id,
            'user_id' => $connection->user_id,
            'server' => $connection->server_location,
            'is_active' => $connection->is_active,
            'is_valid' => $isValid,
            'last_tested' => now()->toISOString(),
            'session_expires_at' => $sessionToken?->expiresAt?->toISOString(),
        ];
    }
}
