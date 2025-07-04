<?php

namespace App\Services;

use App\Models\LinnworksConnection;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

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
     * Refresh session token for a connection
     */
    public function refreshSession(LinnworksConnection $connection): bool
    {
        try {
            $response = Http::post("{$this->baseUrl}/api/Auth/AuthorizeByApplication", [
                'ApplicationId' => $connection->application_id,
                'ApplicationSecret' => $connection->application_secret,
                'Token' => $connection->access_token,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                
                $connection->update([
                    'session_token' => $data['Token'],
                    'server_location' => $data['Server'],
                    'session_expires_at' => Carbon::now()->addHours(2), // Sessions typically last 2 hours
                    'application_data' => $data,
                ]);

                Log::info('Linnworks session refreshed successfully', [
                    'user_id' => $connection->user_id,
                    'server' => $data['Server'],
                ]);

                return true;
            }

            Log::error('Failed to refresh Linnworks session', [
                'user_id' => $connection->user_id,
                'status' => $response->status(),
                'response' => $response->body(),
            ]);

            return false;
        } catch (Exception $e) {
            Log::error('Error refreshing Linnworks session: ' . $e->getMessage(), [
                'user_id' => $connection->user_id,
            ]);
            return false;
        }
    }

    /**
     * Test connection validity
     */
    public function testConnection(LinnworksConnection $connection): bool
    {
        if ($connection->needsNewSession()) {
            if (!$this->refreshSession($connection)) {
                return false;
            }
        }

        try {
            // Test with a simple API call
            $response = Http::withHeaders([
                'Authorization' => $connection->session_token,
            ])->post("{$connection->server_location}/api/Auth/Ping");

            if ($response->successful()) {
                Log::info('Linnworks connection test successful', [
                    'user_id' => $connection->user_id,
                ]);
                return true;
            }

            Log::warning('Linnworks connection test failed', [
                'user_id' => $connection->user_id,
                'status' => $response->status(),
            ]);

            return false;
        } catch (Exception $e) {
            Log::error('Error testing Linnworks connection: ' . $e->getMessage(), [
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
        
        if (!$connection) {
            return null;
        }

        if ($connection->needsNewSession()) {
            if (!$this->refreshSession($connection)) {
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
        
        if (!$connection) {
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
        $connection = $this->getActiveConnection($userId);
        
        if (!$connection) {
            return [
                'connected' => false,
                'status' => 'not_connected',
                'message' => 'No Linnworks connection found',
            ];
        }

        if (!$connection->is_active) {
            return [
                'connected' => false,
                'status' => 'inactive',
                'message' => 'Connection is inactive',
            ];
        }

        if ($connection->needsNewSession()) {
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