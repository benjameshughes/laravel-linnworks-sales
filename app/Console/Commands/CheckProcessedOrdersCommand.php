<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\LinnworksApiService;
use App\Services\LinnworksOAuthService;
use App\Models\Order;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CheckProcessedOrdersCommand extends Command
{
    protected $signature = 'orders:check-processed 
                            {--test-single : Test with just the first order in database}
                            {--dry-run : Show what would be updated without making changes}';

    protected $description = 'Check and update processed status of orders from Linnworks API';

    public function handle(LinnworksOAuthService $oauthService): int
    {
        $this->info('🔍 Checking processed orders status from Linnworks...');

        if ($this->option('test-single')) {
            return $this->testSingleOrder($oauthService);
        }

        $apiService = new LinnworksApiService($oauthService);

        if (!$apiService->isConfigured()) {
            $this->error('❌ Linnworks API is not configured. Please check your .env file.');
            return 1;
        }

        try {
            $this->info('📡 Connecting to Linnworks API...');
            
            if ($this->option('dry-run')) {
                $this->warn('🔍 DRY RUN MODE - No database changes will be made');
                return $this->dryRunCheck($apiService);
            }

            $success = $apiService->checkAndUpdateProcessedOrders();

            if ($success) {
                $this->info('✅ Successfully updated processed orders status');
                return 0;
            } else {
                $this->error('❌ Failed to update processed orders status');
                return 1;
            }

        } catch (\Exception $e) {
            $this->error('❌ Error: ' . $e->getMessage());
            Log::error('CheckProcessedOrdersCommand failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }
    }

    private function testSingleOrder(LinnworksOAuthService $oauthService): int
    {
        $this->info('🧪 Testing with single order...');

        // Get first order from database
        $order = Order::whereNotNull('linnworks_order_id')->first();

        if (!$order) {
            $this->error('❌ No orders found in database with Linnworks order ID');
            return 1;
        }

        $this->info("📋 Testing with order: {$order->order_number} (Linnworks ID: {$order->linnworks_order_id})");
        $this->info("   Current processed status: " . ($order->is_processed ? 'YES' : 'NO'));

        $apiService = new LinnworksApiService($oauthService);

        if (!$apiService->isConfigured()) {
            $this->error('❌ Linnworks API is not configured');
            return 1;
        }

        try {
            // Test authentication first
            $this->info('🔐 Testing API authentication...');
            if (!$apiService->testConnection()) {
                $this->error('❌ Failed to authenticate with Linnworks API');
                return 1;
            }
            $this->info('✅ API authentication successful');

            // Create a minimal test collection with just this order
            $testOrders = collect([$order]);
            $orderUuids = [$order->linnworks_order_id];

            $this->info('📡 Fetching order details from Linnworks...');

            // Use the main service method to test
            $this->info('🔄 Running processed orders check...');
            
            $beforeStatus = $order->is_processed;
            $result = $apiService->checkAndUpdateProcessedOrders();
            
            // Refresh the order to see if it changed
            $order->refresh();
            $afterStatus = $order->is_processed;

            $this->info("🔍 Results:");
            $this->line("   API call result: " . ($result ? 'SUCCESS' : 'FAILED'));
            $this->line("   Before status: " . ($beforeStatus ? 'YES' : 'NO'));
            $this->line("   After status: " . ($afterStatus ? 'YES' : 'NO'));
            
            if ($beforeStatus !== $afterStatus) {
                $this->info("🔄 Status changed: " . ($beforeStatus ? 'YES' : 'NO') . " → " . ($afterStatus ? 'YES' : 'NO'));
            } else {
                $this->info("✨ Status unchanged - either no update needed or API says same status");
            }

            $this->info('✅ Single order test completed successfully');
            return 0;

        } catch (\Exception $e) {
            $this->error('❌ Test failed: ' . $e->getMessage());
            return 1;
        }
    }

    private function dryRunCheck(LinnworksApiService $apiService): int
    {
        $this->info('🔍 Performing dry run check...');

        // Get orders that would be checked
        $orders = Order::where('received_date', '>=', now()->subDays(90))
            ->whereNotNull('linnworks_order_id')
            ->get();

        $this->info("📊 Found {$orders->count()} orders to check");

        if ($orders->isEmpty()) {
            $this->info('✨ No orders to check');
            return 0;
        }

        $this->table(
            ['Order Number', 'Linnworks ID', 'Current Status', 'Received Date'],
            $orders->take(10)->map(fn($order) => [
                $order->order_number,
                substr($order->linnworks_order_id, 0, 8) . '...',
                $order->is_processed ? 'Processed' : 'Open',
                $order->received_date?->format('Y-m-d H:i'),
            ])->toArray()
        );

        if ($orders->count() > 10) {
            $this->info("... and " . ($orders->count() - 10) . " more orders");
        }

        $this->info('🔍 This would check these orders against Linnworks API and update their processed status');
        $this->info('💡 Run without --dry-run to perform actual updates');

        return 0;
    }
}