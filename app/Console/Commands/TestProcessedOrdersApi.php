<?php

namespace App\Console\Commands;

use App\Services\LinnworksApiService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class TestProcessedOrdersApi extends Command
{
    protected $signature = 'test:processed-orders {--days=7 : Number of days back to test}';
    protected $description = 'Test the processed orders API endpoint';

    public function handle(): int
    {
        $apiService = app(LinnworksApiService::class);
        
        if (!$apiService->isConfigured()) {
            $this->error('Linnworks API is not configured.');
            return self::FAILURE;
        }

        $days = (int) $this->option('days');
        $from = Carbon::now()->subDays($days);
        $to = Carbon::now();

        $this->info("Testing processed orders API for last {$days} days...");
        $this->info("Date range: {$from->toDateString()} to {$to->toDateString()}");

        // Test single page fetch
        $this->info('🔄 Testing single page fetch...');
        $result = $apiService->getProcessedOrders($from, $to, 1, 50);
        
        $this->table(['Metric', 'Value'], [
            ['Orders Retrieved', $result->orders->count()],
            ['Total Available', $result->totalResults],
            ['Has More Pages', $result->hasMorePages ? 'Yes' : 'No'],
            ['Current Page', $result->currentPage ?? 'N/A'],
            ['Entries Per Page', $result->entriesPerPage ?? 'N/A'],
        ]);

        if ($result->orders->isNotEmpty()) {
            $firstOrder = $result->orders->first()?->toArray() ?? [];
            $this->info('📋 Sample order data:');
            $this->table(['Field', 'Value'], [
                ['Order ID', $firstOrder['order_id'] ?? 'N/A'],
                ['Order Number', $firstOrder['order_number'] ?? 'N/A'],
                ['Channel', $firstOrder['channel_name'] ?? 'N/A'],
                ['Total Charge', '£' . number_format($firstOrder['total_charge'] ?? 0, 2)],
                ['Items Count', count($firstOrder['items'] ?? [])],
                ['Received Date', $firstOrder['received_date'] ?? 'N/A'],
                ['Processed Date', $firstOrder['processed_date'] ?? 'N/A'],
            ]);

            if (!empty($firstOrder['items'])) {
                $this->info('🛍️ Sample item from first order:');
                $firstItem = $firstOrder['items'][0];
                $this->table(['Field', 'Value'], [
                    ['SKU', $firstItem['sku'] ?? 'N/A'],
                    ['Title', $firstItem['item_title'] ?? 'N/A'],
                    ['Quantity', $firstItem['quantity'] ?? 0],
                    ['Price Per Unit', '£' . number_format($firstItem['price_per_unit'] ?? 0, 2)],
                    ['Line Total', '£' . number_format($firstItem['line_total'] ?? 0, 2)],
                    ['Category', $firstItem['category_name'] ?? 'N/A'],
                ]);
            }
        }

        if ($result->totalResults > 50) {
            $this->warn("⚠️  Only showing first 50 orders. Total available: {$result->totalResults}");
            $this->info("💡 Use the import:historical-orders command to import all orders.");
        }

        if ($result->orders->isEmpty()) {
            $this->warn('⚠️  No orders found in the specified date range.');
            $this->info('💡 Try increasing the --days parameter or check if you have processed orders in Linnworks.');
        } else {
            $this->info('✅ API test completed successfully!');
        }

        return self::SUCCESS;
    }
}
