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
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class SyncOpenOrdersTest extends TestCase
{
    use RefreshDatabase;

    private LinnworksApiService $mockApiService;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Mock the LinnworksApiService
        $this->mockApiService = $this->createMock(LinnworksApiService::class);
        $this->app->bind(LinnworksApiService::class, function () {
            return $this->mockApiService;
        });
        
        // Silence logs during testing
        Log::shouldReceive('info')->zeroOrMoreTimes();
        Log::shouldReceive('error')->zeroOrMoreTimes();
        Log::shouldReceive('warning')->zeroOrMoreTimes();
    }

    public function test_sync_command_fails_when_api_not_configured()
    {
        $this->mockApiService
            ->expects($this->once())
            ->method('isConfigured')
            ->willReturn(false);

        $exitCode = Artisan::call('sync:open-orders');
        $this->assertEquals(1, $exitCode);

        // Verify sync log was created and marked as failed
        $syncLog = SyncLog::first();
        $this->assertNotNull($syncLog);
        $this->assertEquals(SyncLog::TYPE_OPEN_ORDERS, $syncLog->sync_type);
        $this->assertEquals(SyncLog::STATUS_FAILED, $syncLog->status);
        $this->assertEquals('Linnworks API not configured', $syncLog->error_message);
    }

    public function test_sync_command_skips_when_recent_sync_exists()
    {
        // Create a very recent successful sync (within last 2 minutes)
        SyncLog::factory()
            ->openOrders()
            ->successful()
            ->create([
                'completed_at' => now()->subMinutes(1) // Within 5 minute window
            ]);

        $this->mockApiService
            ->expects($this->once())
            ->method('isConfigured')
            ->willReturn(true);

        $exitCode = Artisan::call('sync:open-orders');
        $this->assertEquals(0, $exitCode);

        // Verify new sync log was created and marked as failed (skipped)
        $syncLogs = SyncLog::orderBy('created_at', 'desc')->get();
        $this->assertCount(2, $syncLogs);
        
        $latestSync = $syncLogs->first();
        $this->assertEquals(SyncLog::STATUS_FAILED, $latestSync->status);
        $this->assertEquals('Sync skipped - too soon after last sync', $latestSync->error_message);
    }

    public function test_sync_command_forces_sync_when_flag_provided()
    {
        // Create a recent successful sync
        SyncLog::factory()
            ->openOrders()
            ->successful()
            ->recent()
            ->create();

        $this->mockApiService
            ->expects($this->once())
            ->method('isConfigured')
            ->willReturn(true);

        $this->mockApiService
            ->expects($this->once())
            ->method('getAllOpenOrders')
            ->willReturn(collect()); // Empty collection

        $exitCode = Artisan::call('sync:open-orders', ['--force' => true]);
        $this->assertEquals(0, $exitCode);

        // Verify sync was not skipped
        $syncLogs = SyncLog::orderBy('created_at', 'desc')->get();
        $this->assertCount(2, $syncLogs);
        
        $latestSync = $syncLogs->first();
        $this->assertEquals(SyncLog::STATUS_COMPLETED, $latestSync->status);
    }

    public function test_sync_command_handles_empty_orders_response()
    {
        $this->mockApiService
            ->expects($this->once())
            ->method('isConfigured')
            ->willReturn(true);

        $this->mockApiService
            ->expects($this->once())
            ->method('getAllOpenOrders')
            ->willReturn(collect());

        $exitCode = Artisan::call('sync:open-orders');
        $this->assertEquals(0, $exitCode);

        // Verify sync log was created and completed
        $syncLog = SyncLog::first();
        $this->assertNotNull($syncLog);
        $this->assertEquals(SyncLog::STATUS_COMPLETED, $syncLog->status);
        $this->assertEquals(0, $syncLog->total_fetched);
    }

    public function test_sync_command_creates_new_orders()
    {
        $this->mockApiService
            ->expects($this->once())
            ->method('isConfigured')
            ->willReturn(true);

        // Create mock Linnworks orders with unique IDs
        $mockOrders = collect([
            $this->createMockLinnworksOrder('order-1', 12345, false),
            $this->createMockLinnworksOrder('order-2', 12346, false),
        ]);

        $this->mockApiService
            ->expects($this->once())
            ->method('getAllOpenOrders')
            ->willReturn($mockOrders);

        $exitCode = Artisan::call('sync:open-orders');
        $this->assertEquals(0, $exitCode);

        // Verify orders were created
        $this->assertDatabaseCount('orders', 2);
        
        $order1 = Order::where('linnworks_order_id', 'order-1')->first();
        $this->assertNotNull($order1);
        $this->assertEquals(12345, $order1->order_number);
        $this->assertTrue($order1->is_open);
        $this->assertFalse($order1->has_refund);

        // Verify sync log
        $syncLog = SyncLog::first();
        $this->assertEquals(SyncLog::STATUS_COMPLETED, $syncLog->status);
        $this->assertEquals(2, $syncLog->total_fetched);
        $this->assertEquals(2, $syncLog->total_created);
        $this->assertEquals(0, $syncLog->total_updated);
    }

    public function test_sync_command_updates_existing_orders()
    {
        // Create existing orders
        $order1 = Order::factory()
            ->open()
            ->create([
                'linnworks_order_id' => 'order-1',
                'order_number' => 12345,
                'total_charge' => 100.00,
                'last_synced_at' => Carbon::now()->subHour(),
            ]);

        $order2 = Order::factory()
            ->open()
            ->create([
                'linnworks_order_id' => 'order-2',
                'order_number' => 12346,
                'total_charge' => 50.00,
            ]);

        $this->mockApiService
            ->expects($this->once())
            ->method('isConfigured')
            ->willReturn(true);

        // Create mock Linnworks orders with updated data
        $mockOrders = collect([
            $this->createMockLinnworksOrder('order-1', 12345, false, 150.00), // Updated total
            $this->createMockLinnworksOrder('order-2', 12346, false, 50.00),  // No change
        ]);

        $this->mockApiService
            ->expects($this->once())
            ->method('getAllOpenOrders')
            ->willReturn($mockOrders);

        $exitCode = Artisan::call('sync:open-orders');
        $this->assertEquals(0, $exitCode);

        // Verify orders were updated
        $order1->refresh();
        $this->assertEquals(150.00, $order1->total_charge);
        $this->assertNotNull($order1->last_synced_at);

        $order2->refresh();
        $this->assertEquals(50.00, $order2->total_charge);

        // Verify sync log
        $syncLog = SyncLog::first();
        $this->assertEquals(2, $syncLog->total_fetched);
        $this->assertEquals(0, $syncLog->total_created);
        $this->assertEquals(1, $syncLog->total_updated);
        $this->assertEquals(1, $syncLog->total_skipped); // order-2 had no changes
    }

    public function test_sync_command_processes_all_orders()
    {
        $this->mockApiService
            ->expects($this->once())
            ->method('isConfigured')
            ->willReturn(true);

        // Create mock orders - all should be processed regardless of status
        $mockOrders = collect([
            $this->createMockLinnworksOrder('order-1', 12345, false),  // Normal order
            $this->createMockLinnworksOrder('order-2', 12346, true),   // Previously "refunded" order - now processed
            $this->createMockLinnworksOrder('order-3', 12347, false),  // Normal order
        ]);

        $this->mockApiService
            ->expects($this->once())
            ->method('getAllOpenOrders')
            ->willReturn($mockOrders);

        $exitCode = Artisan::call('sync:open-orders', ['--debug' => true]);
        $this->assertEquals(0, $exitCode);

        // Verify all orders were created (no filtering)
        $this->assertDatabaseCount('orders', 3);
        $this->assertDatabaseHas('orders', ['order_number' => 12345]);
        $this->assertDatabaseHas('orders', ['order_number' => 12346]);
        $this->assertDatabaseHas('orders', ['order_number' => 12347]);

        // Verify sync log
        $syncLog = SyncLog::first();
        $this->assertEquals(3, $syncLog->total_fetched);
        $this->assertEquals(3, $syncLog->total_created);
        $this->assertEquals(0, $syncLog->total_skipped); // no orders skipped
    }

    public function test_sync_command_marks_missing_orders_as_closed()
    {
        // Create existing open orders
        $order1 = Order::factory()
            ->open()
            ->create([
                'linnworks_order_id' => 'order-1',
                'last_synced_at' => Carbon::now()->subHour(),
            ]);

        $order2 = Order::factory()
            ->open()
            ->create([
                'linnworks_order_id' => 'order-2',
                'last_synced_at' => Carbon::now()->subHour(),
            ]);

        $order3 = Order::factory()
            ->open()
            ->create([
                'linnworks_order_id' => 'order-3',
                'last_synced_at' => Carbon::now()->subMinutes(5), // Recently synced
            ]);

        $this->mockApiService
            ->expects($this->once())
            ->method('isConfigured')
            ->willReturn(true);

        // Only return order-1 in the API response (order-2 missing, order-3 too recent)
        $mockOrders = collect([
            $this->createMockLinnworksOrder('order-1', 12345, false),
        ]);

        $this->mockApiService
            ->expects($this->once())
            ->method('getAllOpenOrders')
            ->willReturn($mockOrders);

        $exitCode = Artisan::call('sync:open-orders');
        $this->assertEquals(0, $exitCode);

        // Verify order-1 was updated and is still open
        $order1->refresh();
        $this->assertTrue($order1->is_open);

        // Verify order-2 was marked as closed (missing from API response)
        $order2->refresh();
        $this->assertFalse($order2->is_open);
        $this->assertArrayHasKey('marked_closed_at', $order2->sync_metadata);

        // Verify order-3 is still open (too recently synced to be affected)
        $order3->refresh();
        $this->assertTrue($order3->is_open);
    }

    public function test_sync_command_handles_api_errors_gracefully()
    {
        $this->mockApiService
            ->expects($this->once())
            ->method('isConfigured')
            ->willReturn(true);

        $this->mockApiService
            ->expects($this->once())
            ->method('getAllOpenOrders')
            ->willThrowException(new \Exception('API connection failed'));

        $exitCode = Artisan::call('sync:open-orders');
        $this->assertEquals(1, $exitCode);

        // Verify sync log was created and marked as failed
        $syncLog = SyncLog::first();
        $this->assertEquals(SyncLog::STATUS_FAILED, $syncLog->status);
        $this->assertEquals('API connection failed', $syncLog->error_message);
    }

    public function test_sync_command_continues_processing_after_individual_order_errors()
    {
        $this->mockApiService
            ->expects($this->once())
            ->method('isConfigured')
            ->willReturn(true);

        // Create mock orders where one will cause an error
        $mockOrders = collect([
            $this->createMockLinnworksOrder('order-1', 12345, false),
            $this->createMockLinnworksOrder('invalid-order', null, false), // This will cause an error
            $this->createMockLinnworksOrder('order-3', 12347, false),
        ]);

        $this->mockApiService
            ->expects($this->once())
            ->method('getAllOpenOrders')
            ->willReturn($mockOrders);

        $exitCode = Artisan::call('sync:open-orders', ['--debug' => true]);
        $this->assertEquals(0, $exitCode);

        // Verify that valid orders were still processed
        $this->assertDatabaseCount('orders', 2);
        $this->assertDatabaseHas('orders', ['order_number' => 12345]);
        $this->assertDatabaseHas('orders', ['order_number' => 12347]);

        // Verify sync log shows mixed results
        $syncLog = SyncLog::first();
        $this->assertEquals(SyncLog::STATUS_COMPLETED, $syncLog->status);
        $this->assertEquals(3, $syncLog->total_fetched);
        $this->assertEquals(2, $syncLog->total_created);
        $this->assertEquals(1, $syncLog->total_failed);
    }

    /**
     * Create a mock LinnworksOrder for testing
     */
    private function createMockLinnworksOrder(
        string $orderId,
        ?int $orderNumber = null,
        bool $hasRefund = false,
        float $totalCharge = 100.00
    ): LinnworksOrder {
        $orderNumber = $orderNumber ?? rand(10000, 99999);
        
        return new LinnworksOrder(
            orderId: $orderId,
            orderNumber: $orderNumber,
            receivedDate: Carbon::now()->subDays(2),
            processedDate: null,
            orderSource: 'Amazon',
            subsource: null,
            currency: 'GBP',
            totalCharge: $totalCharge,
            postageCost: 5.00,
            tax: 20.00,
            profitMargin: 30.00,
            orderStatus: 0, // 0 = pending/open
            locationId: 'location-' . $orderId,
            items: collect([])
        );
    }
}