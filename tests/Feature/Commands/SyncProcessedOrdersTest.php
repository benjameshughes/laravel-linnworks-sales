<?php

use App\Console\Commands\SyncProcessedOrders;
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

describe('SyncProcessedOrders Command', function () {
    
    it('can sync new processed orders successfully', function () {
        Http::fake([
            'api.linnworks.net/api/Auth/AuthorizeByApplication' => Http::response([
                'Server' => 'https://eu-ext.linnworks.net/',
                'Token' => 'mock-session-token'
            ], 200),
            'eu-ext.linnworks.net/api/ProcessedOrders/SearchProcessedOrders' => Http::response([
                'Data' => [
                    [
                        'pkOrderID' => 'new-processed-order',
                        'nOrderId' => 55555,
                        'dReceivedDate' => '2025-07-01T10:00:00',
                        'dProcessedOn' => '2025-07-02T14:00:00',
                        'Source' => 'Amazon',
                        'fTotalCharge' => 150.75,
                        'Items' => [
                            [
                                'SKU' => 'PROC-ITEM',
                                'ItemTitle' => 'Processed Item',
                                'Quantity' => 2,
                                'PricePerUnit' => 75.37,
                                'LineTotal' => 150.75
                            ]
                        ]
                    ]
                ],
                'TotalResults' => 1
            ], 200)
        ]);

        $this->artisan('sync:processed-orders --days=7')
            ->expectsConfirmation('Do you want to continue?', 'yes')
            ->assertExitCode(0);

        // Verify processed order was created
        $this->assertDatabaseHas('orders', [
            'order_id' => 'new-processed-order',
            'order_number' => 55555,
            'is_open' => false,
            'status' => 'processed'
        ]);

        // Verify order item was created
        $order = Order::where('order_id', 'new-processed-order')->first();
        expect($order)->not->toBeNull();
        
        $this->assertDatabaseHas('order_items', [
            'order_id' => $order->id,
            'sku' => 'PROC-ITEM',
            'quantity' => 2
        ]);
    });

    it('can update existing open orders to processed status', function () {
        // Create an existing open order
        $openOrder = Order::factory()->create([
            'order_id' => 'existing-open-order',
            'order_number' => 12345,
            'is_open' => true,
            'status' => 'pending'
        ]);

        Http::fake([
            'api.linnworks.net/api/Auth/AuthorizeByApplication' => Http::response([
                'Server' => 'https://eu-ext.linnworks.net/',
                'Token' => 'mock-session-token'
            ], 200),
            'eu-ext.linnworks.net/api/ProcessedOrders/SearchProcessedOrders' => Http::response([
                'Data' => [
                    [
                        'pkOrderID' => 'existing-open-order',
                        'nOrderId' => 12345,
                        'dProcessedOn' => '2025-07-02T14:00:00',
                        'Source' => 'eBay',
                        'Items' => []
                    ]
                ],
                'TotalResults' => 1
            ], 200)
        ]);

        $this->artisan('sync:processed-orders --days=7 --update-existing')
            ->expectsConfirmation('Do you want to continue?', 'yes')
            ->assertExitCode(0);

        // Verify order was updated to processed
        $openOrder->refresh();
        expect($openOrder->is_open)->toBeFalse();
        expect($openOrder->status)->toBe('processed');
        expect($openOrder->processed_date)->not->toBeNull();
    });

    it('skips orders that are already processed', function () {
        // Create an existing processed order
        Order::factory()->create([
            'order_id' => 'already-processed',
            'order_number' => 99999,
            'is_open' => false,
            'status' => 'processed'
        ]);

        Http::fake([
            'api.linnworks.net/api/Auth/AuthorizeByApplication' => Http::response([
                'Server' => 'https://eu-ext.linnworks.net/',
                'Token' => 'mock-session-token'
            ], 200),
            'eu-ext.linnworks.net/api/ProcessedOrders/SearchProcessedOrders' => Http::response([
                'Data' => [
                    [
                        'pkOrderID' => 'already-processed',
                        'nOrderId' => 99999,
                        'Source' => 'Website',
                        'Items' => []
                    ]
                ],
                'TotalResults' => 1
            ], 200)
        ]);

        $this->artisan('sync:processed-orders --days=7')
            ->expectsConfirmation('Do you want to continue?', 'yes')
            ->assertExitCode(0);

        // Should only have one order (not duplicated)
        expect(Order::where('order_number', 99999)->count())->toBe(1);
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
                        'nOrderId' => 77777,
                        'Items' => []
                    ]
                ],
                'TotalResults' => 1
            ], 200)
        ]);

        $this->artisan('sync:processed-orders --days=7 --dry-run')
            ->expectsConfirmation('Do you want to continue?', 'yes')
            ->expectsOutput('This was a dry run - no data was actually modified.')
            ->assertExitCode(0);

        // Should not create any orders in dry run mode
        $this->assertDatabaseMissing('orders', [
            'order_id' => 'dry-run-order'
        ]);
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

        $this->artisan('sync:processed-orders --days=7')
            ->expectsConfirmation('Do you want to continue?', 'yes')
            ->expectsOutput('No processed orders found in the specified date range.')
            ->assertExitCode(0);
    });

    it('enforces maximum batch size', function () {
        Http::fake([
            'api.linnworks.net/api/Auth/AuthorizeByApplication' => Http::response([
                'Server' => 'https://eu-ext.linnworks.net/',
                'Token' => 'mock-session-token'
            ], 200),
            'eu-ext.linnworks.net/api/ProcessedOrders/SearchProcessedOrders' => function ($request) {
                $body = json_decode($request->body(), true);
                
                // Should be capped at 200 even though we requested 500
                expect($body['request']['ResultsPerPage'])->toBe(200);
                
                return Http::response(['Data' => [], 'TotalResults' => 0], 200);
            }
        ]);

        $this->artisan('sync:processed-orders --batch-size=500 --dry-run')
            ->expectsConfirmation('Do you want to continue?', 'yes')
            ->assertExitCode(0);
    });

    it('handles API connection failure gracefully', function () {
        Config::set('linnworks.application_id', null); // Disable API
        
        $this->artisan('sync:processed-orders --days=7')
            ->assertExitCode(1)
            ->expectsOutput('Linnworks API is not configured. Please check your credentials.');
    });

    it('handles date range options correctly', function () {
        Http::fake([
            'api.linnworks.net/api/Auth/AuthorizeByApplication' => Http::response([
                'Server' => 'https://eu-ext.linnworks.net/',
                'Token' => 'mock-session-token'
            ], 200),
            'eu-ext.linnworks.net/api/ProcessedOrders/SearchProcessedOrders' => function ($request) {
                $body = json_decode($request->body(), true);
                
                // Verify the dates in the request match our command options
                expect($body['request']['FromDate'])->toContain('2025-07-01');
                expect($body['request']['ToDate'])->toContain('2025-07-05');
                
                return Http::response(['Data' => [], 'TotalResults' => 0], 200);
            }
        ]);

        $this->artisan('sync:processed-orders --from=2025-07-01 --to=2025-07-05 --dry-run')
            ->expectsConfirmation('Do you want to continue?', 'yes')
            ->assertExitCode(0);
    });

    it('can handle orders without items gracefully', function () {
        Http::fake([
            'api.linnworks.net/api/Auth/AuthorizeByApplication' => Http::response([
                'Server' => 'https://eu-ext.linnworks.net/',
                'Token' => 'mock-session-token'
            ], 200),
            'eu-ext.linnworks.net/api/ProcessedOrders/SearchProcessedOrders' => Http::response([
                'Data' => [
                    [
                        'pkOrderID' => 'order-no-items',
                        'nOrderId' => 88888,
                        'Source' => 'Manual',
                        // No Items array
                    ]
                ],
                'TotalResults' => 1
            ], 200)
        ]);

        $this->artisan('sync:processed-orders --days=7')
            ->expectsConfirmation('Do you want to continue?', 'yes')
            ->assertExitCode(0);

        // Order should be created without items
        $this->assertDatabaseHas('orders', [
            'order_id' => 'order-no-items',
            'is_open' => false,
            'status' => 'processed'
        ]);

        $order = Order::where('order_id', 'order-no-items')->first();
        expect($order->orderItems)->toHaveCount(0);
    });
});