<?php

namespace Tests\Unit\Services;

use App\Models\LinnworksConnection;
use App\Models\User;
use App\Services\LinnworksApiService;
use App\Services\LinnworksOAuthService;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class LinnworksApiServiceTest extends TestCase
{
    use RefreshDatabase;
    private LinnworksApiService $service;
    private LinnworksOAuthService $oauthService;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->oauthService = $this->createMock(LinnworksOAuthService::class);
        $this->service = new LinnworksApiService($this->oauthService);
    }

    public function test_is_configured_returns_true_when_oauth_connected()
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        
        $this->oauthService
            ->expects($this->once())
            ->method('isConnected')
            ->with($user->id)
            ->willReturn(true);

        $this->assertTrue($this->service->isConfigured());
    }

    public function test_is_configured_returns_false_when_oauth_not_connected_and_no_config()
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        
        $this->oauthService
            ->expects($this->once())
            ->method('isConnected')
            ->with($user->id)
            ->willReturn(false);

        config([
            'linnworks.application_id' => '',
            'linnworks.application_secret' => '',
            'linnworks.token' => '',
        ]);

        $this->assertFalse($this->service->isConfigured());
    }

    public function test_is_configured_returns_true_when_config_credentials_present()
    {
        config([
            'linnworks.application_id' => 'test-app-id',
            'linnworks.application_secret' => 'test-secret',
            'linnworks.token' => 'test-token',
        ]);

        $this->assertTrue($this->service->isConfigured());
    }

    public function test_authenticate_returns_false_when_not_configured()
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        
        $this->oauthService
            ->expects($this->once())
            ->method('isConnected')
            ->with($user->id)
            ->willReturn(false);

        config([
            'linnworks.application_id' => '',
            'linnworks.application_secret' => '',
            'linnworks.token' => '',
        ]);

        Log::shouldReceive('warning')
            ->once()
            ->with('Linnworks API credentials not configured');

        $this->assertFalse($this->service->authenticate());
    }

    public function test_authenticate_returns_true_on_successful_response()
    {
        config([
            'linnworks.base_url' => 'https://api.linnworks.net',
            'linnworks.application_id' => 'test-app-id',
            'linnworks.application_secret' => 'test-secret',
            'linnworks.token' => 'test-token',
        ]);

        Http::fake([
            'https://api.linnworks.net/api/Auth/AuthorizeByApplication' => Http::response([
                'Server' => 'https://eu-ext.linnworks.net',
                'Token' => 'session-token'
            ], 200)
        ]);

        $this->assertTrue($this->service->authenticate());
    }

    public function test_authenticate_returns_false_on_http_error()
    {
        config([
            'linnworks.base_url' => 'https://api.linnworks.net',
            'linnworks.application_id' => 'test-app-id',
            'linnworks.application_secret' => 'test-secret',
            'linnworks.token' => 'test-token',
        ]);

        Http::fake([
            'https://api.linnworks.net/api/Auth/AuthorizeByApplication' => Http::response([
                'error' => 'Invalid credentials'
            ], 401)
        ]);

        Log::shouldReceive('error')
            ->once()
            ->with('Linnworks authentication failed', [
                'status' => 401,
                'response' => '{"error":"Invalid credentials"}',
            ]);

        $this->assertFalse($this->service->authenticate());
    }

    public function test_authenticate_returns_false_on_exception()
    {
        config([
            'linnworks.base_url' => 'https://api.linnworks.net',
            'linnworks.application_id' => 'test-app-id',
            'linnworks.application_secret' => 'test-secret',
            'linnworks.token' => 'test-token',
        ]);

        Http::fake(function () {
            throw new Exception('Network error');
        });

        Log::shouldReceive('error')
            ->once()
            ->with('Linnworks authentication error: Network error');

        $this->assertFalse($this->service->authenticate());
    }

    public function test_get_orders_returns_empty_array_when_not_configured()
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        
        $this->oauthService
            ->expects($this->once())
            ->method('isConnected')
            ->with($user->id)
            ->willReturn(false);

        config([
            'linnworks.application_id' => '',
            'linnworks.application_secret' => '',
            'linnworks.token' => '',
        ]);

        $result = $this->service->getOrders();
        
        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $result);
        $this->assertTrue($result->isEmpty());
    }

    public function test_get_orders_returns_orders_on_success()
    {
        config([
            'linnworks.base_url' => 'https://api.linnworks.net',
            'linnworks.application_id' => 'test-app-id',
            'linnworks.application_secret' => 'test-secret',
            'linnworks.token' => 'test-token',
        ]);

        $expectedOrders = [
            'Data' => [
                [
                    'pkOrderID' => 'order-123',
                    'nOrderId' => 12345,
                    'fTotalCharge' => 29.99
                ]
            ]
        ];

        Http::fake([
            'https://api.linnworks.net/api/Auth/AuthorizeByApplication' => Http::response([
                'Server' => 'https://eu-ext.linnworks.net',
                'Token' => 'session-token'
            ], 200),
            'https://eu-ext.linnworks.net/api/Orders/GetOrders' => Http::response($expectedOrders, 200)
        ]);

        $result = $this->service->getOrders();
        
        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $result);
        $this->assertCount(1, $result);
        
        $order = $result->first();
        $this->assertInstanceOf(\App\DataTransferObjects\LinnworksOrder::class, $order);
        $this->assertEquals('order-123', $order->orderId);
    }

    public function test_get_orders_handles_date_parameters()
    {
        config([
            'linnworks.base_url' => 'https://api.linnworks.net',
            'linnworks.application_id' => 'test-app-id',
            'linnworks.application_secret' => 'test-secret',
            'linnworks.token' => 'test-token',
        ]);

        $from = Carbon::create(2023, 1, 1);
        $to = Carbon::create(2023, 1, 31);

        Http::fake([
            'https://api.linnworks.net/api/Auth/AuthorizeByApplication' => Http::response([
                'Server' => 'https://eu-ext.linnworks.net',
                'Token' => 'session-token'
            ], 200),
            'https://eu-ext.linnworks.net/api/Orders/GetOrders' => Http::response(['Data' => []], 200)
        ]);

        $this->service->getOrders($from, $to);

        Http::assertSent(function ($request) use ($from, $to) {
            if ($request->url() !== 'https://eu-ext.linnworks.net/api/Orders/GetOrders') {
                return false;
            }
            $data = $request->data();
            return $data['from'] === $from->format('Y-m-d\TH:i:s.v\Z') &&
                   $data['to'] === $to->format('Y-m-d\TH:i:s.v\Z');
        });
    }

    public function test_get_order_details_returns_null_when_not_configured()
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        
        $this->oauthService
            ->expects($this->once())
            ->method('isConnected')
            ->with($user->id)
            ->willReturn(false);

        config([
            'linnworks.application_id' => '',
            'linnworks.application_secret' => '',
            'linnworks.token' => '',
        ]);

        $result = $this->service->getOrderDetails('order-123');
        
        $this->assertNull($result);
    }

    public function test_get_order_details_returns_order_data_on_success()
    {
        config([
            'linnworks.base_url' => 'https://api.linnworks.net',
            'linnworks.application_id' => 'test-app-id',
            'linnworks.application_secret' => 'test-secret',
            'linnworks.token' => 'test-token',
        ]);

        $expectedOrder = [
            'pkOrderID' => 'order-123',
            'nOrderId' => 12345,
            'Items' => []
        ];

        Http::fake([
            'https://api.linnworks.net/api/Auth/AuthorizeByApplication' => Http::response([
                'Server' => 'https://eu-ext.linnworks.net',
                'Token' => 'session-token'
            ], 200),
            'https://eu-ext.linnworks.net/api/Orders/GetOrdersByOrderId' => Http::response([$expectedOrder], 200)
        ]);

        $result = $this->service->getOrderDetails('order-123');
        
        $this->assertInstanceOf(\App\DataTransferObjects\LinnworksOrder::class, $result);
        $this->assertEquals('order-123', $result->orderId);
        $this->assertEquals(12345, $result->orderNumber);
    }

    public function test_get_processed_orders_with_oauth_session()
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        
        $this->oauthService
            ->expects($this->once())
            ->method('isConnected')
            ->with($user->id)
            ->willReturn(true);

        $this->oauthService
            ->expects($this->once())
            ->method('getValidSessionToken')
            ->with($user->id)
            ->willReturn([
                'token' => 'session-token',
                'server' => 'https://eu-ext.linnworks.net'
            ]);

        $expectedOrders = [
            'Data' => [
                [
                    'pkOrderID' => 'processed-order-123',
                    'nOrderId' => 67890,
                    'fTotalCharge' => 49.99
                ]
            ]
        ];

        Http::fake([
            'https://eu-ext.linnworks.net/api/Orders/GetOrdersProcessed' => Http::response($expectedOrders, 200)
        ]);

        $result = $this->service->getProcessedOrders();
        
        $this->assertEquals($expectedOrders, $result);
    }

    public function test_test_connection_returns_true_on_successful_auth()
    {
        config([
            'linnworks.base_url' => 'https://api.linnworks.net',
            'linnworks.application_id' => 'test-app-id',
            'linnworks.application_secret' => 'test-secret',
            'linnworks.token' => 'test-token',
        ]);

        Http::fake([
            'https://api.linnworks.net/api/Auth/AuthorizeByApplication' => Http::response([
                'Server' => 'https://eu-ext.linnworks.net',
                'Token' => 'session-token'
            ], 200)
        ]);

        $this->assertTrue($this->service->testConnection());
    }

    public function test_test_connection_returns_false_on_exception()
    {
        config([
            'linnworks.base_url' => 'https://api.linnworks.net',
            'linnworks.application_id' => 'test-app-id',
            'linnworks.application_secret' => 'test-secret',
            'linnworks.token' => 'test-token',
        ]);

        Http::fake(function () {
            throw new Exception('Connection failed');
        });

        Log::shouldReceive('error')
            ->once()
            ->with('Linnworks authentication error: Connection failed');

        $this->assertFalse($this->service->testConnection());
    }

    public function test_get_recent_open_orders_returns_empty_array_when_no_connection()
    {
        $user = User::factory()->create();
        
        Log::shouldReceive('warning')
            ->once()
            ->with('No active Linnworks connection found for user', ['user_id' => $user->id]);

        $result = $this->service->getRecentOpenOrders($user->id);
        
        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $result);
        $this->assertTrue($result->isEmpty());
    }

    public function test_get_recent_open_orders_with_valid_session()
    {
        $user = User::factory()->create();
        
        // Create a real connection object with valid session
        $connection = LinnworksConnection::factory()->create([
            'user_id' => $user->id,
            'session_expires_at' => Carbon::now()->addHours(2),
            'session_token' => 'valid-session-token',
            'server_location' => 'https://eu-ext.linnworks.net',
            'is_active' => true,
        ]);

        $this->oauthService
            ->expects($this->any())
            ->method('getActiveConnection')
            ->with($user->id)
            ->willReturn($connection);

        Http::fake([
            'https://eu-ext.linnworks.net/api/Orders/GetOpenOrderIds' => Http::response(['order-123'], 200),
            'https://eu-ext.linnworks.net/api/Orders/GetOrder*' => Http::response([
                'pkOrderID' => 'order-123',
                'nOrderId' => 12345,
                'Items' => []
            ], 200)
        ]);

        Log::shouldReceive('info')->zeroOrMoreTimes();
        Log::shouldReceive('error')->zeroOrMoreTimes();
        Log::shouldReceive('warning')->zeroOrMoreTimes();

        $result = $this->service->getRecentOpenOrders($user->id);
        
        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $result);
        $this->assertCount(1, $result);
        
        $order = $result->first();
        $this->assertInstanceOf(\App\DataTransferObjects\LinnworksOrder::class, $order);
    }

    public function test_strip_customer_data_from_orders()
    {
        $user = User::factory()->create();
        
        // Create a real connection object with valid session
        $connection = LinnworksConnection::factory()->create([
            'user_id' => $user->id,
            'session_expires_at' => Carbon::now()->addHours(2),
            'session_token' => 'valid-session-token',
            'server_location' => 'https://eu-ext.linnworks.net',
            'is_active' => true,
        ]);

        $this->oauthService
            ->expects($this->any())
            ->method('getActiveConnection')
            ->with($user->id)
            ->willReturn($connection);

        $orderWithCustomerData = [
            'pkOrderID' => 'order-123',
            'nOrderId' => 12345,
            'CustomerName' => 'John Doe',
            'CustomerEmail' => 'john@example.com',
            'BillingAddress' => 'Secret Address',
            'fTotalCharge' => 29.99,
            'Items' => [
                [
                    'ItemId' => 'item-1',
                    'SKU' => 'SKU001',
                    'ItemTitle' => 'Test Product',
                    'Quantity' => 1,
                    'PricePerUnit' => 29.99,
                    'CustomerNotes' => 'Customer specific notes',
                ]
            ]
        ];

        Http::fake([
            'https://eu-ext.linnworks.net/api/Orders/GetOpenOrderIds' => Http::response(['order-123'], 200),
            'https://eu-ext.linnworks.net/api/Orders/GetOrder*' => Http::response($orderWithCustomerData, 200)
        ]);

        Log::shouldReceive('info')->zeroOrMoreTimes();
        Log::shouldReceive('error')->zeroOrMoreTimes();
        Log::shouldReceive('warning')->zeroOrMoreTimes();

        $result = $this->service->getRecentOpenOrders($user->id);
        
        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $result);
        $this->assertCount(1, $result);
        
        $order = $result->first();
        $this->assertInstanceOf(\App\DataTransferObjects\LinnworksOrder::class, $order);
        $this->assertEquals('order-123', $order->orderId);
        $this->assertEquals(12345, $order->orderNumber);
        $this->assertEquals(29.99, $order->totalCharge);
        
        $this->assertCount(1, $order->items);
        
        $item = $order->items->first();
        $this->assertInstanceOf(\App\DataTransferObjects\LinnworksOrderItem::class, $item);
        $this->assertEquals('item-1', $item->itemId);
        $this->assertEquals('SKU001', $item->sku);
        $this->assertEquals('Test Product', $item->itemTitle);
    }
}