<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\OrderItem;
use App\Services\LinnworksApiService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EnrichOrderItems extends Command
{
    protected $signature = 'orders:enrich-items
                            {--from= : Start date (YYYY-MM-DD)}
                            {--to= : End date (YYYY-MM-DD)}
                            {--days=90 : Number of days back}
                            {--limit= : Max orders to process}
                            {--only-missing : Only fetch items for orders without items}
                            {--dry-run : Preview without saving}
                            {--force : Skip confirmation}';

    protected $description = 'Fetch and populate order items from Linnworks API';

    private int $totalProcessed = 0;
    private int $totalEnriched = 0;
    private int $totalItemsAdded = 0;
    private int $totalSkipped = 0;
    private int $totalErrors = 0;

    public function __construct(
        private readonly LinnworksApiService $apiService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        if (!$this->apiService->isConfigured()) {
            $this->error('❌ Linnworks API is not configured.');
            return self::FAILURE;
        }

        $dateRange = $this->getDateRange();
        $limit = $this->option('limit') ? (int) $this->option('limit') : null;
        $onlyMissing = $this->option('only-missing');
        $isDryRun = $this->option('dry-run');

        $this->info('🔄 Enriching orders with item-level data...');
        $this->table(['Setting', 'Value'], [
            ['From Date', $dateRange['from']->toDateString()],
            ['To Date', $dateRange['to']->toDateString()],
            ['Max Orders', $limit ?? 'Unlimited'],
            ['Filter', $onlyMissing ? 'Only orders without items' : 'All orders'],
            ['Mode', $isDryRun ? 'DRY RUN' : 'LIVE ENRICHMENT'],
        ]);

        if (!$this->option('force') && !$this->confirm('Continue?')) {
            $this->info('Operation cancelled.');
            return self::SUCCESS;
        }

        $success = $this->enrichOrders($dateRange['from'], $dateRange['to'], $limit, $onlyMissing, $isDryRun);

        $this->displaySummary($isDryRun);

        return $success ? self::SUCCESS : self::FAILURE;
    }

    private function getDateRange(): array
    {
        $from = $this->option('from');
        $to = $this->option('to');
        $days = (int) $this->option('days');

        if ($from && $to) {
            return [
                'from' => \Carbon\Carbon::parse($from)->startOfDay(),
                'to' => \Carbon\Carbon::parse($to)->endOfDay(),
            ];
        } elseif ($from) {
            return [
                'from' => \Carbon\Carbon::parse($from)->startOfDay(),
                'to' => \Carbon\Carbon::now()->endOfDay(),
            ];
        } elseif ($to) {
            return [
                'from' => \Carbon\Carbon::parse($to)->subDays($days)->startOfDay(),
                'to' => \Carbon\Carbon::parse($to)->endOfDay(),
            ];
        }

        return [
            'from' => \Carbon\Carbon::now()->subDays($days)->startOfDay(),
            'to' => \Carbon\Carbon::now()->endOfDay(),
        ];
    }

    private function enrichOrders($from, $to, ?int $limit, bool $onlyMissing, bool $isDryRun): bool
    {
        $this->info('📥 Counting orders to process...');

        try {
            // Get count of orders that need enrichment
            $query = Order::whereBetween('received_date', [$from, $to])
                ->whereNotNull('linnworks_order_id');

            if ($onlyMissing) {
                $query->whereDoesntHave('orderItems');
            }

            $totalCount = $query->count();

            if ($totalCount === 0) {
                $this->warn('⚠️  No orders found matching criteria.');
                return true;
            }

            // Apply limit if specified
            $ordersToProcess = $limit ? min($limit, $totalCount) : $totalCount;

            $this->info("Found {$totalCount} orders without items");
            if ($limit) {
                $this->info("Processing first {$ordersToProcess} orders (limit applied)");
            }
            $this->newLine();

            $progressBar = $this->output->createProgressBar($ordersToProcess);
            $progressBar->start();

            // Process orders in chunks to avoid memory issues
            $query = Order::whereBetween('received_date', [$from, $to])
                ->whereNotNull('linnworks_order_id');

            if ($onlyMissing) {
                $query->whereDoesntHave('orderItems');
            }

            if ($limit) {
                $query->limit($limit);
            }

            $query->orderBy('received_date', 'desc')
                ->chunk(100, function ($orders) use ($progressBar, $isDryRun, &$ordersToProcess) {
                    foreach ($orders as $order) {
                        if ($this->totalProcessed >= $ordersToProcess) {
                            return false; // Stop chunking
                        }

                        $this->totalProcessed++;

                        try {
                            $itemsAdded = $this->enrichOrder($order, $isDryRun);

                            if ($itemsAdded > 0) {
                                $this->totalEnriched++;
                                $this->totalItemsAdded += $itemsAdded;
                            } else {
                                $this->totalSkipped++;
                            }

                            $progressBar->advance();

                            // Rate limiting - respect Linnworks API limits
                            usleep(100000); // 100ms delay = max 10 requests/second

                        } catch (\Exception $e) {
                            $this->totalErrors++;
                            Log::error('Failed to enrich order items', [
                                'order_id' => $order->id,
                                'order_number' => $order->order_number,
                                'linnworks_order_id' => $order->linnworks_order_id,
                                'error' => $e->getMessage(),
                            ]);

                            if ($this->output->isVerbose()) {
                                $this->newLine();
                                $this->error("  ❌ Error: {$order->order_number}: {$e->getMessage()}");
                            }
                        }
                    }
                });

            $progressBar->finish();
            $this->newLine(2);

            $this->info('✅ Enrichment complete');
            return true;

        } catch (\Exception $e) {
            $this->error("❌ Fatal error: {$e->getMessage()}");
            Log::error('Order enrichment failed', [
                'error' => $e->getMessage(),
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ]);
            return false;
        }
    }

    private function enrichOrder(Order $order, bool $isDryRun): int
    {
        // Check if order already has items (unless we're refreshing)
        if ($order->orderItems()->exists() && $this->option('only-missing')) {
            return 0;
        }

        // Fetch items from Linnworks API using GetOrderItems endpoint
        $items = $this->fetchOrderItems($order->linnworks_order_id);

        if (empty($items)) {
            return 0;
        }

        if ($isDryRun) {
            return count($items);
        }

        // Save items to database
        DB::transaction(function () use ($order, $items) {
            // Delete existing items if refreshing
            if (!$this->option('only-missing')) {
                $order->orderItems()->delete();
            }

            foreach ($items as $itemData) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'item_id' => $itemData['RowId'] ?? null,
                    'sku' => $itemData['SKU'] ?? null,
                    'item_title' => $itemData['ItemTitle'] ?? $itemData['Title'] ?? null,
                    'quantity' => $itemData['Quantity'] ?? 0,
                    'unit_cost' => $itemData['Cost'] ?? $itemData['UnitCost'] ?? 0,
                    'price_per_unit' => $itemData['PricePerUnit'] ?? 0,
                    'line_total' => $itemData['LineTotal'] ?? ($itemData['PricePerUnit'] ?? 0) * ($itemData['Quantity'] ?? 0),
                    'category_name' => $itemData['CategoryName'] ?? null,
                ]);
            }
        });

        return count($items);
    }

    private function fetchOrderItems(string $orderUuid): array
    {
        try {
            // Use the existing API service to fetch order details
            $orderDetails = $this->apiService->getOrderDetails($orderUuid);

            if (!$orderDetails) {
                return [];
            }

            // Extract items from the order details
            return $orderDetails->items->toArray();

        } catch (\Exception $e) {
            Log::warning('Failed to fetch order items from API', [
                'order_uuid' => $orderUuid,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    private function displaySummary(bool $isDryRun): void
    {
        $this->newLine();
        $this->info('📊 Enrichment Summary');
        $this->table(['Metric', 'Count'], [
            ['Orders Processed', number_format($this->totalProcessed)],
            ['Orders Enriched', number_format($this->totalEnriched)],
            ['Total Items Added', number_format($this->totalItemsAdded)],
            ['Orders Skipped (No Items)', number_format($this->totalSkipped)],
            ['Errors', number_format($this->totalErrors)],
        ]);

        if ($this->totalEnriched > 0) {
            $avgItemsPerOrder = $this->totalItemsAdded / $this->totalEnriched;
            $this->info(sprintf('Average items per order: %.1f', $avgItemsPerOrder));
        }

        $this->newLine();

        if ($isDryRun) {
            $this->info('🔍 This was a dry run - no changes were made.');
            $this->info('💡 Run without --dry-run to save items to database.');
        } elseif ($this->totalEnriched > 0) {
            $this->info('✅ Order items enriched successfully!');
            $coveragePercent = ($this->totalEnriched / $this->totalProcessed) * 100;
            $this->info(sprintf('Item coverage: %.1f%% of processed orders', $coveragePercent));
        } else {
            $this->warn('⚠️  No items were added to any orders.');
            $this->info('This may be normal if:');
            $this->line('  - Orders are too old (Linnworks may not have item history)');
            $this->line('  - Orders already have items (use without --only-missing to refresh)');
            $this->line('  - API returned no item data for these orders');
        }

        if ($this->totalErrors > 0) {
            $this->warn("⚠️  {$this->totalErrors} errors occurred. Check logs for details.");
        }
    }
}
