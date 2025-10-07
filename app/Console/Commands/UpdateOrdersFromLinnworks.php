<?php

namespace App\Console\Commands;

use App\DataTransferObjects\LinnworksOrder;
use App\Services\LinnworksApiService;
use App\Models\Order;
use App\Models\OrderItem;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UpdateOrdersFromLinnworks extends Command
{
    protected $signature = 'orders:update
                            {--from= : Start date (YYYY-MM-DD)}
                            {--to= : End date (YYYY-MM-DD)}
                            {--days=90 : Number of days back (if no dates provided)}
                            {--batch-size=200 : Orders per API batch}
                            {--only-missing : Only update orders with missing data}
                            {--reimport : Delete and re-import orders (use with caution)}
                            {--dry-run : Preview changes without saving}
                            {--force : Skip confirmation}';

    protected $description = 'Update existing orders from Linnworks (fixes processed_date, profit_margin, etc.)';

    private int $totalFetched = 0;
    private int $totalUpdated = 0;
    private int $totalReimported = 0;
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
        $batchSize = min((int) $this->option('batch-size'), 200);
        $isDryRun = $this->option('dry-run');
        $isReimport = $this->option('reimport');
        $onlyMissing = $this->option('only-missing');

        $this->info('🔄 Updating orders from Linnworks...');
        $this->table(['Setting', 'Value'], [
            ['From Date', $dateRange['from']->toDateString()],
            ['To Date', $dateRange['to']->toDateString()],
            ['Batch Size', $batchSize],
            ['Mode', $isDryRun ? 'DRY RUN' : 'LIVE UPDATE'],
            ['Strategy', $isReimport ? '🔴 RE-IMPORT (destructive)' : 'UPDATE (merge)'],
            ['Filter', $onlyMissing ? 'Only missing data' : 'All orders'],
        ]);

        if ($isReimport && !$isDryRun) {
            $this->warn('⚠️  RE-IMPORT mode will DELETE existing orders and re-import them!');
        }

        if (!$this->option('force') && !$this->confirm('Continue?')) {
            $this->info('Operation cancelled.');
            return self::SUCCESS;
        }

        $success = $this->processOrders(
            $dateRange['from'],
            $dateRange['to'],
            $batchSize,
            $isDryRun,
            $isReimport,
            $onlyMissing
        );

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
                'from' => Carbon::parse($from)->startOfDay(),
                'to' => Carbon::parse($to)->endOfDay(),
            ];
        } elseif ($from) {
            return [
                'from' => Carbon::parse($from)->startOfDay(),
                'to' => Carbon::now()->endOfDay(),
            ];
        } elseif ($to) {
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

    private function processOrders(
        Carbon $from,
        Carbon $to,
        int $batchSize,
        bool $isDryRun,
        bool $isReimport,
        bool $onlyMissing
    ): bool {
        $this->info('📥 Fetching orders from Linnworks API...');

        try {
            $pageNumber = 1;
            $hasMore = true;

            while ($hasMore) {
                $result = $this->apiService->getProcessedOrders($from, $to, $pageNumber, $batchSize);

                if ($result->orders->isEmpty()) {
                    if ($pageNumber === 1) {
                        $this->warn('⚠️  No orders returned from API for this date range.');
                    }
                    break;
                }

                $this->info("📄 Processing page {$pageNumber} ({$result->orders->count()} orders)...");

                foreach ($result->orders as $linnworksOrder) {
                    $this->totalFetched++;

                    try {
                        if ($isReimport) {
                            $this->reimportOrder($linnworksOrder, $isDryRun);
                        } else {
                            $this->updateOrder($linnworksOrder, $isDryRun, $onlyMissing);
                        }

                        if ($this->totalFetched % 100 === 0) {
                            $this->info("📈 Progress: {$this->totalFetched} fetched, {$this->totalUpdated} updated");
                        }

                    } catch (\Exception $e) {
                        $this->totalErrors++;
                        $this->error("❌ Error processing order {$linnworksOrder->orderNumber}: {$e->getMessage()}");
                        Log::error('Failed to update order', [
                            'order_id' => $linnworksOrder->orderId,
                            'order_number' => $linnworksOrder->orderNumber,
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString(),
                        ]);
                    }
                }

                $hasMore = $result->hasMorePages;
                $pageNumber++;
            }

            $this->info("✅ Completed processing {$this->totalFetched} orders from API");
            return true;

        } catch (\Exception $e) {
            $this->error("❌ Fatal error: {$e->getMessage()}");
            Log::error('Order update failed', [
                'error' => $e->getMessage(),
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ]);
            return false;
        }
    }

    private function updateOrder(LinnworksOrder $linnworksOrder, bool $isDryRun, bool $onlyMissing): void
    {
        // Find existing order
        $order = Order::where('linnworks_order_id', $linnworksOrder->orderId)
            ->orWhere('order_number', $linnworksOrder->orderNumber)
            ->first();

        if (!$order) {
            $this->totalSkipped++;
            if ($this->output->isVerbose()) {
                $this->line("  ⏭️  Skipped {$linnworksOrder->orderNumber} (not in database)");
            }
            return;
        }

        // Build update data
        $updateData = $this->buildUpdateData($order, $linnworksOrder, $onlyMissing);

        if (empty($updateData)) {
            $this->totalSkipped++;
            return;
        }

        if ($isDryRun) {
            $fields = implode(', ', array_keys($updateData));
            $this->line("  [DRY RUN] Would update order {$order->order_number}: {$fields}");
            $this->totalUpdated++;
            return;
        }

        // Perform update
        $order->update(array_merge($updateData, [
            'last_synced_at' => now(),
        ]));

        $this->totalUpdated++;
    }

    private function reimportOrder(LinnworksOrder $linnworksOrder, bool $isDryRun): void
    {
        $orderData = $linnworksOrder->toArray();

        if ($isDryRun) {
            $this->line("  [DRY RUN] Would re-import order {$linnworksOrder->orderNumber}");
            $this->totalReimported++;
            return;
        }

        DB::transaction(function () use ($orderData, $linnworksOrder) {
            // Delete existing order and items
            Order::where('linnworks_order_id', $orderData['order_id'])
                ->orWhere('order_number', $orderData['order_number'])
                ->delete();

            // Re-create order
            $statusMap = [
                0 => 'pending',
                1 => 'processed',  // Dispatched
                2 => 'cancelled',
                3 => 'pending',    // On hold
                4 => 'refunded',
            ];

            $linnworksStatus = $orderData['order_status'] ?? 0;
            $status = $statusMap[$linnworksStatus] ?? 'pending';

            $order = Order::create([
                'linnworks_order_id' => $orderData['order_id'],
                'order_id' => $orderData['order_id'],
                'order_number' => $orderData['order_number'],
                'received_date' => $orderData['received_date'] ? Carbon::parse($orderData['received_date']) : null,
                'processed_date' => $orderData['processed_date'] ? Carbon::parse($orderData['processed_date']) : null,
                'channel_name' => $orderData['order_source'] ?? 'Unknown',
                'sub_source' => $orderData['subsource'] ?? null,
                'currency' => $orderData['currency'] ?? 'GBP',
                'total_charge' => $orderData['total_charge'] ?? 0,
                'total_paid' => $orderData['total_charge'] ?? 0,
                'postage_cost' => $orderData['postage_cost'] ?? 0,
                'tax' => $orderData['tax'] ?? 0,
                'profit_margin' => $orderData['profit_margin'] ?? 0,
                'order_status' => $linnworksStatus,
                'status' => $status,
                'is_open' => false,
                'is_processed' => in_array($linnworksStatus, [1, 2, 4]),
                'is_cancelled' => $linnworksStatus === 2,
                'has_refund' => $linnworksStatus === 4,
                'location_id' => $orderData['location_id'] ?? null,
                'last_synced_at' => now(),
            ]);

            // Re-create items
            if (!empty($orderData['items'])) {
                foreach ($orderData['items'] as $itemData) {
                    OrderItem::create([
                        'order_id' => $order->id,
                        'item_id' => $itemData['item_id'] ?? null,
                        'sku' => $itemData['sku'] ?? null,
                        'item_title' => $itemData['item_title'] ?? null,
                        'quantity' => $itemData['quantity'] ?? 0,
                        'unit_cost' => $itemData['unit_cost'] ?? 0,
                        'price_per_unit' => $itemData['price_per_unit'] ?? 0,
                        'line_total' => $itemData['line_total'] ?? 0,
                        'category_name' => $itemData['category_name'] ?? null,
                    ]);
                }
            }
        });

        $this->totalReimported++;
    }

    private function buildUpdateData(Order $order, LinnworksOrder $linnworksOrder, bool $onlyMissing): array
    {
        $updateData = [];

        // Processed date
        if ($linnworksOrder->processedDate) {
            if (!$onlyMissing || !$order->processed_date) {
                $updateData['processed_date'] = $linnworksOrder->processedDate;
            }
        }

        // Profit margin
        if ($linnworksOrder->profitMargin > 0) {
            if (!$onlyMissing || $order->profit_margin == 0) {
                $updateData['profit_margin'] = $linnworksOrder->profitMargin;
            }
        }

        // Tax
        if ($linnworksOrder->tax > 0) {
            if (!$onlyMissing || $order->tax == 0) {
                $updateData['tax'] = $linnworksOrder->tax;
            }
        }

        // Postage cost
        if ($linnworksOrder->postageCost > 0) {
            if (!$onlyMissing || $order->postage_cost == 0) {
                $updateData['postage_cost'] = $linnworksOrder->postageCost;
            }
        }

        // Channel name
        if ($linnworksOrder->orderSource) {
            if (!$onlyMissing || !$order->channel_name || $order->channel_name === 'Unknown') {
                $updateData['channel_name'] = $linnworksOrder->orderSource;
            }
        }

        // Subsource
        if ($linnworksOrder->subsource) {
            if (!$onlyMissing || !$order->sub_source) {
                $updateData['sub_source'] = $linnworksOrder->subsource;
            }
        }

        return $updateData;
    }

    private function displaySummary(bool $isDryRun): void
    {
        $this->newLine(2);
        $this->info('📊 Update Summary');
        $this->table(['Metric', 'Count'], [
            ['Orders Fetched from API', number_format($this->totalFetched)],
            ['Orders Updated', number_format($this->totalUpdated)],
            ['Orders Re-imported', number_format($this->totalReimported)],
            ['Orders Skipped', number_format($this->totalSkipped)],
            ['Errors', number_format($this->totalErrors)],
        ]);

        if ($isDryRun) {
            $this->info('🔍 This was a dry run - no changes were made.');
            $this->info('💡 Run without --dry-run to apply updates.');
        } elseif ($this->totalUpdated > 0 || $this->totalReimported > 0) {
            $this->info('✅ Orders updated successfully!');
        } else {
            $this->warn('⚠️  No orders needed updating.');
        }

        if ($this->totalErrors > 0) {
            $this->warn("⚠️  {$this->totalErrors} errors occurred. Check logs for details.");
        }
    }
}
