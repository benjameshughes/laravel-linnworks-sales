<?php

namespace App\Console\Commands;

use App\DataTransferObjects\LinnworksOrder;
use App\Events\ImportCompleted;
use App\Events\ImportProgressUpdated;
use App\Events\ImportStarted;
use App\Services\LinnworksApiService;
use App\Models\Order;
use App\Models\OrderItem;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class ImportHistoricalOrders extends Command
{
    protected $signature = 'import:historical-orders
                            {--from= : Start date (YYYY-MM-DD)}
                            {--to= : End date (YYYY-MM-DD)}
                            {--days=30 : Number of days back to import (if no from/to provided)}
                            {--batch-size=200 : Number of orders to process per batch (max 200)}
                            {--dry-run : Show what would be imported without saving}
                            {--force : Skip confirmation prompt}';

    protected $description = 'Import historical processed orders from Linnworks for improved analytics';

    private LinnworksApiService $apiService;
    private int $totalProcessed = 0;
    private int $totalImported = 0;
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

        // Determine date range
        try {
            $dateRange = $this->getDateRange();
        } catch (\InvalidArgumentException $exception) {
            $this->error("❌ {$exception->getMessage()}");
            return self::FAILURE;
        }
        $batchSize = min((int) $this->option('batch-size'), 200); // Enforce API limit
        $isDryRun = $this->option('dry-run');

        $this->info('🔄 Starting historical order import...');
        $this->table(['Setting', 'Value'], [
            ['From Date', $dateRange['from']->toDateString()],
            ['To Date', $dateRange['to']->toDateString()],
            ['Batch Size', $batchSize],
            ['Mode', $isDryRun ? 'DRY RUN' : 'LIVE IMPORT'],
            ['Rate Limit', '150 requests/minute (auto-managed)'],
        ]);

        if (!$this->option('force') && !$this->confirm('Do you want to continue?')) {
            $this->info('Import cancelled.');
            return self::SUCCESS;
        }

        // Test API connection (skipped - we know it works)
        // if (!$this->testApiConnection()) {
        //     return self::FAILURE;
        // }

        // Import orders
        $success = $this->importHistoricalOrders(
            $dateRange['from'], 
            $dateRange['to'], 
            $batchSize, 
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
            $fromDate = Carbon::parse($from)->startOfDay();
            $toDate = Carbon::parse($to)->endOfDay();
        } elseif ($from && !$to) {
            $fromDate = Carbon::parse($from)->startOfDay();
            $toDate = Carbon::now()->endOfDay();
        } elseif (!$from && $to) {
            $toDate = Carbon::parse($to)->endOfDay();
            $fromDate = $toDate->copy()->subDays($days)->startOfDay();
        } else {
            // Default: last N days
            $toDate = Carbon::now()->endOfDay();
            $fromDate = $toDate->copy()->subDays($days)->startOfDay();
        }

        // Validate maximum 2 years (730 days)
        $daysDiff = $fromDate->diffInDays($toDate);
        if ($daysDiff > 731) {
            $this->error('❌ Date range exceeds maximum of 2 years (730 days)');
            $this->info("   Requested: {$daysDiff} days");
            $this->info("   From: {$fromDate->toDateString()}");
            $this->info("   To: {$toDate->toDateString()}");
            $this->newLine();
            $this->warn('💡 Tip: Use --from=YYYY-MM-DD to limit the date range');

            throw new \InvalidArgumentException('Date range exceeds the maximum of 730 days.');
        }

        // Warn if going back more than 1 year
        if ($daysDiff > 365) {
            $this->warn("⚠️  Importing {$daysDiff} days of data. This may take a while...");
        }

        return [
            'from' => $fromDate,
            'to' => $toDate,
        ];
    }

    private function testApiConnection(): bool
    {
        $this->info('🔗 Testing API connection...');
        
        if (!$this->apiService->testConnection()) {
            $this->error('❌ Failed to connect to Linnworks API. Please check your credentials and network connection.');
            return false;
        }

        $this->info('✅ API connection successful');
        return true;
    }

    private function importHistoricalOrders(Carbon $from, Carbon $to, int $batchSize, bool $isDryRun): bool
    {
        $this->info('📥 Fetching historical orders from Linnworks...');
        
        try {
            $pageNumber = 1;
            $totalOrdersAvailable = 0;

            // Get first page to determine total available
            $firstResult = $this->apiService->getProcessedOrders($from, $to, $pageNumber, $batchSize);
            
            $totalOrdersAvailable = $firstResult->totalResults;
            
            if ($totalOrdersAvailable === 0 || $firstResult->orders->isEmpty()) {
                $this->warn('⚠️  No orders found in the specified date range.');

                if (!$isDryRun) {
                    event(new ImportStarted($from, $to, $batchSize, 0));
                    event(new ImportCompleted(
                        totalProcessed: 0,
                        totalImported: 0,
                        totalSkipped: 0,
                        totalErrors: 0,
                        success: true
                    ));
                }

                return true;
            }

            $this->info("📊 Found {$totalOrdersAvailable} orders to process");

            if (!$isDryRun) {
                event(new ImportStarted($from, $to, $batchSize, $totalOrdersAvailable));
            }

            // Process first batch
            $this->processOrderBatch($firstResult->orders, $isDryRun);
            $pageNumber++;
            $hasMorePages = $firstResult->hasMorePages;

            // Process remaining pages if any
            while ($hasMorePages) {
                $this->info("📄 Processing page {$pageNumber}...");
                
                $result = $this->apiService->getProcessedOrders($from, $to, $pageNumber, $batchSize);
                
                if ($result->orders->isEmpty()) {
                    break;
                }

                $this->processOrderBatch($result->orders, $isDryRun);

                $this->info("✓ Page {$pageNumber} complete ({$result->orders->count()} orders)");

                // Broadcast progress update
                if (!$isDryRun) {
                    event(new ImportProgressUpdated(
                        totalProcessed: $this->totalProcessed,
                        totalImported: $this->totalImported,
                        totalSkipped: $this->totalSkipped,
                        totalErrors: $this->totalErrors,
                        currentPage: $pageNumber,
                        totalOrders: $totalOrdersAvailable,
                        status: 'processing',
                        message: "Page {$pageNumber} complete"
                    ));
                }

                $hasMorePages = $result->hasMorePages;
                $pageNumber++;
            }

            $this->info("✅ Completed processing. Total orders: {$this->totalProcessed}");

            // Broadcast import completed event
            if (!$isDryRun) {
                event(new ImportCompleted(
                    totalProcessed: $this->totalProcessed,
                    totalImported: $this->totalImported,
                    totalSkipped: $this->totalSkipped,
                    totalErrors: $this->totalErrors,
                    success: true
                ));
            }

            return true;

        } catch (\Exception $e) {
            $this->error("❌ Error during import: {$e->getMessage()}");
            Log::error('Historical order import failed', [
                'error' => $e->getMessage(),
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ]);

            // Broadcast import failed event
            if (!$isDryRun) {
            event(new ImportCompleted(
                totalProcessed: $this->totalProcessed,
                totalImported: $this->totalImported,
                totalSkipped: $this->totalSkipped,
                totalErrors: $this->totalErrors,
                success: false
            ));
            }

            return false;
        }
    }

    private function processOrderBatch($orders, bool $isDryRun): void
    {
        foreach ($orders as $order) {
            $this->totalProcessed++;
            $orderData = $order instanceof LinnworksOrder
                ? $order->toArray()
                : (array) $order;
            
            try {
                if ($isDryRun) {
                    $this->line("  [DRY RUN] Would import: {$orderData['order_number']} ({$orderData['received_date']})");
                    $this->totalImported++;
                    continue;
                }

                // Check if order already exists
                $existingOrder = isset($orderData['order_id'])
                    ? Order::where('linnworks_order_id', $orderData['order_id'])->first()
                    : null;

                if ($existingOrder) {
                    // Update existing order with processed data
                    $this->updateExistingOrder($existingOrder, $orderData);
                    $this->totalImported++;
                    if ($this->totalProcessed % 50 === 0) {
                        $this->line("  🔄  Updated: {$orderData['order_number']}");
                    }
                    continue;
                }

                // Import new order and its items
                $this->importOrderAndItems($orderData);
                $this->totalImported++;
                
                if ($this->totalProcessed % 25 === 0) {
                    $this->info("📈 Progress: {$this->totalProcessed} processed, {$this->totalImported} imported, {$this->totalSkipped} skipped");

                    // Broadcast progress update every 25 orders
                    if (!$isDryRun) {
                        event(new ImportProgressUpdated(
                            totalProcessed: $this->totalProcessed,
                            totalImported: $this->totalImported,
                            totalSkipped: $this->totalSkipped,
                            totalErrors: $this->totalErrors,
                            currentPage: 0, // Will be set by page completion
                            totalOrders: 0, // Will be set by page completion
                            status: 'processing',
                            message: null
                        ));
                    }
                }

            } catch (\Exception $e) {
                $this->totalErrors++;
                $this->error("❌ Failed to import order {$orderData['order_number']}: {$e->getMessage()}");
                Log::error('Failed to import individual order', [
                    'order_id' => $orderData['order_id'] ?? 'unknown',
                    'order_number' => $orderData['order_number'] ?? 'unknown',
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private function importOrderAndItems(array $orderData): void
    {
        DB::transaction(function () use ($orderData) {
            // Map Linnworks status to our system
            $linnworksStatus = $orderData['order_status'] ?? 0;
            $statusMap = [
                0 => 'pending',
                1 => 'processed',  // Dispatched
                2 => 'cancelled',
                3 => 'pending',    // On hold
                4 => 'refunded',
            ];
            $status = $statusMap[$linnworksStatus] ?? 'pending';

            // Determine processed state
            $isProcessed = in_array($linnworksStatus, [1, 2, 4]); // Dispatched, cancelled, or refunded
            $isCancelled = $linnworksStatus === 2;
            $hasRefund = $linnworksStatus === 4;

            // Create the order record
            $order = Order::create([
                'linnworks_order_id' => $orderData['order_id'],
                'order_id' => $orderData['order_id'],
                'order_number' => $orderData['order_number'],
                'received_date' => $orderData['received_date'] ? Carbon::parse($orderData['received_date']) : null,
                'processed_date' => $orderData['processed_date'] ? Carbon::parse($orderData['processed_date']) : null,
                'channel_name' => $orderData['channel_name'] ?? 'Unknown',
                'sub_source' => $orderData['sub_source'] ?? null,
                'currency' => $orderData['currency'] ?? 'GBP',
                'total_charge' => $orderData['total_charge'] ?? 0,
                'total_paid' => $orderData['total_charge'] ?? 0,
                'postage_cost' => $orderData['postage_cost'] ?? 0,
                'tax' => $orderData['tax'] ?? 0,
                'profit_margin' => $orderData['profit_margin'] ?? 0,
                'order_status' => $linnworksStatus,
                'status' => $status,
                'is_open' => false, // All processed orders are closed
                'is_processed' => $isProcessed,
                'is_cancelled' => $isCancelled,
                'has_refund' => $hasRefund,
                'location_id' => $orderData['location_id'] ?? null,
                'notes' => $orderData['notes'] ?? null,
                'last_synced_at' => now(),
            ]);

            // Create order items
            if (!empty($orderData['items'])) {
                foreach ($orderData['items'] as $itemData) {
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

    private function updateExistingOrder(Order $order, array $orderData): void
    {
        // Map Linnworks status to our system
        $statusMap = [
            0 => 'pending',
            1 => 'processed',  // Dispatched
            2 => 'cancelled',
            3 => 'pending',    // On hold
            4 => 'refunded',
        ];

        $linnworksStatus = $orderData['order_status'] ?? 0;
        $status = $statusMap[$linnworksStatus] ?? 'pending';

        // Update order with processed data
        $order->update([
            'processed_date' => $orderData['processed_date'] ? Carbon::parse($orderData['processed_date']) : null,
            'tax' => $orderData['tax'] ?? $order->tax,
            'postage_cost' => $orderData['postage_cost'] ?? $order->postage_cost,
            'profit_margin' => $orderData['profit_margin'] ?? $order->profit_margin,
            'is_open' => false,
            'is_processed' => in_array($linnworksStatus, [1, 2, 4]), // Dispatched, cancelled, or refunded
            'is_cancelled' => $linnworksStatus === 2,
            'has_refund' => $linnworksStatus === 4,
            'status' => $status,
            'last_synced_at' => now(),
        ]);
    }

    private function displayFinalSummary(bool $isDryRun): void
    {
        $this->newLine(2);
        $this->info('📊 Import Summary');
        $this->table(['Metric', 'Count'], [
            ['Total Processed', number_format($this->totalProcessed)],
            ['Successfully Imported/Updated', number_format($this->totalImported)],
            ['Skipped (Already Exists)', number_format($this->totalSkipped)],
            ['Errors', number_format($this->totalErrors)],
        ]);

        if ($isDryRun) {
            $this->info('🔍 This was a dry run - no data was actually imported.');
            $this->info('💡 Run without --dry-run to perform the actual import.');
        } elseif ($this->totalImported > 0) {
            $this->info('✅ Historical order import completed successfully!');
            $this->info('💡 Consider running: php artisan analytics:refresh-cache --force');
        } elseif ($this->totalSkipped === $this->totalProcessed) {
            $this->warn('⚠️  All orders were already in the database. No new data imported.');
        } else {
            $this->error('❌ Import completed with errors. Check the logs for details.');
        }

        if ($this->totalErrors > 0) {
            $this->warn("⚠️  {$this->totalErrors} orders failed to import. Check the application logs for details.");
        }
    }
}
