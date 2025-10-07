<?php

use App\Models\Order;
use App\Services\LinnworksApiService;
use App\Services\LinnworksOAuthService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;

beforeEach(function () {
    // Mock Linnworks config
    Config::set('linnworks.application_id', 'test-app-id');
    Config::set('linnworks.application_secret', 'test-secret');
    Config::set('linnworks.token', 'test-token');
    Config::set('linnworks.base_url', 'https://api.linnworks.net');

    // Create test orders
    $this->testOrders = collect([
        Order::factory()->create([
            'linnworks_order_id' => 'uuid-1',
            'is_processed' => false,
            'received_date' => now()->subDays(5),
        ]),
        Order::factory()->create([
            'linnworks_order_id' => 'uuid-2',
            'is_processed' => true,
            'received_date' => now()->subDays(10),
        ]),
    ]);

    $this->oauthService = $this->mock(LinnworksOAuthService::class);
    $this->apiService = new LinnworksApiService($this->oauthService);
});

test('can check and update processed orders with mocked API response', function () {
    // Mock session token
    Cache::put('linnworks.session_token', 'test-session-token');
    Cache::put('linnworks.server', 'https://test-server.linnworks.net');

    // Mock the API response
    Http::fake([
        '*/api/Orders/GetOrdersById' => Http::response([
            [
                'OrderId' => 'uuid-1',
                'Processed' => true,
                'nStatus' => 10
            ],
            [
                'OrderId' => 'uuid-2', 
                'Processed' => false,
                'nStatus' => 5
            ]
        ], 200)
    ]);

    $result = $this->apiService->checkAndUpdateProcessedOrders();

    expect($result)->toBeTrue();

    // Verify database updates
    $order1 = Order::where('linnworks_order_id', 'uuid-1')->first();
    $order2 = Order::where('linnworks_order_id', 'uuid-2')->first();

    expect($order1->is_processed)->toBeTrue(); // Changed from false to true
    expect($order2->is_processed)->toBeFalse(); // Changed from true to false

    // Verify API was called correctly
    Http::assertSent(function ($request) {
        return $request->url() === 'https://test-server.linnworks.net/api/Orders/GetOrdersById' &&
               $request->method() === 'POST' &&
               $request->hasHeader('Authorization', 'test-session-token') &&
               isset($request->data()['pkOrderIds']) &&
               in_array('uuid-1', $request->data()['pkOrderIds']) &&
               in_array('uuid-2', $request->data()['pkOrderIds']);
    });
});

test('handles API authentication failure gracefully', function () {
    // Mock failed authentication
    Cache::forget('linnworks.session_token');
    Cache::forget('linnworks.server');
    
    Http::fake([
        '*/api/Auth/AuthorizeByApplication' => Http::response([], 401)
    ]);

    $result = $this->apiService->checkAndUpdateProcessedOrders();

    expect($result)->toBeFalse();
});

test('handles empty orders gracefully', function () {
    // Remove all orders
    Order::query()->delete();

    $result = $this->apiService->checkAndUpdateProcessedOrders();

    expect($result)->toBeTrue();
});

test('processes orders in batches', function () {
    // Create many orders to test batching
    $manyOrders = [];
    for ($i = 1; $i <= 75; $i++) {
        $order = Order::factory()->create([
            'linnworks_order_id' => "uuid-{$i}",
            'is_processed' => false,
            'received_date' => now()->subDays(5),
        ]);
        $manyOrders[] = $order;
    }

    Cache::put('linnworks.session_token', 'test-session-token');
    Cache::put('linnworks.server', 'https://test-server.linnworks.net');

    // Mock API response for batches
    Http::fake([
        '*/api/Orders/GetOrdersById' => Http::response(function ($request) {
            $orderIds = $request->data()['pkOrderIds'];
            $response = [];
            foreach ($orderIds as $orderId) {
                $response[] = [
                    'OrderId' => $orderId,
                    'Processed' => true,
                ];
            }
            return $response;
        }, 200)
    ]);

    $result = $this->apiService->checkAndUpdateProcessedOrders();

    expect($result)->toBeTrue();

    // Should have made at least 2 API calls for 75 orders (batch size 50)
    Http::assertSentCount(2);
});

test('handles malformed API response', function () {
    Cache::put('linnworks.session_token', 'test-session-token');
    Cache::put('linnworks.server', 'https://test-server.linnworks.net');

    // Mock malformed response
    Http::fake([
        '*/api/Orders/GetOrdersById' => Http::response('invalid json', 200)
    ]);

    $result = $this->apiService->checkAndUpdateProcessedOrders();

    expect($result)->toBeFalse();
});