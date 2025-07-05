<?php

namespace Tests\Feature\Commands;

use App\Console\Commands\SyncOpenOrders;
use App\DataTransferObjects\LinnworksOrder;
use App\Models\Order;
use App\Models\SyncLog;
use App\Services\LinnworksApiService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class SimpleSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_simple_order_creation_directly()
    {
        // Silence logs
        Log::shouldReceive('info')->zeroOrMoreTimes();
        Log::shouldReceive('error')->zeroOrMoreTimes();
        Log::shouldReceive('warning')->zeroOrMoreTimes();

        // Create a sync log like the command does
        $syncLog = SyncLog::startSync(SyncLog::TYPE_OPEN_ORDERS, [
            'force' => false,
            'started_by' => 'test',
        ]);

        // Create mock orders
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

        $openOrders = collect([$order1, $order2]);

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $failed = 0;

        // Simulate the sync command logic
        foreach ($openOrders as $linnworksOrder) {
            try {
                // Check for existing order
                $existingOrder = Order::where('linnworks_order_id', $linnworksOrder->orderId)->first();

                if (!$existingOrder) {
                    // Create new order
                    $orderModel = Order::fromLinnworksOrder($linnworksOrder);
                    $orderModel->save();
                    $created++;
                    
                    echo "Created order: {$orderModel->order_number}\n";
                }
            } catch (\Exception $e) {
                $failed++;
                echo "Failed to sync order {$linnworksOrder->orderNumber}: {$e->getMessage()}\n";
            }
        }

        // Complete sync log
        $syncLog->complete($openOrders->count(), $created, $updated, $skipped, $failed);

        // Verify results
        $this->assertEquals(2, $created);
        $this->assertEquals(0, $failed);
        $this->assertDatabaseCount('orders', 2);
        $this->assertDatabaseCount('sync_logs', 1);

        $savedSyncLog = SyncLog::first();
        $this->assertEquals(SyncLog::STATUS_COMPLETED, $savedSyncLog->status);
        $this->assertEquals(2, $savedSyncLog->total_created);
    }
}