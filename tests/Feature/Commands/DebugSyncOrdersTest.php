<?php

namespace Tests\Feature\Commands;

use App\Console\Commands\SyncOpenOrders;
use App\DataTransferObjects\LinnworksOrder;
use App\DataTransferObjects\LinnworksOrderItem;
use App\Models\Order;
use App\Models\SyncLog;
use App\Services\LinnworksApiService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class DebugSyncOrdersTest extends TestCase
{
    use RefreshDatabase;

    public function test_debug_order_creation()
    {
        // Create mock API service
        $mockApiService = $this->createMock(LinnworksApiService::class);
        $this->app->bind(LinnworksApiService::class, function () use ($mockApiService) {
            return $mockApiService;
        });
        
        // Silent logging
        Log::shouldReceive('info')->zeroOrMoreTimes();
        Log::shouldReceive('error')->zeroOrMoreTimes();
        Log::shouldReceive('warning')->zeroOrMoreTimes();

        $mockApiService
            ->expects($this->once())
            ->method('isConfigured')
            ->willReturn(true);
        
        // Add debug to see if this is even called
        $mockApiService
            ->method('isConfigured')
            ->willReturnCallback(function() {
                echo "Mock isConfigured called!\n";
                return true;
            });

        // Create two very simple mock orders
        $order1 = new LinnworksOrder(
            orderId: 'test-order-1',
            orderNumber: 11111,
            receivedDate: Carbon::now()->subDays(2),
            processedDate: null,
            orderSource: 'Amazon',
            subsource: 'Amazon UK',
            currency: 'GBP',
            totalCharge: 100.00,
            postageCost: 5.00,
            tax: 20.00,
            profitMargin: 30.00,
            orderStatus: 0,
            locationId: 'location-1',
            items: collect([])
        );

        $order2 = new LinnworksOrder(
            orderId: 'test-order-2',
            orderNumber: 22222,
            receivedDate: Carbon::now()->subDays(1),
            processedDate: null,
            orderSource: 'eBay',
            subsource: 'eBay UK',
            currency: 'GBP',
            totalCharge: 50.00,
            postageCost: 3.00,
            tax: 10.00,
            profitMargin: 15.00,
            orderStatus: 0,
            locationId: 'location-2',
            items: collect([])
        );

        $mockOrders = collect([$order1, $order2]);

        $mockApiService
            ->expects($this->once())
            ->method('getRecentOpenOrders')
            ->willReturnCallback(function() use ($mockOrders) {
                echo "Mock getRecentOpenOrders called with " . $mockOrders->count() . " orders!\n";
                return $mockOrders;
            });

        // Run the command using Artisan::call instead
        $exitCode = \Illuminate\Support\Facades\Artisan::call('sync:open-orders', ['--debug' => true]);
        
        // Debug output
        echo "\n=== Debug Info ===\n";
        echo "Orders in database: " . Order::count() . "\n";
        echo "SyncLogs in database: " . SyncLog::count() . "\n";
        
        $orders = Order::all();
        foreach ($orders as $order) {
            echo "Order: ID={$order->id}, LinnworksID={$order->linnworks_order_id}, Number={$order->order_number}\n";
        }
        
        $syncLogs = SyncLog::all();
        foreach ($syncLogs as $log) {
            echo "SyncLog: Status={$log->status}, Created={$log->total_created}, Updated={$log->total_updated}, Failed={$log->total_failed}\n";
        }

        $this->assertEquals(0, $exitCode);
        
        // These should pass if everything works correctly
        $this->assertDatabaseCount('orders', 2);
        $this->assertDatabaseHas('orders', ['linnworks_order_id' => 'test-order-1']);
        $this->assertDatabaseHas('orders', ['linnworks_order_id' => 'test-order-2']);
    }
}