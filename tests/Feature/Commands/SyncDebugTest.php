<?php

namespace Tests\Feature\Commands;

use App\DataTransferObjects\LinnworksOrder;
use App\Models\Order;
use App\Models\SyncLog;
use App\Services\LinnworksApiService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class SyncDebugTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_command_with_debug_output()
    {
        // Silence logs except errors
        Log::shouldReceive('info')->zeroOrMoreTimes();
        Log::shouldReceive('warning')->zeroOrMoreTimes();
        Log::shouldReceive('error')->andReturnUsing(function($message, $context = []) {
            echo "ERROR LOG: $message\n";
            if ($context) {
                echo "CONTEXT: " . json_encode($context) . "\n";
            }
        });

        // Mock the API service
        $mockApiService = $this->createMock(LinnworksApiService::class);
        $this->app->bind(LinnworksApiService::class, function () use ($mockApiService) {
            return $mockApiService;
        });

        $mockApiService
            ->expects($this->once())
            ->method('isConfigured')
            ->willReturn(true);

        // Create simple mock orders
        $order1 = new LinnworksOrder(
            orderId: 'test-order-1',
            orderNumber: 11111,
            receivedDate: Carbon::now()->subDays(2),
            processedDate: null,
            orderSource: 'Amazon',
            subsource: null,
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
            subsource: null,
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
            ->willReturn($mockOrders);

        echo "Running sync command...\n";
        
        $exitCode = Artisan::call('sync:open-orders', ['--debug' => true]);
        
        echo "Command exit code: $exitCode\n";
        echo "Orders in database: " . Order::count() . "\n";
        echo "SyncLogs in database: " . SyncLog::count() . "\n";
        
        $orders = Order::all();
        foreach ($orders as $order) {
            echo "Order: ID={$order->id}, LinnworksID={$order->linnworks_order_id}, Number={$order->order_number}\n";
        }
        
        $syncLogs = SyncLog::all();
        foreach ($syncLogs as $log) {
            echo "SyncLog: Status={$log->status}, Created={$log->total_created}, Updated={$log->total_updated}, Failed={$log->total_failed}, Error={$log->error_message}\n";
        }

        $this->assertEquals(0, $exitCode);
        $this->assertDatabaseCount('orders', 2);
    }
}