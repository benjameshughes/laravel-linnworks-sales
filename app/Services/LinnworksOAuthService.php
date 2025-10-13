<?php

namespace App\Services;

use App\Models\LinnworksConnection;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LinnworksOAuthService
{
    private string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = config('linnworks.base_url', 'https://api.linnworks.net');
    }

    /**
     * Create or update a Linnworks connection for a user
     */
    public function createConnection(
        int $userId,
        string $applicationId,
        string $applicationSecret,
        string $accessToken
    ): LinnworksConnection {
        // Deactivate any existing connections for this user
        LinnworksConnection::forUser($userId)->update(['is_active' => false]);

        // Create new connection
        $connection = LinnworksConnection::create([
            'user_id' => $userId,
            'application_id' => $applicationId,
            'application_secret' => $applicationSecret,
            'access_token' => $accessToken,
            'is_active' => true,
        ]);

        // Try to establish session immediately
        $this->refreshSession($connection);

        return $connection;
    }

    /**
     * Get active connection for user
     */
    public function getActiveConnection(int $userId): ?LinnworksConnection
    {
        return LinnworksConnection::forUser($userId)->active()->first();
    }

    /**
     * Get session token using the installation token
     */
    public function refreshSession(LinnworksConnection $connection): bool
    {
        try {
            $requestData = [
                'ApplicationId' => $connection->application_id,
                'ApplicationSecret' => $connection->application_secret,
                'Token' => $connection->access_token, // This is the installation token
            ];

            Log::info('Attempting Linnworks session token exchange', [
                'url' => "{$this->baseUrl}/api/Auth/AuthorizeByApplication",
                'app_id' => $connection->application_id,
                'has_secret' => ! empty($connection->application_secret),
                'has_token' => ! empty($connection->access_token),
                'token_length' => strlen($connection->access_token),
            ]);

            // Step 1: Use installation token to get session token
            $response = Http::asForm()->post("{$this->baseUrl}/api/Auth/AuthorizeByApplication", $requestData);

            if ($response->successful()) {
                $data = $response->json();

                // Step 2: Store the session token and server info
                $connection->update([
                    'session_token' => $data['Token'], // This is the actual session token for API calls
                    'server_location' => $data['Server'],
                    'session_expires_at' => Carbon::now()->addHours(2), // Sessions typically last 2 hours
                    'application_data' => $data,
                    'status' => 'active',
                ]);

                Log::info('Linnworks session token obtained successfully', [
                    'user_id' => $connection->user_id,
                    'server' => $data['Server'],
                    'session_token' => substr($data['Token'], 0, 10).'...',
                ]);

                return true;
            }

            Log::error('Failed to get Linnworks session token', [
                'user_id' => $connection->user_id,
                'status' => $response->status(),
                'response' => $response->body(),
                'response_headers' => $response->headers(),
                'url' => "{$this->baseUrl}/api/Auth/AuthorizeByApplication",
                'request_data' => [
                    'ApplicationId' => $connection->application_id,
                    'has_secret' => ! empty($connection->application_secret),
                    'has_token' => ! empty($connection->access_token),
                ],
            ]);

            return false;
        } catch (Exception $e) {
            Log::error('Error getting Linnworks session token: '.$e->getMessage(), [
                'user_id' => $connection->user_id,
                'trace' => $e->getTraceAsString(),
            ]);

            return false;
        }
    }

    /**
     * Test connection validity by getting session token and testing API
     */
    public function testConnection(LinnworksConnection $connection): bool
    {
        // Step 1: Get a session token using the installation token
        if (! $this->refreshSession($connection)) {
            Log::error('Failed to get session token for connection test', [
                'user_id' => $connection->user_id,
            ]);

            return false;
        }

        // Step 2: Test the session token with a simple API call
        try {
            // Use HTTPS and test with a lightweight endpoint (GetStockLocations)
            $serverUrl = str_replace('http://', 'https://', $connection->server_location);

            $response = Http::withHeaders([
                'Authorization' => $connection->session_token,
            ])->post("{$serverUrl}/api/Inventory/GetStockLocations");

            if ($response->successful()) {
                Log::info('Linnworks connection test successful - session token works', [
                    'user_id' => $connection->user_id,
                    'server' => $connection->server_location,
                ]);

                return true;
            }

            Log::warning('Linnworks session token test failed', [
                'user_id' => $connection->user_id,
                'status' => $response->status(),
                'response' => $response->body(),
            ]);

            return false;
        } catch (Exception $e) {
            Log::error('Error testing Linnworks session token: '.$e->getMessage(), [
                'user_id' => $connection->user_id,
            ]);

            return false;
        }
    }

    /**
     * Get valid session token for user, refreshing if necessary
     */
    public function getValidSessionToken(int $userId): ?array
    {
        $connection = $this->getActiveConnection($userId);

        if (! $connection) {
            return null;
        }

        if ($connection->needs_new_session) {
            if (! $this->refreshSession($connection)) {
                return null;
            }
            $connection->refresh();
        }

        return [
            'token' => $connection->session_token,
            'server' => $connection->server_location,
        ];
    }

    /**
     * Disconnect user's Linnworks connection
     */
    public function disconnect(int $userId): bool
    {
        $connection = $this->getActiveConnection($userId);

        if (! $connection) {
            return false;
        }

        $connection->update(['is_active' => false]);

        Log::info('Linnworks connection disconnected', [
            'user_id' => $userId,
        ]);

        return true;
    }

    /**
     * Check if user has an active Linnworks connection
     */
    public function isConnected(int $userId): bool
    {
        $connection = $this->getActiveConnection($userId);

        return $connection && $connection->is_active;
    }

    /**
     * Get connection status for user
     */
    public function getConnectionStatus(int $userId): array
    {
        // Only check ACTIVE connections for status
        $connection = LinnworksConnection::forUser($userId)->active()->first();

        if (! $connection) {
            return [
                'connected' => false,
                'status' => 'not_connected',
                'message' => 'No Linnworks connection found',
            ];
        }

        if (! $connection->is_active) {
            return [
                'connected' => false,
                'status' => 'inactive',
                'message' => 'Connection is inactive',
            ];
        }

        if ($connection->needs_new_session) {
            return [
                'connected' => true,
                'status' => 'needs_refresh',
                'message' => 'Session needs to be refreshed',
                'last_connected' => $connection->updated_at,
            ];
        }

        return [
            'connected' => true,
            'status' => 'active',
            'message' => 'Connection is active and ready',
            'server' => $connection->server_location,
            'expires_at' => $connection->session_expires_at,
            'last_connected' => $connection->updated_at,
        ];
    }
}
