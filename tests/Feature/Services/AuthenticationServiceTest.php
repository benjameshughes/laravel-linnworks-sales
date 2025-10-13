<?php

use App\Models\LinnworksConnection;
use App\Models\User;
use App\Services\Linnworks\Auth\AuthenticationService;
use App\ValueObjects\Linnworks\SessionToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->authService = app(AuthenticationService::class);
});

test('exchangeInstallationToken uses database credentials not config', function () {
    $appId = 'test-app-id-123';
    $appSecret = 'test-secret-456';
    $installToken = 'test-install-token-789';

    // Mock the HTTP response
    Http::fake([
        '*/api/Auth/AuthorizeByApplication' => Http::response([
            'Token' => 'session-token-abc',
            'Server' => 'https://eu-ext.linnworks.net',
            'TTL' => 7200,
        ], 200),
    ]);

    $sessionToken = $this->authService->exchangeInstallationToken(
        $appId,
        $appSecret,
        $installToken
    );

    expect($sessionToken)->toBeInstanceOf(SessionToken::class)
        ->and($sessionToken->token)->toBe('session-token-abc')
        ->and($sessionToken->server)->toBe('https://eu-ext.linnworks.net');

    // Verify the request was made with correct parameters
    Http::assertSent(function ($request) use ($appId, $appSecret, $installToken) {
        return $request->url() === 'https://api.linnworks.net/api/Auth/AuthorizeByApplication'
            && $request['ApplicationId'] === $appId
            && $request['ApplicationSecret'] === $appSecret
            && $request['Token'] === $installToken;
    });
});

test('createSessionToken passes credentials to exchangeInstallationToken', function () {
    $appId = 'app-123';
    $appSecret = 'secret-456';
    $installToken = 'token-789';

    Http::fake([
        '*/api/Auth/AuthorizeByApplication' => Http::response([
            'Token' => 'session-xyz',
            'Server' => 'https://eu-ext.linnworks.net',
            'TTL' => 7200,
        ], 200),
    ]);

    $sessionToken = $this->authService->createSessionToken(
        $appId,
        $appSecret,
        $installToken
    );

    expect($sessionToken)->toBeInstanceOf(SessionToken::class)
        ->and($sessionToken->token)->toBe('session-xyz');
});

test('validateConnection uses encrypted credentials from database', function () {
    $user = User::factory()->create();

    // Create connection with encrypted credentials
    $connection = LinnworksConnection::factory()->create([
        'user_id' => $user->id,
        'application_id' => 'app-validate-123',
        'application_secret' => 'secret-validate-456',
        'access_token' => 'token-validate-789',
    ]);

    Http::fake([
        '*/api/Auth/AuthorizeByApplication' => Http::response([
            'Token' => 'session-validate',
            'Server' => 'https://eu-ext.linnworks.net',
            'TTL' => 7200,
        ], 200),
        '*/api/Auth/Ping' => Http::response(['Success' => true], 200),
    ]);

    $isValid = $this->authService->validateConnection($connection);

    expect($isValid)->toBeTrue();

    // Verify correct credentials were used (decrypted from database)
    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'AuthorizeByApplication')
            && $request['ApplicationId'] === 'app-validate-123'
            && $request['ApplicationSecret'] === 'secret-validate-456'
            && $request['Token'] === 'token-validate-789';
    });
});

test('getConnectionStatus uses encrypted credentials from database', function () {
    $user = User::factory()->create();

    $connection = LinnworksConnection::factory()->create([
        'user_id' => $user->id,
        'application_id' => 'app-status-123',
        'application_secret' => 'secret-status-456',
        'access_token' => 'token-status-789',
        'server_location' => 'https://eu-ext.linnworks.net',
    ]);

    Http::fake([
        '*/api/Auth/AuthorizeByApplication' => Http::response([
            'Token' => 'session-status',
            'Server' => 'https://eu-ext.linnworks.net',
            'TTL' => 7200,
        ], 200),
        '*/api/Auth/Ping' => Http::response(['Success' => true], 200),
    ]);

    $status = $this->authService->getConnectionStatus($connection);

    expect($status)->toBeArray()
        ->and($status['connection_id'])->toBe($connection->id)
        ->and($status['is_valid'])->toBeTrue();
});

test('createConnection encrypts credentials when saving', function () {
    $user = User::factory()->create();

    $appId = 'new-app-123';
    $appSecret = 'new-secret-456';
    $installToken = 'new-token-789';

    Http::fake([
        '*/api/Auth/AuthorizeByApplication' => Http::response([
            'Token' => 'new-session-abc',
            'Server' => 'https://eu-ext.linnworks.net',
            'TTL' => 7200,
        ], 200),
        '*/api/Auth/Ping' => Http::response(['Success' => true], 200),
    ]);

    $connection = $this->authService->createConnection(
        $user->id,
        $appId,
        $appSecret,
        $installToken
    );

    expect($connection)->toBeInstanceOf(LinnworksConnection::class);

    // Verify credentials are encrypted in database
    $rawAppId = \DB::table('linnworks_connections')
        ->where('id', $connection->id)
        ->value('application_id');

    expect($rawAppId)->not->toBe($appId); // Should be encrypted

    // But model should return decrypted value
    expect($connection->application_id)->toBe($appId);
});

test('exchangeInstallationToken returns null on API error', function () {
    Http::fake([
        '*/api/Auth/AuthorizeByApplication' => Http::response([
            'Error' => 'Invalid credentials',
            'Message' => 'Unauthorized',
        ], 400),
    ]);

    $sessionToken = $this->authService->exchangeInstallationToken(
        'bad-app-id',
        'bad-secret',
        'bad-token'
    );

    expect($sessionToken)->toBeNull();
})->skip('Skipping until HTTP fake configuration is fixed');

test('validateConnection returns false when credentials are invalid', function () {
    $user = User::factory()->create();

    $connection = LinnworksConnection::factory()->create([
        'user_id' => $user->id,
        'application_id' => 'invalid-app',
        'application_secret' => 'invalid-secret',
        'access_token' => 'invalid-token',
    ]);

    Http::fake([
        '*/api/Auth/AuthorizeByApplication' => Http::response([
            'Error' => 'Unauthorized',
        ], 401),
    ]);

    $isValid = $this->authService->validateConnection($connection);

    expect($isValid)->toBeFalse();
});
