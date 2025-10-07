<?php

use App\Services\LinnworksApiService;
use Carbon\Carbon;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    // Mock Linnworks API configuration
    Config::set('linnworks.application_id', 'test-app-id');
    Config::set('linnworks.application_secret', 'test-app-secret');
    Config::set('linnworks.token', 'test-token');
    Config::set('linnworks.base_url', 'https://api.linnworks.net');
    
    // Clear any cached session tokens
    Cache::forget('linnworks.session_token');
    Cache::forget('linnworks.server');
});

describe('LinnworksApiService Historical Orders', function () {
    
    it('can fetch processed orders with valid response', function () {
        // Mock authentication response
        Http::fake([
            'api.linnworks.net/api/Auth/AuthorizeByApplication' => Http::response([
                'Server' => 'https://eu-ext.linnworks.net/',
                'Token' => 'mock-session-token'
            ], 200),
            'eu-ext.linnworks.net/api/ProcessedOrders/SearchProcessedOrders' => Http::response([
                'Data' => [
                    [
                        'pkOrderID' => 'order-123',
                        'nOrderId' => 12345,
                        'dReceivedDate' => '2024-01-15T10:30:00Z',
                        'dProcessedOn' => '2024-01-15T12:00:00Z',
                        'Source' => 'Amazon',
                        'SubSource' => 'UK',
                        'cCurrency' => 'GBP',
                        'fTotalCharge' => 25.99,
                        'fPostageCost' => 3.99,
                        'fTax' => 4.33,
                        'ProfitMargin' => 8.50,
                        'nStatus' => 1,
                        'fkOrderLocationID' => 'loc-456',
                        'GeneralInfo' => 'Test order',
                        'Items' => [
                            [
                                'ItemId' => 'item-789',
                                'SKU' => 'TEST-SKU-001',
                                'ItemTitle' => 'Test Product',
                                'Quantity' => 2,
                                'UnitCost' => 5.00,
                                'PricePerUnit' => 10.99,
                                'LineTotal' => 21.98,
                                'CategoryName' => 'Electronics'
                            ]
                        ]
                    ]
                ],
                'TotalResults' => 1
            ], 200)
        ]);

        $service = app(LinnworksApiService::class);
        $from = Carbon::parse('2024-01-01');
        $to = Carbon::parse('2024-01-31');
        
        $result = $service->getProcessedOrders($from, $to, 1, 50);
        
        expect($result)->toBeArray()
            ->and($result['orders'])->toHaveCount(1)
            ->and($result['totalResults'])->toBe(1)
            ->and($result['hasMorePages'])->toBeFalse()
            ->and($result['currentPage'])->toBe(1);
            
        $order = $result['orders']->first();
        expect($order)->toBeArray()
            ->and($order['order_id'])->toBe('order-123')
            ->and($order['order_number'])->toBe(12345)
            ->and($order['channel_name'])->toBe('Amazon')
            ->and($order['total_charge'])->toBe(25.99)
            ->and($order['items'])->toHaveCount(1);
            
        $item = $order['items'][0];
        expect($item)->toBeArray()
            ->and($item['sku'])->toBe('TEST-SKU-001')
            ->and($item['quantity'])->toBe(2)
            ->and($item['price_per_unit'])->toBe(10.99);
    });

    it('handles pagination correctly with multiple pages', function () {
        Http::fake([
            'api.linnworks.net/api/Auth/AuthorizeByApplication' => Http::response([
                'Server' => 'https://eu-ext.linnworks.net/',
                'Token' => 'mock-session-token'
            ], 200),
            'eu-ext.linnworks.net/api/ProcessedOrders/SearchProcessedOrders' => Http::response([
                'Data' => array_fill(0, 200, [ // Full page of 200 orders
                    'pkOrderID' => 'order-123',
                    'nOrderId' => 12345,
                    'Source' => 'Amazon',
                    'Items' => []
                ]),
                'TotalResults' => 350 // More than one page
            ], 200)
        ]);

        $service = app(LinnworksApiService::class);
        $result = $service->getProcessedOrders(null, null, 1, 200);
        
        expect($result['hasMorePages'])->toBeTrue()
            ->and($result['orders'])->toHaveCount(200)
            ->and($result['totalResults'])->toBe(350);
    });

    it('handles empty response gracefully', function () {
        Http::fake([
            'api.linnworks.net/api/Auth/AuthorizeByApplication' => Http::response([
                'Server' => 'https://eu-ext.linnworks.net/',
                'Token' => 'mock-session-token'
            ], 200),
            'eu-ext.linnworks.net/api/ProcessedOrders/SearchProcessedOrders' => Http::response([
                'Data' => [],
                'TotalResults' => 0
            ], 200)
        ]);

        $service = app(LinnworksApiService::class);
        $result = $service->getProcessedOrders();
        
        expect($result['orders'])->toBeEmpty()
            ->and($result['totalResults'])->toBe(0)
            ->and($result['hasMorePages'])->toBeFalse();
    });

    it('handles API errors gracefully', function () {
        Http::fake([
            'api.linnworks.net/api/Auth/AuthorizeByApplication' => Http::response([
                'Server' => 'https://eu-ext.linnworks.net/',
                'Token' => 'mock-session-token'
            ], 200),
            'eu-ext.linnworks.net/api/ProcessedOrders/SearchProcessedOrders' => Http::response([
                'error' => 'Unauthorized'
            ], 401)
        ]);

        $service = app(LinnworksApiService::class);
        $result = $service->getProcessedOrders();
        
        expect($result['orders'])->toBeEmpty()
            ->and($result['totalResults'])->toBe(0)
            ->and($result['hasMorePages'])->toBeFalse();
    });

    it('enforces maximum entries per page limit', function () {
        Http::fake([
            'api.linnworks.net/api/Auth/AuthorizeByApplication' => Http::response([
                'Server' => 'https://eu-ext.linnworks.net/',
                'Token' => 'mock-session-token'
            ], 200),
            'eu-ext.linnworks.net/api/ProcessedOrders/SearchProcessedOrders' => Http::response([
                'Data' => [],
                'TotalResults' => 0
            ], 200)
        ]);

        $service = app(LinnworksApiService::class);
        
        // Test with value over 200 limit
        $result = $service->getProcessedOrders(null, null, 1, 500);
        
        expect($result['entriesPerPage'])->toBe(200); // Should be capped at 200
    });

    it('sanitizes customer data correctly', function () {
        Http::fake([
            'api.linnworks.net/api/Auth/AuthorizeByApplication' => Http::response([
                'Server' => 'https://eu-ext.linnworks.net/',
                'Token' => 'mock-session-token'
            ], 200),
            'eu-ext.linnworks.net/api/ProcessedOrders/SearchProcessedOrders' => Http::response([
                'Data' => [
                    [
                        'pkOrderID' => 'order-123',
                        'nOrderId' => 12345,
                        'Source' => 'Amazon',
                        'Items' => [],
                        // Customer data that should be excluded
                        'cFullName' => 'John Doe',
                        'cEmailAddress' => 'john@example.com',
                        'cPostCode' => 'SW1A 1AA',
                        'cAddress1' => '123 Main Street'
                    ]
                ],
                'TotalResults' => 1
            ], 200)
        ]);

        $service = app(LinnworksApiService::class);
        $result = $service->getProcessedOrders();
        
        $order = $result['orders']->first();
        
        // Ensure customer data is not present
        expect($order)->not->toHaveKey('cFullName')
            ->and($order)->not->toHaveKey('cEmailAddress')
            ->and($order)->not->toHaveKey('cPostCode')
            ->and($order)->not->toHaveKey('cAddress1')
            // But business data should be present
            ->and($order)->toHaveKey('order_id')
            ->and($order)->toHaveKey('channel_name')
            ->and($order)->toHaveKey('total_charge');
    });

    it('handles missing optional fields gracefully', function () {
        Http::fake([
            'api.linnworks.net/api/Auth/AuthorizeByApplication' => Http::response([
                'Server' => 'https://eu-ext.linnworks.net/',
                'Token' => 'mock-session-token'
            ], 200),
            'eu-ext.linnworks.net/api/ProcessedOrders/SearchProcessedOrders' => Http::response([
                'Data' => [
                    [
                        'pkOrderID' => 'order-123',
                        // Missing many optional fields
                        'Items' => [
                            [
                                'SKU' => 'TEST-SKU'
                                // Missing other item fields
                            ]
                        ]
                    ]
                ],
                'TotalResults' => 1
            ], 200)
        ]);

        $service = app(LinnworksApiService::class);
        $result = $service->getProcessedOrders();
        
        $order = $result['orders']->first();
        expect($order['order_id'])->toBe('order-123')
            ->and($order['order_number'])->toBeNull()
            ->and($order['channel_name'])->toBe('Unknown')
            ->and($order['total_charge'])->toBe(0.0);
            
        $item = $order['items'][0];
        expect($item['sku'])->toBe('TEST-SKU')
            ->and($item['quantity'])->toBe(0)
            ->and($item['price_per_unit'])->toBe(0.0);
    });

    it('returns empty result when API is not configured', function () {
        Config::set('linnworks.application_id', null);
        
        $service = app(LinnworksApiService::class);
        $result = $service->getProcessedOrders();
        
        expect($result['orders'])->toBeEmpty()
            ->and($result['totalResults'])->toBe(0)
            ->and($result['hasMorePages'])->toBeFalse();
    });

    it('can fetch all processed orders with pagination', function () {
        $callCount = 0;
        
        Http::fake([
            'api.linnworks.net/api/Auth/AuthorizeByApplication' => Http::response([
                'Server' => 'https://eu-ext.linnworks.net/',
                'Token' => 'mock-session-token'
            ], 200),
            'eu-ext.linnworks.net/api/ProcessedOrders/SearchProcessedOrders' => function () use (&$callCount) {
                $callCount++;
                
                if ($callCount === 1) {
                    // First page - full page
                    return Http::response([
                        'Data' => array_fill(0, 200, ['pkOrderID' => "order-{$callCount}", 'Items' => []]),
                        'TotalResults' => 250
                    ], 200);
                } else {
                    // Second page - partial page
                    return Http::response([
                        'Data' => array_fill(0, 50, ['pkOrderID' => "order-{$callCount}", 'Items' => []]),
                        'TotalResults' => 250
                    ], 200);
                }
            }
        ]);

        $service = app(LinnworksApiService::class);
        $allOrders = $service->getAllProcessedOrders();
        
        expect($allOrders)->toHaveCount(250)
            ->and($callCount)->toBe(2); // Should make 2 API calls
    });
});