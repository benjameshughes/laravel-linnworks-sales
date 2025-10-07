<?php

namespace App\Console\Commands;

use App\Services\LinnworksApiService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class QuickTestProcessed extends Command
{
    protected $signature = 'quick:test-processed';
    protected $description = 'Quick test of processed orders API';

    public function handle(): int
    {
        $apiService = app(LinnworksApiService::class);
        
        // Test with exact dates from our database
        $from = Carbon::parse('2025-06-10');
        $to = Carbon::parse('2025-07-06');
        
        $this->info("Testing processed orders from {$from->toDateString()} to {$to->toDateString()}");
        
        $result = $apiService->getProcessedOrders($from, $to, 1, 50);
        
        $this->info("Results: {$result->orders->count()} orders, {$result->totalResults} total");
        
        if ($result->orders->isNotEmpty()) {
            $this->info("✅ Found orders!");
            $firstOrder = $result->orders->first()?->toArray() ?? [];
            $orderId = $firstOrder['order_id'] ?? 'N/A';
            $channel = $firstOrder['channel_name'] ?? 'N/A';
            $this->info("First order: {$orderId} - {$channel}");
        } else {
            $this->warn("❌ No orders found");
            
            // Try with different DateField options
            $this->info("🔄 Trying with DateField: 'processed'...");
            
            // Temporarily change the service to test different date fields
            $this->info("🔄 The issue might be:");
            $this->line("1. Orders haven't been 'processed' in Linnworks yet");  
            $this->line("2. Need different DateField (processed/received/dispatched)");
            $this->line("3. API permissions or endpoint differences");
            $this->line("4. Orders are still in 'open' status in Linnworks");
        }
        
        return self::SUCCESS;
    }
}
