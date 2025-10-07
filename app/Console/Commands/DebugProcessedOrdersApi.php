<?php

namespace App\Console\Commands;

use App\Services\LinnworksApiService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class DebugProcessedOrdersApi extends Command
{
    protected $signature = 'debug:processed-orders {--days=30 : Number of days back to test}';
    protected $description = 'Debug the processed orders API with detailed logging';

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

        $this->info("🔍 Debug: Testing processed orders API for last {$days} days...");
        $this->info("📅 Date range: {$from->toDateString()} to {$to->toDateString()}");
        
        // Enable verbose logging temporarily
        Log::info('=== DEBUGGING PROCESSED ORDERS API ===');
        Log::info('Date range', [
            'from' => $from->format('Y-m-d\TH:i:s.v\Z'),
            'to' => $to->format('Y-m-d\TH:i:s.v\Z'),
            'days' => $days
        ]);

        $this->info('🔄 Testing API call with detailed logging...');
        
        // Test the service with debug info
        $result = $apiService->getProcessedOrders($from, $to, 1, 50);
        
        $this->info('📊 Raw API Response Analysis:');
        $this->table(['Field', 'Value', 'Type'], [
            ['Orders Count', $result->orders->count(), get_class($result->orders)],
            ['Total Results', $result->totalResults, gettype($result->totalResults)],
            ['Has More Pages', $result->hasMorePages ? 'true' : 'false', gettype($result->hasMorePages)],
            ['Current Page', $result->currentPage ?? 'missing', gettype($result->currentPage ?? null)],
            ['Entries Per Page', $result->entriesPerPage ?? 'missing', gettype($result->entriesPerPage ?? null)],
        ]);

        // Test authentication specifically
        $this->info('🔐 Testing authentication...');
        if ($apiService->testConnection()) {
            $this->info('✅ Authentication successful');
        } else {
            $this->error('❌ Authentication failed');
            return self::FAILURE;
        }

        // Test with different parameters
        $this->info('🧪 Testing alternative parameters...');
        
        // Try without search fields
        Log::info('Testing with minimal parameters');
        
        // Try with different date formats
        $this->info('📝 Request parameters being sent:');
        $this->table(['Parameter', 'Value'], [
            ['fromDate', $from->format('Y-m-d\TH:i:s.v\Z')],
            ['toDate', $to->format('Y-m-d\TH:i:s.v\Z')],
            ['pageNumber', '1'],
            ['entriesPerPage', '50'],
            ['searchField', "''"],
            ['searchTerm', "''"],
            ['fulfilmentCenter', "''"],
            ['sorting.Direction', '0'],
            ['sorting.Field', 'dProcessedOn'],
        ]);

        // Suggest next steps
        $this->newLine();
        if ($result->totalResults === 0) {
            $this->warn('⚠️  API returned 0 results. This could mean:');
            $this->line('   1. No processed orders exist in this date range');
            $this->line('   2. The endpoint requires different parameters');
            $this->line('   3. Different permissions are needed');
            $this->line('   4. The account has no processed orders');
            $this->newLine();
            $this->info('💡 Suggestions:');
            $this->line('   - Check your Linnworks dashboard for processed orders');
            $this->line('   - Try the open orders endpoint instead');
            $this->line('   - Contact Linnworks support about API permissions');
        } else {
            $this->info('✅ Success! Found orders in the API response.');
        }

        return self::SUCCESS;
    }
}
