<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\SyncLog;
use App\Services\Linnworks\Orders\ProcessedOrdersService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CheckProcessedStatus extends Command
{
    protected $signature = 'sync:check-processed
                            {--limit=100 : Number of orders to check}
                            {--dry-run : Show what would be checked without making changes}';

    protected $description = 'Check if open orders have been processed (oldest first)';

    public function handle(ProcessedOrdersService $processedOrdersService): int
    {
        $limit = (int) $this->option('limit');
        $isDryRun = $this->option('dry-run');

        $this->info('🔍 Checking processed order status...');

        if ($isDryRun) {
            $this->warn('🔍 DRY RUN MODE - No database changes will be made');
        }

        // Get oldest open/pending orders (status = 0)
        $openOrders = Order::where('status', 0)
            ->whereNotNull('order_id')
            ->where('received_at', '>=', now()->subDays(30)) // Only check orders from last 30 days
            ->orderBy('received_at', 'asc') // Oldest first!
            ->limit($limit)
            ->get(['id', 'order_id', 'number', 'received_at', 'source']);

        if ($openOrders->isEmpty()) {
            $this->info('✨ No open orders to check');

            return self::SUCCESS;
        }

        $this->info("📋 Checking {$openOrders->count()} oldest open orders...");

        if ($isDryRun) {
            $this->table(
                ['Order Number', 'Order ID', 'Received', 'Channel'],
                $openOrders->map(fn ($o) => [
                    $o->number,
                    substr($o->order_id, 0, 8).'...',
                    $o->received_at->format('Y-m-d H:i'),
                    $o->source,
                ])->toArray()
            );

            return self::SUCCESS;
        }

        // Start sync log
        $syncLog = SyncLog::startSync('check_processed', [
            'orders_to_check' => $openOrders->count(),
        ]);

        $updatedCount = 0;
        $notFoundCount = 0;
        $errorCount = 0;

        $progressBar = $this->output->createProgressBar($openOrders->count());
        $progressBar->start();

        foreach ($openOrders as $order) {
            try {
                // Query ProcessedOrders API for this specific order
                $response = $processedOrdersService->getProcessedOrderById(
                    userId: 1,
                    orderId: $order->order_id
                );

                if ($response->isError()) {
                    // Order not found in processed orders = still open
                    $notFoundCount++;
                    $progressBar->advance();

                    continue;
                }

                // Order found! It's been processed!
                $processedData = $response->getData()->toArray();

                // Update the order with processed data
                $order->update([
                    'status' => 1, // processed
                    'processed_at' => now(),
                    'total_charge' => $processedData['TotalValue'] ?? 0,
                    'postage_cost' => $processedData['PostageCost'] ?? 0,
                    'tax' => $processedData['Tax'] ?? 0,
                    'profit_margin' => $processedData['Profit'] ?? 0,
                ]);

                $updatedCount++;

                Log::info('Order transitioned to processed', [
                    'number' => $order->number,
                    'order_id' => $order->order_id,
                    'total_charge' => $processedData['TotalValue'] ?? 0,
                ]);

            } catch (\Exception $e) {
                $errorCount++;
                Log::error('Error checking order processed status', [
                    'order_id' => $order->order_id,
                    'error' => $e->getMessage(),
                ]);
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        // Complete sync log
        $syncLog->update([
            'status' => 'completed',
            'completed_at' => now(),
            'total_fetched' => $openOrders->count(),
            'total_updated' => $updatedCount,
            'total_skipped' => $notFoundCount,
            'total_failed' => $errorCount,
            'metadata' => array_merge($syncLog->metadata ?? [], [
                'orders_checked' => $openOrders->count(),
                'updated_to_processed' => $updatedCount,
                'still_open' => $notFoundCount,
                'errors' => $errorCount,
            ]),
        ]);

        // Display summary
        $this->info('✅ Check completed!');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Orders Checked', $openOrders->count()],
                ['Updated to Processed', $updatedCount],
                ['Still Open', $notFoundCount],
                ['Errors', $errorCount],
            ]
        );

        if ($updatedCount > 0) {
            $this->info("🎉 Successfully updated {$updatedCount} orders to processed status!");
        }

        return self::SUCCESS;
    }
}
