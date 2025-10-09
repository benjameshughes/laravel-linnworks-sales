<?php

namespace App\Console\Commands;

use App\DataTransferObjects\LinnworksOrder;
use App\Services\LinnworksApiService;
use App\Models\Order;
use App\Models\OrderItem;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class SyncProcessedOrders extends Command
{
    protected $signature = 'sync:processed-orders 
                            {--from= : Start date (YYYY-MM-DD)}
                            {--to= : End date (YYYY-MM-DD)}
                            {--days=30 : Number of days back to sync (if no from/to provided)}
                            {--batch-size=200 : Number of orders to process per batch (max 200)}
                            {--update-existing : Update existing open orders to processed status}
                            {--dry-run : Show what would be synced without saving}';

    protected $description = 'Sync processed orders from Linnworks and update order statuses locally';

    private LinnworksApiService $apiService;
    private int $totalProcessed = 0;
    private int $totalImported = 0;
    private int $totalUpdated = 0;
    private int $totalSkipped = 0;
    private int $totalErrors = 0;

    public function __construct(LinnworksApiService $apiService)
    {
        parent::__construct();
        $this->apiService = $apiService;
    }

    public function handle(): int
    {
        if (!$this->apiService->isConfigured()) {
            $this->error('Linnworks API is not configured. Please check your credentials.');
            return self::FAILURE;
        }

        $dateRange = $this->getDateRange();
        $batchSize = min((int) $this->option('batch-size'), 200);
        $updateExisting = $this->option('update-existing');
        $isDryRun = $this->option('dry-run');

        $this->info('🔄 Starting processed orders sync...');
        $this->table(['Setting', 'Value'], [
            ['From Date', $dateRange['from']->toDateString()],
            ['To Date', $dateRange['to']->toDateString()],
            ['Batch Size', $batchSize],
            ['Update Existing', $updateExisting ? 'Yes' : 'No'],
            ['Mode', $isDryRun ? 'DRY RUN' : 'LIVE SYNC'],
            ['Safety', 'READ-ONLY from Linnworks (never writes back)'],
        ]);

        if (!$this->confirm('Do you want to continue?')) {
            $this->info('Sync cancelled.');
            return self::SUCCESS;
        }

        // Test API connection
        if (!$this->testApiConnection()) {
            return self::FAILURE;
        }

        // Sync processed orders
        $success = $this->syncProcessedOrders(
            $dateRange['from'], 
            $dateRange['to'], 
            $batchSize,
            $updateExisting,
            $isDryRun
        );

        // Final summary
        $this->displayFinalSummary($isDryRun);

        return $success ? self::SUCCESS : self::FAILURE;
    }

    private function getDateRange(): array
    {
        $from = $this->option('from');
        $to = $this->option('to');
        $days = (int) $this->option('days');

        if ($from && $to) {
            return [
                'from' => Carbon::parse($from)->startOfDay(),
                'to' => Carbon::parse($to)->endOfDay(),
            ];
        }

        if ($from && !$to) {
            return [
                'from' => Carbon::parse($from)->startOfDay(),
                'to' => Carbon::now()->endOfDay(),
            ];
        }

        if (!$from && $to) {
            return [
                'from' => Carbon::parse($to)->subDays($days)->startOfDay(),
                'to' => Carbon::parse($to)->endOfDay(),
            ];
        }

        return [
            'from' => Carbon::now()->subDays($days)->startOfDay(),
            'to' => Carbon::now()->endOfDay(),
        ];
    }

    private function testApiConnection(): bool
    {
        $this->info('🔗 Testing API connection...');
        
        if (!$this->apiService->testConnection()) {
            $this->error('❌ Failed to connect to Linnworks API.');
            return false;
        }

        $this->info('✅ API connection successful');
        return true;
    }

    private function syncProcessedOrders(
        Carbon $from, 
        Carbon $to, 
        int $batchSize,
        bool $updateExisting,
        bool $isDryRun
    ): bool {
        $this->info('📥 Fetching processed orders from Linnworks...');
        
        try {
            $pageNumber = 1;
            $totalOrdersAvailable = 0;

            // Get first page to determine total available
            $firstResult = $this->apiService->getProcessedOrders($from, $to, $pageNumber, $batchSize);
            
            if ($firstResult->orders->isEmpty()) {
                $this->warn('⚠️  No processed orders found in the specified date range.');
                $this->info('💡 This is normal if orders haven\'t been processed in Linnworks yet.');
                return true;
            }

            $totalOrdersAvailable = $firstResult->totalResults;
            $this->info("📊 Found {$totalOrdersAvailable} processed orders to sync");

            // Process first batch
            $this->processOrderBatch($firstResult->orders, $updateExisting, $isDryRun);
            $pageNumber++;
            $hasMorePages = $firstResult->hasMorePages;

            // Process remaining pages if any
            while ($hasMorePages) {
                $this->info("📄 Processing page {$pageNumber}...");
                
                $result = $this->apiService->getProcessedOrders($from, $to, $pageNumber, $batchSize);
                
                if ($result->orders->isEmpty()) {
                    break;
                }

                $this->processOrderBatch($result->orders, $updateExisting, $isDryRun);
                
                $this->info("✓ Page {$pageNumber} complete ({$result->orders->count()} orders)");
                
                $hasMorePages = $result->hasMorePages;
                $pageNumber++;
            }

            $this->info("✅ Completed sync. Total orders processed: {$this->totalProcessed}");
            return true;

        } catch (\Exception $e) {
            $this->error("❌ Error during sync: {$e->getMessage()}");
            Log::error('Processed orders sync failed', [
                'error' => $e->getMessage(),
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ]);
            return false;
        }
    }

    private function processOrderBatch($processedOrders, bool $updateExisting, bool $isDryRun): void
    {
        foreach ($processedOrders as $processedOrder) {
            $this->totalProcessed++;
            $processedOrderData = $processedOrder instanceof LinnworksOrder
                ? $processedOrder->toArray()
                : (array) $processedOrder;
            
            try {
                if ($isDryRun) {
                    $this->handleDryRunOrder($processedOrderData, $updateExisting);
                    continue;
                }

                $this->handleProcessedOrder($processedOrderData, $updateExisting);
                
                if ($this->totalProcessed % 25 === 0) {
                    $this->info("📈 Progress: {$this->totalProcessed} processed, {$this->totalImported} new, {$this->totalUpdated} updated, {$this->totalSkipped} skipped");
                }

            } catch (\Exception $e) {
                $this->totalErrors++;
                $this->error("❌ Failed to process order {$processedOrderData['order_number']}: {$e->getMessage()}");
                Log::error('Failed to process individual order', [
                    'order_id' => $processedOrderData['order_id'] ?? 'unknown',
                    'order_number' => $processedOrderData['order_number'] ?? 'unknown',
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private function handleDryRunOrder(array $processedOrderData, bool $updateExisting): void
    {
        // Check if this order exists in our database as an open order
        $existingOpenOrder = Order::open()
            ->where(function($query) use ($processedOrderData) {
                $query->where('order_id', $processedOrderData['order_id'])
                      ->orWhere('linnworks_order_id', $processedOrderData['order_id'])
                      ->orWhere('order_number', $processedOrderData['order_number']);
            })
            ->first();

        // Check if this order already exists as processed
        $existingProcessedOrder = Order::processed()
            ->where(function($query) use ($processedOrderData) {
                $query->where('order_id', $processedOrderData['order_id'])
                      ->orWhere('linnworks_order_id', $processedOrderData['order_id'])
                      ->orWhere('order_number', $processedOrderData['order_number']);
            })
            ->first();

        if ($existingProcessedOrder) {
            $this->line("  [DRY RUN] Would skip: {$processedOrderData['order_number']} (already processed)");
            $this->totalSkipped++;
        } elseif ($existingOpenOrder && $updateExisting) {
            $this->line("  [DRY RUN] Would update: {$processedOrderData['order_number']} (open → processed)");
            $this->totalUpdated++;
        } elseif (!$existingOpenOrder) {
            $this->line("  [DRY RUN] Would import: {$processedOrderData['order_number']} (new processed order)");
            $this->totalImported++;
        } else {
            $this->line("  [DRY RUN] Would skip: {$processedOrderData['order_number']} (exists as open, update not enabled)");
            $this->totalSkipped++;
        }
    }

    private function handleProcessedOrder(array $processedOrderData, bool $updateExisting): void
    {
        // Check if this order exists in our database as an open order
        $existingOpenOrder = Order::open()
            ->where(function($query) use ($processedOrderData) {
                $query->where('order_id', $processedOrderData['order_id'])
                      ->orWhere('linnworks_order_id', $processedOrderData['order_id'])
                      ->orWhere('order_number', $processedOrderData['order_number']);
            })
            ->first();

        // Check if this order already exists as processed
        $existingProcessedOrder = Order::processed()
            ->where(function($query) use ($processedOrderData) {
                $query->where('order_id', $processedOrderData['order_id'])
                      ->orWhere('linnworks_order_id', $processedOrderData['order_id'])
                      ->orWhere('order_number', $processedOrderData['order_number']);
            })
            ->first();

        if ($existingProcessedOrder) {
            // Order already exists as processed - skip
            $this->totalSkipped++;
            if ($this->totalProcessed % 50 === 0) {
                $this->line("  ⏭️  Skipped existing processed: {$processedOrderData['order_number']}");
            }
            return;
        }

        if ($existingOpenOrder && $updateExisting) {
            // Update existing open order to processed status
            $this->updateOpenOrderToProcessed($existingOpenOrder, $processedOrderData);
            $this->totalUpdated++;
            return;
        }

        if (!$existingOpenOrder) {
            // Import as new processed order
            $this->importNewProcessedOrder($processedOrderData);
            $this->totalImported++;
            return;
        }

        // Order exists as open but update not enabled
        $this->totalSkipped++;
    }

    private function updateOpenOrderToProcessed(Order $openOrder, array $processedOrderData): void
    {
        $processedDate = $processedOrderData['processed_date'] 
            ? Carbon::parse($processedOrderData['processed_date']) 
            : null;

        $openOrder->markAsProcessed($processedDate);
        
        if ($this->totalUpdated % 10 === 0) {
            $this->info("  🔄 Updated to processed: {$openOrder->order_number}");
        }
    }

    private function importNewProcessedOrder(array $processedOrderData): void
    {
        DB::transaction(function () use ($processedOrderData) {
            // Create the processed order record
            $order = Order::create([
                'order_id' => $processedOrderData['order_id'],
                'order_number' => $processedOrderData['order_number'],
                'received_date' => $processedOrderData['received_date'] ? Carbon::parse($processedOrderData['received_date']) : null,
                'processed_date' => $processedOrderData['processed_date'] ? Carbon::parse($processedOrderData['processed_date']) : null,
                'channel_name' => \Illuminate\Support\Str::lower(str_replace(' ', '_', $processedOrderData['order_source'] ?? $processedOrderData['channel_name'] ?? 'Unknown')),
                'sub_source' => isset($processedOrderData['subsource']) || isset($processedOrderData['sub_source'])
                    ? \Illuminate\Support\Str::lower(str_replace(' ', '_', $processedOrderData['subsource'] ?? $processedOrderData['sub_source']))
                    : null,
                'currency' => $processedOrderData['currency'] ?? 'GBP',
                'total_charge' => $processedOrderData['total_charge'] ?? 0,
                'postage_cost' => $processedOrderData['postage_cost'] ?? 0,
                'tax' => $processedOrderData['tax'] ?? 0,
                'profit_margin' => $processedOrderData['profit_margin'] ?? 0,
                'order_status' => $processedOrderData['order_status'] ?? 0,
                'location_id' => $processedOrderData['location_id'],
                'notes' => $processedOrderData['notes'],
                'is_open' => false, // This is a processed order
                'status' => 'processed',
                'sync_status' => 'synced',
                'last_synced_at' => now(),
            ]);

            // Create order items
            if (!empty($processedOrderData['items'])) {
                foreach ($processedOrderData['items'] as $itemData) {
                    OrderItem::create([
                        'order_id' => $order->id,
                        'item_id' => $itemData['item_id'],
                        'sku' => $itemData['sku'],
                        'item_title' => $itemData['item_title'],
                        'quantity' => $itemData['quantity'] ?? 0,
                        'unit_cost' => $itemData['unit_cost'] ?? 0,
                        'price_per_unit' => $itemData['price_per_unit'] ?? 0,
                        'line_total' => $itemData['line_total'] ?? 0,
                        'category_name' => $itemData['category_name'],
                    ]);
                }
            }
        });
    }

    private function displayFinalSummary(bool $isDryRun): void
    {
        $this->newLine(2);
        $this->info('📊 Processed Orders Sync Summary');
        $this->table(['Metric', 'Count'], [
            ['Total Processed', number_format($this->totalProcessed)],
            ['New Imports', number_format($this->totalImported)],
            ['Updated (Open → Processed)', number_format($this->totalUpdated)],
            ['Skipped (Already Processed)', number_format($this->totalSkipped)],
            ['Errors', number_format($this->totalErrors)],
        ]);

        if ($isDryRun) {
            $this->info('🔍 This was a dry run - no data was actually modified.');
            $this->info('💡 Run without --dry-run to perform the actual sync.');
        } elseif ($this->totalImported > 0 || $this->totalUpdated > 0) {
            $this->info('✅ Processed orders sync completed successfully!');
            $this->info('💡 Consider running: php artisan analytics:refresh-cache --force');
        } elseif ($this->totalSkipped === $this->totalProcessed) {
            $this->warn('⚠️  All orders were already synced. No changes made.');
        } else {
            $this->error('❌ Sync completed with errors. Check the logs for details.');
        }

        if ($this->totalErrors > 0) {
            $this->warn("⚠️  {$this->totalErrors} orders failed to sync. Check the application logs for details.");
        }

        $this->newLine();
        $this->info('🔒 Safety reminder: This command only reads from Linnworks and never writes back.');
    }
}
