<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MigrateOrderItemsToTable extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'orders:migrate-items 
                            {--batch=100 : Number of orders to process at once}
                            {--dry-run : Run without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate order items from JSON column to order_items table';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $batchSize = (int) $this->option('batch');
        $isDryRun = $this->option('dry-run');
        
        if ($isDryRun) {
            $this->info('Running in DRY RUN mode - no changes will be made');
        }
        
        $this->info('Starting order items migration...');
        
        // Get total count of orders with items
        $totalOrders = Order::whereNotNull('items')->count();
        
        if ($totalOrders === 0) {
            $this->info('No orders with items found to migrate.');
            return Command::SUCCESS;
        }
        
        $this->info("Found {$totalOrders} orders with items to migrate");
        
        $progressBar = $this->output->createProgressBar($totalOrders);
        $progressBar->start();
        
        $migratedCount = 0;
        $errorCount = 0;
        
        // Process orders in batches
        Order::whereNotNull('items')
            ->chunk($batchSize, function($orders) use (&$migratedCount, &$errorCount, $isDryRun, $progressBar) {
                foreach ($orders as $order) {
                    try {
                        // Check if items already migrated
                        if (!$isDryRun && $order->orderItems()->exists()) {
                            $progressBar->advance();
                            continue;
                        }
                        
                        $items = is_string($order->items) ? json_decode($order->items, true) : $order->items;
                        
                        if (!is_array($items) || empty($items)) {
                            $progressBar->advance();
                            continue;
                        }
                        
                        if (!$isDryRun) {
                            DB::transaction(function() use ($order, $items) {
                                foreach ($items as $item) {
                                    OrderItem::create([
                                        'order_id' => $order->id,
                                        'item_id' => $item['item_id'] ?? null,
                                        'sku' => $item['sku'] ?? '',
                                        'item_title' => $item['item_title'] ?? $item['title'] ?? $item['sku'] ?? 'Unknown Item',
                                        'quantity' => (int) ($item['quantity'] ?? 0),
                                        'unit_cost' => (float) ($item['unit_cost'] ?? 0),
                                        'price_per_unit' => (float) ($item['price_per_unit'] ?? 0),
                                        'line_total' => (float) ($item['line_total'] ?? 0) ?: ((float) ($item['price_per_unit'] ?? 0) * (int) ($item['quantity'] ?? 0)),
                                        'discount_amount' => (float) ($item['discount_amount'] ?? 0),
                                        'tax_amount' => (float) ($item['tax_amount'] ?? 0),
                                        'category_name' => $item['category_name'] ?? null,
                                        'metadata' => array_diff_key($item, array_flip([
                                            'item_id', 'sku', 'item_title', 'title', 'quantity',
                                            'unit_cost', 'price_per_unit', 'line_total',
                                            'discount_amount', 'tax_amount', 'category_name'
                                        ])),
                                    ]);
                                }
                            });
                        }
                        
                        $migratedCount++;
                        
                    } catch (\Exception $e) {
                        $errorCount++;
                        $this->error("Failed to migrate order {$order->id}: " . $e->getMessage());
                        Log::error('Failed to migrate order items', [
                            'order_id' => $order->id,
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString()
                        ]);
                        
                        // Break after first few errors for debugging
                        if ($errorCount <= 3) {
                            $this->error("Stack trace: " . $e->getTraceAsString());
                        }
                    }
                    
                    $progressBar->advance();
                }
            });
        
        $progressBar->finish();
        $this->newLine(2);
        
        // Summary
        $this->info('Migration Summary:');
        $this->info("✓ Successfully migrated: {$migratedCount} orders");
        if ($errorCount > 0) {
            $this->error("✗ Failed: {$errorCount} orders");
        }
        
        if ($isDryRun) {
            $this->comment('This was a DRY RUN - no changes were made');
            $this->comment('Run without --dry-run to perform the actual migration');
        }
        
        return $errorCount > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}