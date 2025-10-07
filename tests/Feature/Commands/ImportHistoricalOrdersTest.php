<?php

use App\Console\Commands\ImportHistoricalOrders;
use App\Services\LinnworksApiService;
use App\Models\Order;
use App\Models\OrderItem;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Config;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Mock Linnworks API configuration
    Config::set('linnworks.application_id', 'test-app-id');
    Config::set('linnworks.application_secret', 'test-app-secret');
    Config::set('linnworks.token', 'test-token');
});

describe('ImportHistoricalOrders Command', function () {
    
    it('can import historical orders successfully', function () {
        Http::fake([
            'api.linnworks.net/api/Auth/AuthorizeByApplication' => Http::response([
                'Server' => 'https://eu-ext.linnworks.net/',
                'Token' => 'mock-session-token'
            ], 200),
            'eu-ext.linnworks.net/api/ProcessedOrders/SearchProcessedOrders' => Http::response([
                'Data' => [
                    [
                        'pkOrderID' => 'test-order-123',
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

        $this->artisan('import:historical-orders', ['--days' => 7])
            ->expectsConfirmation('Do you want to continue?', 'yes')
            ->assertExitCode(0);

        // Verify order was created
        $this->assertDatabaseHas('orders', [
            'order_id' => 'test-order-123',
            'order_number' => 12345,
            'channel_name' => 'Amazon',
            'total_charge' => 25.99
        ]);

        // Verify order item was created
        $order = Order::where('order_id', 'test-order-123')->first();
        expect($order)->not->toBeNull();
        
        $this->assertDatabaseHas('order_items', [
            'order_id' => $order->id,
            'sku' => 'TEST-SKU-001',
            'quantity' => 2,
            'price_per_unit' => 10.99
        ]);
    });

    it('skips existing orders correctly', function () {
        // Create an existing order
        $existingOrder = Order::factory()->create([
            'order_id' => 'existing-order-123',
            'order_number' => 12345
        ]);

        Http::fake([
            'api.linnworks.net/api/Auth/AuthorizeByApplication' => Http::response([
                'Server' => 'https://eu-ext.linnworks.net/',
                'Token' => 'mock-session-token'
            ], 200),
            'eu-ext.linnworks.net/api/ProcessedOrders/SearchProcessedOrders' => Http::response([
                'Data' => [
                    [
                        'pkOrderID' => 'existing-order-123', // Same as existing
                        'nOrderId' => 12345,
                        'Items' => []
                    ]
                ],
                'TotalResults' => 1
            ], 200)
        ]);

        $this->artisan('import:historical-orders', ['--days' => 7])
            ->expectsConfirmation('Do you want to continue?', 'yes')
            ->assertExitCode(0);

        // Should still have only one order
        expect(Order::where('order_id', 'existing-order-123')->count())->toBe(1);
    });

    it('handles dry run mode correctly', function () {
        Http::fake([
            'api.linnworks.net/api/Auth/AuthorizeByApplication' => Http::response([
                'Server' => 'https://eu-ext.linnworks.net/',
                'Token' => 'mock-session-token'
            ], 200),
            'eu-ext.linnworks.net/api/ProcessedOrders/SearchProcessedOrders' => Http::response([
                'Data' => [
                    [
                        'pkOrderID' => 'dry-run-order',
                        'nOrderId' => 12345,
                        'Items' => []
                    ]
                ],
                'TotalResults' => 1
            ], 200)
        ]);

        $this->artisan('import:historical-orders', ['--dry-run', '--days' => 7])
            ->expectsConfirmation('Do you want to continue?', 'yes')
            ->assertExitCode(0);

        // Should not create any orders in dry run mode
        $this->assertDatabaseMissing('orders', [
            'order_id' => 'dry-run-order'
        ]);
    });

    it('handles date range options correctly', function () {
        Http::fake([
            'api.linnworks.net/api/Auth/AuthorizeByApplication' => Http::response([
                'Server' => 'https://eu-ext.linnworks.net/',
                'Token' => 'mock-session-token'
            ], 200),
            'eu-ext.linnworks.net/api/ProcessedOrders/SearchProcessedOrdersPaged' => function ($request) {
                $body = json_decode($request->body(), true);
                
                // Verify the dates in the request match our command options
                expect($body['fromDate'])->toContain('2024-01-01')
                    ->and($body['toDate'])->toContain('2024-01-31');
                
                return Http::response(['Data' => [], 'TotalResults' => 0], 200);
            }
        ]);

        $this->artisan('import:historical-orders', [
            '--from' => '2024-01-01',
            '--to' => '2024-01-31',
            '--dry-run'
        ])
            ->expectsConfirmation('Do you want to continue?', 'yes')
            ->assertExitCode(0);
    });

    it('handles API connection failure gracefully', function () {
        Config::set('linnworks.application_id', null); // Disable API
        
        $this->artisan('import:historical-orders', ['--days' => 7])
            ->assertExitCode(1)
            ->expectsOutput('Linnworks API is not configured. Please check your credentials.');
    });

    it('handles API authentication failure', function () {
        Http::fake([
            'api.linnworks.net/api/Auth/AuthorizeByApplication' => Http::response([
                'error' => 'Invalid credentials'
            ], 401)
        ]);

        $this->artisan('import:historical-orders', ['--days' => 7])
            ->expectsConfirmation('Do you want to continue?', 'yes')
            ->assertExitCode(1);
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

        $this->artisan('import:historical-orders', ['--days' => 7])
            ->expectsConfirmation('Do you want to continue?', 'yes')
            ->assertExitCode(0);
    });

    it('enforces maximum batch size', function () {
        Http::fake([
            'api.linnworks.net/api/Auth/AuthorizeByApplication' => Http::response([
                'Server' => 'https://eu-ext.linnworks.net/',
                'Token' => 'mock-session-token'
            ], 200),
            'eu-ext.linnworks.net/api/ProcessedOrders/SearchProcessedOrdersPaged' => function ($request) {
                $body = json_decode($request->body(), true);
                
                // Should be capped at 200 even though we requested 500
                expect($body['entriesPerPage'])->toBe(200);
                
                return Http::response(['Data' => [], 'TotalResults' => 0], 200);
            }
        ]);

        $this->artisan('import:historical-orders', [
            '--batch-size' => 500, // Over the limit
            '--dry-run'
        ])
            ->expectsConfirmation('Do you want to continue?', 'yes')
            ->assertExitCode(0);
    });

    it('handles user cancellation', function () {
        Http::fake();

        $this->artisan('import:historical-orders', ['--days' => 7])
            ->expectsConfirmation('Do you want to continue?', 'no')
            ->assertExitCode(0)
            ->expectsOutput('Import cancelled.');
    });

    it('handles missing order items gracefully', function () {
        Http::fake([
            'api.linnworks.net/api/Auth/AuthorizeByApplication' => Http::response([
                'Server' => 'https://eu-ext.linnworks.net/',
                'Token' => 'mock-session-token'
            ], 200),
            'eu-ext.linnworks.net/api/ProcessedOrders/SearchProcessedOrders' => Http::response([
                'Data' => [
                    [
                        'pkOrderID' => 'order-no-items',
                        'nOrderId' => 12345,
                        // No Items array
                    ]
                ],
                'TotalResults' => 1
            ], 200)
        ]);

        $this->artisan('import:historical-orders', ['--days' => 7])
            ->expectsConfirmation('Do you want to continue?', 'yes')
            ->assertExitCode(0);

        // Order should be created without items
        $this->assertDatabaseHas('orders', [
            'order_id' => 'order-no-items'
        ]);

        $order = Order::where('order_id', 'order-no-items')->first();
        expect($order->orderItems)->toHaveCount(0);
    });

    it('handles database transaction failures', function () {
        Http::fake([
            'api.linnworks.net/api/Auth/AuthorizeByApplication' => Http::response([
                'Server' => 'https://eu-ext.linnworks.net/',
                'Token' => 'mock-session-token'
            ], 200),
            'eu-ext.linnworks.net/api/ProcessedOrders/SearchProcessedOrders' => Http::response([
                'Data' => [
                    [
                        'pkOrderID' => null, // Invalid data that should cause DB error
                        'nOrderId' => 12345,
                        'Items' => []
                    ]
                ],
                'TotalResults' => 1
            ], 200)
        ]);

        $this->artisan('import:historical-orders', ['--days' => 7])
            ->expectsConfirmation('Do you want to continue?', 'yes')
            ->assertExitCode(0); // Should complete despite errors

        // Should not create the invalid order
        $this->assertDatabaseMissing('orders', [
            'order_number' => 12345
        ]);
    });
});