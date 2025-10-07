<?php

namespace App\Console\Commands;

use App\Services\LinnworksApiService;
use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class UpdateProcessedOrderDetails extends Command
{
    protected $signature = 'orders:update-processed-details
                            {--days=730 : Number of days back to check}
                            {--batch-size=200 : Orders to fetch per batch}
                            {--dry-run : Preview changes without saving}
                            {--force : Skip confirmation}';

    protected $description = 'Update processed_date and profit_margin for existing orders from Linnworks API';

    private int $totalChecked = 0;
    private int $totalUpdated = 0;
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
            $this->error('Linnworks API is not configured.');
            return self::FAILURE;
        }

        $days = (int) $this->option('days');
        $batchSize = min((int) $this->option('batch-size'), 200);
        $isDryRun = $this->option('dry-run');

        $this->info('🔄 Updating processed order details from Linnworks...');
        $this->table(['Setting', 'Value'], [
            ['Days Back', $days],
            ['Batch Size', $batchSize],
            ['Mode', $isDryRun ? 'DRY RUN' : 'LIVE UPDATE'],
        ]);

        if (!$this->option('force') && !$this->confirm('Continue?')) {
            return self::SUCCESS;
        }

        $from = Carbon::now()->subDays($days)->startOfDay();
        $to = Carbon::now()->endOfDay();

        $this->updateOrderDetails($from, $to, $batchSize, $isDryRun);
        $this->displaySummary($isDryRun);

        return self::SUCCESS;
    }

    private function updateOrderDetails(Carbon $from, Carbon $to, int $batchSize, bool $isDryRun): void
    {
        $this->info('📥 Fetching processed orders from Linnworks...');

        try {
            $pageNumber = 1;
            $hasMore = true;

            while ($hasMore) {
                $result = $this->apiService->getProcessedOrders($from, $to, $pageNumber, $batchSize);

                if ($result->orders->isEmpty()) {
                    break;
                }

                $this->info("📄 Processing page {$pageNumber} ({$result->orders->count()} orders)...");

                foreach ($result->orders as $linnworksOrder) {
                    $this->totalChecked++;

                    try {
                        // Find existing order in database
                        $order = Order::where('linnworks_order_id', $linnworksOrder->orderId)
                            ->orWhere('order_number', $linnworksOrder->orderNumber)
                            ->first();

                        if (!$order) {
                            $this->totalSkipped++;
                            continue;
                        }

                        // Check if update is needed
                        $needsUpdate = false;
                        $changes = [];

                        if ($linnworksOrder->processedDate && !$order->processed_date) {
                            $needsUpdate = true;
                            $changes[] = 'processed_date';
                        }

                        if ($linnworksOrder->profitMargin > 0 && $order->profit_margin == 0) {
                            $needsUpdate = true;
                            $changes[] = 'profit_margin';
                        }

                        if (!$needsUpdate) {
                            $this->totalSkipped++;
                            continue;
                        }

                        if ($isDryRun) {
                            $this->line("  [DRY RUN] Would update order {$order->order_number}: " . implode(', ', $changes));
                            $this->totalUpdated++;
                            continue;
                        }

                        // Perform the update
                        $updateData = [];

                        if ($linnworksOrder->processedDate) {
                            $updateData['processed_date'] = $linnworksOrder->processedDate;
                        }

                        if ($linnworksOrder->profitMargin > 0) {
                            $updateData['profit_margin'] = $linnworksOrder->profitMargin;
                        }

                        $order->update($updateData);
                        $this->totalUpdated++;

                        if ($this->totalChecked % 100 === 0) {
                            $this->info("📈 Progress: {$this->totalChecked} checked, {$this->totalUpdated} updated");
                        }

                    } catch (\Exception $e) {
                        $this->totalErrors++;
                        $this->error("❌ Failed to update order: {$e->getMessage()}");
                        Log::error('Failed to update order details', [
                            'order_id' => $linnworksOrder->orderId,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }

                $hasMore = $result->hasMorePages;
                $pageNumber++;
            }

            $this->info("✅ Completed processing {$this->totalChecked} orders");

        } catch (\Exception $e) {
            $this->error("❌ Error: {$e->getMessage()}");
            Log::error('Failed to update order details', [
                'error' => $e->getMessage(),
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ]);
        }
    }

    private function displaySummary(bool $isDryRun): void
    {
        $this->newLine(2);
        $this->info('📊 Update Summary');
        $this->table(['Metric', 'Count'], [
            ['Total Checked', number_format($this->totalChecked)],
            ['Updated', number_format($this->totalUpdated)],
            ['Skipped (No Changes Needed)', number_format($this->totalSkipped)],
            ['Errors', number_format($this->totalErrors)],
        ]);

        if ($isDryRun) {
            $this->info('🔍 This was a dry run - no changes were made.');
            $this->info('💡 Run without --dry-run to apply updates.');
        } elseif ($this->totalUpdated > 0) {
            $this->info('✅ Order details updated successfully!');
        } else {
            $this->warn('⚠️  No orders needed updating.');
        }
    }
}
