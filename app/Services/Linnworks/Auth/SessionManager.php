<?php

namespace App\Services\Linnworks\Auth;

use App\Models\LinnworksConnection;
use App\ValueObjects\Linnworks\SessionToken;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SessionManager
{
    private const CACHE_PREFIX = 'linnworks_session:';

    private const CACHE_TTL = 1800; // 30 minutes

    public function __construct(
        private readonly AuthenticationService $authService,
    ) {}

    /**
     * Get a valid session token for the user
     */
    public function getValidSessionToken(int $userId): ?SessionToken
    {
        $cacheKey = $this->getCacheKey($userId);

        // Try to get from cache first
        $cachedToken = Cache::get($cacheKey);
        if ($cachedToken) {
            $sessionToken = SessionToken::fromCacheData($cachedToken);

            // Check if token is still valid
            if (! $sessionToken->isExpiringSoon()) {
                Log::debug('Using cached session token', [
                    'user_id' => $userId,
                    'expires_at' => $sessionToken->expiresAt->toISOString(),
                ]);

                return $sessionToken;
            }
        }

        // Token expired or not cached, refresh it
        return $this->refreshSessionToken($userId);
    }

    /**
     * Refresh session token for the user
     */
    public function refreshSessionToken(int $userId): ?SessionToken
    {
        $connection = LinnworksConnection::where('user_id', $userId)->active()->first();

        if (! $connection) {
            Log::warning('No active Linnworks connection found for user', ['user_id' => $userId]);

            return null;
        }

        try {
            // Get new session token using database credentials (automatically decrypted by model)
            $sessionToken = $this->authService->createSessionToken(
                $connection->application_id,
                $connection->application_secret,
                $connection->access_token
            );

            if ($sessionToken) {
                // Cache the new token
                $this->cacheSessionToken($userId, $sessionToken);

                $connection->fill([
                    'session_token' => $sessionToken->token,
                    'server_location' => $sessionToken->server,
                    'session_expires_at' => $sessionToken->expiresAt,
                    'status' => 'active',
                ])->save();

                Log::info('Session token refreshed successfully', [
                    'user_id' => $userId,
                    'expires_at' => $sessionToken->expiresAt->toISOString(),
                ]);

                return $sessionToken;
            }

            Log::error('Failed to refresh session token', ['user_id' => $userId]);

            return null;

        } catch (\Exception $e) {
            Log::error('Error refreshing session token', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Cache session token
     */
    private function cacheSessionToken(int $userId, SessionToken $sessionToken): void
    {
        $cacheKey = $this->getCacheKey($userId);
        $cacheData = $sessionToken->toCacheData();

        // Cache with TTL based on token expiry
        $secondsUntilExpiry = max(0, $sessionToken->expiresAt->diffInSeconds(now(), false));
        $ttl = max(60, min(self::CACHE_TTL, $secondsUntilExpiry));

        Cache::put($cacheKey, $cacheData, $ttl);

        Log::debug('Session token cached', [
            'user_id' => $userId,
            'cache_key' => $cacheKey,
            'ttl' => $ttl,
        ]);
    }

    /**
     * Clear cached session token
     */
    public function clearSessionToken(int $userId): void
    {
        $cacheKey = $this->getCacheKey($userId);
        Cache::forget($cacheKey);

        Log::debug('Session token cleared', [
            'user_id' => $userId,
            'cache_key' => $cacheKey,
        ]);
    }

    /**
     * Check if user has a valid session
     */
    public function hasValidSession(int $userId): bool
    {
        $sessionToken = $this->getValidSessionToken($userId);

        return $sessionToken !== null;
    }

    /**
     * Get session token info without refreshing
     */
    public function getSessionInfo(int $userId): ?array
    {
        $cacheKey = $this->getCacheKey($userId);
        $cachedToken = Cache::get($cacheKey);

        if (! $cachedToken) {
            return null;
        }

        $sessionToken = SessionToken::fromCacheData($cachedToken);

        return [
            'user_id' => $userId,
            'server' => $sessionToken->server,
            'expires_at' => $sessionToken->expiresAt->toISOString(),
            'is_expired' => $sessionToken->isExpired(),
            'is_expiring_soon' => $sessionToken->isExpiringSoon(),
            'cached_at' => now()->toISOString(),
        ];
    }

    /**
     * Get all active sessions
     */
    public function getActiveSessions(): array
    {
        // This would require a more sophisticated cache implementation
        // For now, return empty array as we don't track all sessions
        return [];
    }

    /**
     * Clear all cached sessions
     */
    public function clearAllSessions(): void
    {
        // Pattern-based cache clearing would be needed here
        // For now, just flush all cache (not ideal for production)
        Cache::flush();

        Log::info('All session tokens cleared');
    }

    /**
     * Get cache key for user session
     */
    private function getCacheKey(int $userId): string
    {
        return self::CACHE_PREFIX.$userId;
    }

    /**
     * Get session statistics
     */
    public function getStats(): array
    {
        return [
            'cache_prefix' => self::CACHE_PREFIX,
            'cache_ttl' => self::CACHE_TTL,
            'active_sessions' => count($this->getActiveSessions()),
        ];
    }
}
