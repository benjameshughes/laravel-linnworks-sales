<?php

use App\Models\LinnworksConnection;
use App\Models\User;
use App\Services\Linnworks\Auth\SessionManager;
use App\ValueObjects\Linnworks\SessionToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->sessionManager = app(SessionManager::class);
    Cache::flush(); // Clear cache before each test
});

test('refreshSessionToken uses encrypted credentials from database', function () {
    $user = User::factory()->create();

    // Create connection with encrypted credentials
    $connection = LinnworksConnection::factory()->create([
        'user_id' => $user->id,
        'application_id' => 'session-app-123',
        'application_secret' => 'session-secret-456',
        'access_token' => 'session-token-789',
        'is_active' => true,
    ]);

    Http::fake([
        '*/api/Auth/AuthorizeByApplication' => Http::response([
            'Token' => 'new-session-abc',
            'Server' => 'https://eu-ext.linnworks.net',
            'TTL' => 7200,
        ], 200),
    ]);

    $sessionToken = $this->sessionManager->refreshSessionToken($user->id);

    expect($sessionToken)->toBeInstanceOf(SessionToken::class)
        ->and($sessionToken->token)->toBe('new-session-abc');

    // Verify correct encrypted credentials were used
    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'AuthorizeByApplication')
            && $request['ApplicationId'] === 'session-app-123'
            && $request['ApplicationSecret'] === 'session-secret-456'
            && $request['Token'] === 'session-token-789';
    });

    // Verify connection was updated with new session token (encrypted)
    $connection->refresh();
    expect($connection->session_token)->toBe('new-session-abc');

    // Verify session token is encrypted in database
    $rawSessionToken = \DB::table('linnworks_connections')
        ->where('id', $connection->id)
        ->value('session_token');

    expect($rawSessionToken)->not->toBe('new-session-abc');
});

test('refreshSessionToken only uses active connections', function () {
    $user = User::factory()->create();

    // Create inactive connection
    LinnworksConnection::factory()->inactive()->create([
        'user_id' => $user->id,
        'is_active' => false,
    ]);

    Http::fake();

    $sessionToken = $this->sessionManager->refreshSessionToken($user->id);

    expect($sessionToken)->toBeNull();

    // No HTTP requests should have been made
    Http::assertNothingSent();
});

test('getValidSessionToken returns cached session when available', function () {
    $user = User::factory()->create();

    LinnworksConnection::factory()->create([
        'user_id' => $user->id,
        'is_active' => true,
    ]);

    // Cache a valid session token
    $cachedToken = [
        'token' => 'cached-token-123',
        'server' => 'https://eu-ext.linnworks.net',
        'expires_at' => now()->addHours(2)->toISOString(),
    ];

    Cache::put("linnworks_session:{$user->id}", $cachedToken, 1800);

    Http::fake(); // Should not make any HTTP requests

    $sessionToken = $this->sessionManager->getValidSessionToken($user->id);

    expect($sessionToken)->toBeInstanceOf(SessionToken::class)
        ->and($sessionToken->token)->toBe('cached-token-123');

    // Verify no HTTP requests were made
    Http::assertNothingSent();
});

test('getValidSessionToken refreshes when cache expires', function () {
    $user = User::factory()->create();

    LinnworksConnection::factory()->create([
        'user_id' => $user->id,
        'application_id' => 'refresh-app',
        'application_secret' => 'refresh-secret',
        'access_token' => 'refresh-token',
        'is_active' => true,
    ]);

    // Cache an expiring token
    $expiringToken = [
        'token' => 'expiring-token',
        'server' => 'https://eu-ext.linnworks.net',
        'expires_at' => now()->addMinutes(4)->toISOString(), // Expiring soon
    ];

    Cache::put("linnworks_session:{$user->id}", $expiringToken, 60);

    Http::fake([
        '*/api/Auth/AuthorizeByApplication' => Http::response([
            'Token' => 'refreshed-token-abc',
            'Server' => 'https://eu-ext.linnworks.net',
            'TTL' => 7200,
        ], 200),
    ]);

    $sessionToken = $this->sessionManager->getValidSessionToken($user->id);

    expect($sessionToken)->toBeInstanceOf(SessionToken::class)
        ->and($sessionToken->token)->toBe('refreshed-token-abc');

    // Verify HTTP request was made to refresh
    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'AuthorizeByApplication');
    });
});

test('clearSessionToken removes cached token', function () {
    $user = User::factory()->create();

    // Cache a token
    Cache::put("linnworks_session:{$user->id}", ['token' => 'test-token'], 1800);

    $this->sessionManager->clearSessionToken($user->id);

    // Verify cache is cleared
    expect(Cache::has("linnworks_session:{$user->id}"))->toBeFalse();
});

test('hasValidSession returns true when user has valid session', function () {
    $user = User::factory()->create();

    LinnworksConnection::factory()->create([
        'user_id' => $user->id,
        'application_id' => 'valid-app',
        'application_secret' => 'valid-secret',
        'access_token' => 'valid-token',
        'is_active' => true,
    ]);

    Http::fake([
        '*/api/Auth/AuthorizeByApplication' => Http::response([
            'Token' => 'valid-session-token',
            'Server' => 'https://eu-ext.linnworks.net',
            'TTL' => 7200,
        ], 200),
    ]);

    $hasValid = $this->sessionManager->hasValidSession($user->id);

    expect($hasValid)->toBeTrue();
});

test('hasValidSession returns false when user has no connection', function () {
    $user = User::factory()->create();

    $hasValid = $this->sessionManager->hasValidSession($user->id);

    expect($hasValid)->toBeFalse();
});

test('refreshSessionToken caches the new session token', function () {
    $user = User::factory()->create();

    LinnworksConnection::factory()->create([
        'user_id' => $user->id,
        'application_id' => 'cache-app',
        'application_secret' => 'cache-secret',
        'access_token' => 'cache-token',
        'is_active' => true,
    ]);

    Http::fake([
        '*/api/Auth/AuthorizeByApplication' => Http::response([
            'Token' => 'cached-session-xyz',
            'Server' => 'https://eu-ext.linnworks.net',
            'TTL' => 7200,
        ], 200),
    ]);

    $this->sessionManager->refreshSessionToken($user->id);

    // Verify token is cached
    $cacheKey = "linnworks_session:{$user->id}";
    expect(Cache::has($cacheKey))->toBeTrue();

    $cachedData = Cache::get($cacheKey);
    expect($cachedData['token'])->toBe('cached-session-xyz');
});

test('refreshSessionToken updates connection with new session token', function () {
    $user = User::factory()->create();

    $connection = LinnworksConnection::factory()->create([
        'user_id' => $user->id,
        'application_id' => 'update-app',
        'application_secret' => 'update-secret',
        'access_token' => 'update-token',
        'session_token' => 'old-session-token',
        'server_location' => 'https://old-server.linnworks.net',
        'is_active' => true,
    ]);

    Http::fake([
        '*/api/Auth/AuthorizeByApplication' => Http::response([
            'Token' => 'new-updated-session',
            'Server' => 'https://eu-ext.linnworks.net',
            'TTL' => 7200,
        ], 200),
    ]);

    $this->sessionManager->refreshSessionToken($user->id);

    $connection->refresh();

    expect($connection->session_token)->toBe('new-updated-session')
        ->and($connection->server_location)->toBe('https://eu-ext.linnworks.net')
        ->and($connection->status)->toBe('active');
});

test('getSessionInfo returns cached session information', function () {
    $user = User::factory()->create();

    $cachedToken = [
        'token' => 'info-token-123',
        'server' => 'https://eu-ext.linnworks.net',
        'expires_at' => now()->addHours(2)->toISOString(),
    ];

    Cache::put("linnworks_session:{$user->id}", $cachedToken, 1800);

    $info = $this->sessionManager->getSessionInfo($user->id);

    expect($info)->toBeArray()
        ->and($info['user_id'])->toBe($user->id)
        ->and($info['server'])->toBe('https://eu-ext.linnworks.net')
        ->and($info['is_expired'])->toBeFalse()
        ->and($info['is_expiring_soon'])->toBeFalse();
});

test('getSessionInfo returns null when no cached session', function () {
    $user = User::factory()->create();

    $info = $this->sessionManager->getSessionInfo($user->id);

    expect($info)->toBeNull();
});
