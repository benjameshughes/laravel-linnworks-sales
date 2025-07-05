<?php

namespace Tests\Unit\Services;

use App\Models\LinnworksConnection;
use App\Models\User;
use App\Services\LinnworksOAuthService;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class LinnworksOAuthServiceTest extends TestCase
{
    use RefreshDatabase;
    private LinnworksOAuthService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new LinnworksOAuthService();
    }

    public function test_create_connection_creates_new_connection_and_deactivates_existing()
    {
        $user = User::factory()->create();
        
        // Create existing connection
        $existingConnection = LinnworksConnection::factory()->create([
            'user_id' => $user->id,
            'is_active' => true,
        ]);

        Http::fake([
            'https://api.linnworks.net/api/Auth/AuthorizeByApplication' => Http::response([
                'Server' => 'https://eu-ext.linnworks.net',
                'Token' => 'session-token'
            ], 200)
        ]);

        $newConnection = $this->service->createConnection(
            $user->id,
            'new-app-id',
            'new-secret',
            'new-token'
        );

        $this->assertInstanceOf(LinnworksConnection::class, $newConnection);
        $this->assertEquals($user->id, $newConnection->user_id);
        $this->assertEquals('new-app-id', $newConnection->application_id);
        $this->assertEquals('new-secret', $newConnection->application_secret);
        $this->assertEquals('new-token', $newConnection->access_token);
        $this->assertTrue($newConnection->is_active);

        // Check existing connection is deactivated
        $existingConnection->refresh();
        $this->assertFalse($existingConnection->is_active);
    }

    public function test_get_active_connection_returns_active_connection()
    {
        $user = User::factory()->create();
        
        // Create inactive connection
        LinnworksConnection::factory()->create([
            'user_id' => $user->id,
            'is_active' => false,
        ]);

        // Create active connection
        $activeConnection = LinnworksConnection::factory()->create([
            'user_id' => $user->id,
            'is_active' => true,
        ]);

        $result = $this->service->getActiveConnection($user->id);

        $this->assertInstanceOf(LinnworksConnection::class, $result);
        $this->assertEquals($activeConnection->id, $result->id);
        $this->assertTrue($result->is_active);
    }

    public function test_get_active_connection_returns_null_when_no_active_connection()
    {
        $user = User::factory()->create();
        
        // Create inactive connection
        LinnworksConnection::factory()->create([
            'user_id' => $user->id,
            'is_active' => false,
        ]);

        $result = $this->service->getActiveConnection($user->id);

        $this->assertNull($result);
    }

    public function test_refresh_session_returns_true_on_successful_response()
    {
        $connection = LinnworksConnection::factory()->create([
            'application_id' => 'test-app-id',
            'application_secret' => 'test-secret',
            'access_token' => 'test-token',
        ]);

        Http::fake([
            'https://api.linnworks.net/api/Auth/AuthorizeByApplication' => Http::response([
                'Server' => 'https://eu-ext.linnworks.net',
                'Token' => 'new-session-token'
            ], 200)
        ]);

        $result = $this->service->refreshSession($connection);

        $this->assertTrue($result);
        
        $connection->refresh();
        $this->assertEquals('new-session-token', $connection->session_token);
        $this->assertEquals('https://eu-ext.linnworks.net', $connection->server_location);
        $this->assertEquals('active', $connection->status);
        $this->assertNotNull($connection->session_expires_at);
    }

    public function test_refresh_session_returns_false_on_http_error()
    {
        $connection = LinnworksConnection::factory()->create([
            'application_id' => 'test-app-id',
            'application_secret' => 'test-secret',
            'access_token' => 'test-token',
        ]);

        Http::fake([
            'https://api.linnworks.net/api/Auth/AuthorizeByApplication' => Http::response([
                'error' => 'Invalid credentials'
            ], 401)
        ]);

        Log::shouldReceive('info')
            ->once()
            ->with('Attempting Linnworks session token exchange', \Mockery::type('array'));

        Log::shouldReceive('error')
            ->once()
            ->with('Failed to get Linnworks session token', \Mockery::type('array'));

        $result = $this->service->refreshSession($connection);

        $this->assertFalse($result);
    }

    public function test_refresh_session_returns_false_on_exception()
    {
        $connection = LinnworksConnection::factory()->create([
            'application_id' => 'test-app-id',
            'application_secret' => 'test-secret',
            'access_token' => 'test-token',
        ]);

        Http::fake(function () {
            throw new Exception('Network error');
        });

        Log::shouldReceive('info')
            ->once()
            ->with('Attempting Linnworks session token exchange', \Mockery::type('array'));

        Log::shouldReceive('error')
            ->once()
            ->with('Error getting Linnworks session token: Network error', \Mockery::type('array'));

        $result = $this->service->refreshSession($connection);

        $this->assertFalse($result);
    }

    public function test_test_connection_returns_true_on_successful_session_and_api_test()
    {
        $connection = LinnworksConnection::factory()->create([
            'application_id' => 'test-app-id',
            'application_secret' => 'test-secret',
            'access_token' => 'test-token',
        ]);

        Http::fake([
            'https://api.linnworks.net/api/Auth/AuthorizeByApplication' => Http::response([
                'Server' => 'https://eu-ext.linnworks.net',
                'Token' => 'session-token'
            ], 200),
            'https://eu-ext.linnworks.net/api/Inventory/GetStockLocations' => Http::response([
                ['LocationId' => 1, 'LocationName' => 'Main']
            ], 200)
        ]);

        $result = $this->service->testConnection($connection);

        $this->assertTrue($result);
    }

    public function test_test_connection_returns_false_when_session_refresh_fails()
    {
        $connection = LinnworksConnection::factory()->create([
            'application_id' => 'test-app-id',
            'application_secret' => 'test-secret',
            'access_token' => 'test-token',
        ]);

        Http::fake([
            'https://api.linnworks.net/api/Auth/AuthorizeByApplication' => Http::response([
                'error' => 'Invalid credentials'
            ], 401)
        ]);

        Log::shouldReceive('error')
            ->once()
            ->with('Failed to get Linnworks session token', \Mockery::type('array'))
            ->andReturn(null);

        Log::shouldReceive('info')
            ->once()
            ->with('Attempting Linnworks session token exchange', \Mockery::type('array'));

        Log::shouldReceive('error')
            ->once()
            ->with('Failed to get session token for connection test', \Mockery::type('array'));

        $result = $this->service->testConnection($connection);

        $this->assertFalse($result);
    }

    public function test_test_connection_returns_false_when_api_test_fails()
    {
        $connection = LinnworksConnection::factory()->create([
            'application_id' => 'test-app-id',
            'application_secret' => 'test-secret',
            'access_token' => 'test-token',
        ]);

        Http::fake([
            'https://api.linnworks.net/api/Auth/AuthorizeByApplication' => Http::response([
                'Server' => 'https://eu-ext.linnworks.net',
                'Token' => 'session-token'
            ], 200),
            'https://eu-ext.linnworks.net/api/Inventory/GetStockLocations' => Http::response([
                'error' => 'Unauthorized'
            ], 401)
        ]);

        Log::shouldReceive('info')
            ->once()
            ->with('Attempting Linnworks session token exchange', \Mockery::type('array'));

        Log::shouldReceive('info')
            ->once()
            ->with('Linnworks session token obtained successfully', \Mockery::type('array'));

        Log::shouldReceive('warning')
            ->once()
            ->with('Linnworks session token test failed', \Mockery::type('array'));

        $result = $this->service->testConnection($connection);

        $this->assertFalse($result);
    }

    public function test_get_valid_session_token_returns_null_when_no_connection()
    {
        $user = User::factory()->create();

        $result = $this->service->getValidSessionToken($user->id);

        $this->assertNull($result);
    }

    public function test_get_valid_session_token_returns_existing_valid_token()
    {
        $user = User::factory()->create();
        $connection = LinnworksConnection::factory()->create([
            'user_id' => $user->id,
            'is_active' => true,
            'session_token' => 'valid-token',
            'server_location' => 'https://eu-ext.linnworks.net',
            'session_expires_at' => Carbon::now()->addHour(),
        ]);

        $result = $this->service->getValidSessionToken($user->id);

        $this->assertIsArray($result);
        $this->assertEquals('valid-token', $result['token']);
        $this->assertEquals('https://eu-ext.linnworks.net', $result['server']);
    }

    public function test_get_valid_session_token_refreshes_expired_token()
    {
        $user = User::factory()->create();
        $connection = LinnworksConnection::factory()->create([
            'user_id' => $user->id,
            'is_active' => true,
            'session_token' => 'expired-token',
            'server_location' => 'https://eu-ext.linnworks.net',
            'session_expires_at' => Carbon::now()->subHour(),
        ]);

        Http::fake([
            'https://api.linnworks.net/api/Auth/AuthorizeByApplication' => Http::response([
                'Server' => 'https://eu-ext.linnworks.net',
                'Token' => 'new-session-token'
            ], 200)
        ]);

        $result = $this->service->getValidSessionToken($user->id);

        $this->assertIsArray($result);
        $this->assertEquals('new-session-token', $result['token']);
        $this->assertEquals('https://eu-ext.linnworks.net', $result['server']);
    }

    public function test_disconnect_deactivates_connection()
    {
        $user = User::factory()->create();
        $connection = LinnworksConnection::factory()->create([
            'user_id' => $user->id,
            'is_active' => true,
        ]);

        Log::shouldReceive('info')
            ->once()
            ->with('Linnworks connection disconnected', ['user_id' => $user->id]);

        $result = $this->service->disconnect($user->id);

        $this->assertTrue($result);
        
        $connection->refresh();
        $this->assertFalse($connection->is_active);
    }

    public function test_disconnect_returns_false_when_no_connection()
    {
        $user = User::factory()->create();

        $result = $this->service->disconnect($user->id);

        $this->assertFalse($result);
    }

    public function test_is_connected_returns_true_when_active_connection_exists()
    {
        $user = User::factory()->create();
        LinnworksConnection::factory()->create([
            'user_id' => $user->id,
            'is_active' => true,
        ]);

        $result = $this->service->isConnected($user->id);

        $this->assertTrue($result);
    }

    public function test_is_connected_returns_false_when_no_active_connection()
    {
        $user = User::factory()->create();
        LinnworksConnection::factory()->create([
            'user_id' => $user->id,
            'is_active' => false,
        ]);

        $result = $this->service->isConnected($user->id);

        $this->assertFalse($result);
    }

    public function test_get_connection_status_returns_not_connected_when_no_connection()
    {
        $user = User::factory()->create();

        $result = $this->service->getConnectionStatus($user->id);

        $this->assertIsArray($result);
        $this->assertFalse($result['connected']);
        $this->assertEquals('not_connected', $result['status']);
        $this->assertEquals('No Linnworks connection found', $result['message']);
    }

    public function test_get_connection_status_returns_inactive_when_connection_inactive()
    {
        $user = User::factory()->create();
        LinnworksConnection::factory()->inactive()->create([
            'user_id' => $user->id,
        ]);

        $result = $this->service->getConnectionStatus($user->id);

        $this->assertIsArray($result);
        $this->assertFalse($result['connected']);
        $this->assertEquals('inactive', $result['status']);
        $this->assertEquals('Connection is inactive', $result['message']);
    }

    public function test_get_connection_status_returns_needs_refresh_when_session_expired()
    {
        $user = User::factory()->create();
        $connection = LinnworksConnection::factory()->create([
            'user_id' => $user->id,
            'is_active' => true,
            'session_expires_at' => Carbon::now()->subHour(),
        ]);

        $result = $this->service->getConnectionStatus($user->id);

        $this->assertIsArray($result);
        $this->assertTrue($result['connected']);
        $this->assertEquals('needs_refresh', $result['status']);
        $this->assertEquals('Session needs to be refreshed', $result['message']);
        $this->assertEquals($connection->updated_at, $result['last_connected']);
    }

    public function test_get_connection_status_returns_active_when_session_valid()
    {
        $user = User::factory()->create();
        $connection = LinnworksConnection::factory()->create([
            'user_id' => $user->id,
            'is_active' => true,
            'session_token' => 'valid-token',
            'server_location' => 'https://eu-ext.linnworks.net',
            'session_expires_at' => Carbon::now()->addHour(),
        ]);

        $result = $this->service->getConnectionStatus($user->id);

        $this->assertIsArray($result);
        $this->assertTrue($result['connected']);
        $this->assertEquals('active', $result['status']);
        $this->assertEquals('Connection is active and ready', $result['message']);
        $this->assertEquals('https://eu-ext.linnworks.net', $result['server']);
        $this->assertEquals($connection->session_expires_at, $result['expires_at']);
        $this->assertEquals($connection->updated_at, $result['last_connected']);
    }

    public function test_refresh_session_logs_request_details()
    {
        $connection = LinnworksConnection::factory()->create([
            'application_id' => 'test-app-id',
            'application_secret' => 'test-secret',
            'access_token' => 'test-token-123',
        ]);

        Http::fake([
            'https://api.linnworks.net/api/Auth/AuthorizeByApplication' => Http::response([
                'Server' => 'https://eu-ext.linnworks.net',
                'Token' => 'session-token'
            ], 200)
        ]);

        Log::shouldReceive('info')
            ->once()
            ->with('Attempting Linnworks session token exchange', [
                'url' => 'https://api.linnworks.net/api/Auth/AuthorizeByApplication',
                'app_id' => 'test-app-id',
                'has_secret' => true,
                'has_token' => true,
                'token_length' => 14,
            ]);

        Log::shouldReceive('info')
            ->once()
            ->with('Linnworks session token obtained successfully', \Mockery::type('array'));

        $this->service->refreshSession($connection);
    }

    public function test_refresh_session_uses_form_data_format()
    {
        $connection = LinnworksConnection::factory()->create([
            'application_id' => 'test-app-id',
            'application_secret' => 'test-secret',
            'access_token' => 'test-token',
        ]);

        Http::fake([
            'https://api.linnworks.net/api/Auth/AuthorizeByApplication' => Http::response([
                'Server' => 'https://eu-ext.linnworks.net',
                'Token' => 'session-token'
            ], 200)
        ]);

        $this->service->refreshSession($connection);

        Http::assertSent(function ($request) {
            return $request->hasHeader('Content-Type', 'application/x-www-form-urlencoded');
        });
    }
}