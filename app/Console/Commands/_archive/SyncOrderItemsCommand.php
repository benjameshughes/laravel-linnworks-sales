<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Console\Command;

class SyncOrderItemsCommand extends Command
{
    protected $signature = 'orders:sync-items
                          {--dry-run : Show what would be synced without making changes}
                          {--force : Force sync even if order already has items}';

    protected $description = 'Sync order items from JSON to OrderItem table for existing orders';

    public function handle()
    {
        $this->info('🔄 Syncing order items from JSON to OrderItem table...');

        $dryRun = $this->option('dry-run');
        $force = $this->option('force');

        if ($dryRun) {
            $this->warn('🔍 DRY RUN MODE - No database changes will be made');
        }

        // Get orders that have JSON items but no OrderItem records
        $query = Order::whereNotNull('items')
            ->where('items', '!=', '[]')
            ->where('items', '!=', 'null');

        if (!$force) {
            $query->whereDoesntHave('orderItems');
        }

        $orders = $query->get();

        $this->info("📊 Found {$orders->count()} orders to sync");

        if ($orders->isEmpty()) {
            $this->info('✨ No orders need syncing');
            return 0;
        }

        $progressBar = $this->output->createProgressBar($orders->count());
        $progressBar->start();

        $synced = 0;
        $errors = 0;

        foreach ($orders as $order) {
            try {
                $items = $order->items ?? [];
                
                if (empty($items)) {
                    $progressBar->advance();
                    continue;
                }

                if (!$dryRun) {
                    // Clear existing items if force sync
                    if ($force) {
                        $order->orderItems()->delete();
                    }

                    // Create order items
                    foreach ($items as $item) {
                        $sku = $item['sku'] ?? null;
                        $itemTitle = $item['item_title'] ?? null;
                        
                        // If no item title, try to get it from Product model
                        if (empty($itemTitle) && !empty($sku)) {
                            $product = \App\Models\Product::where('sku', $sku)->first();
                            $itemTitle = $product?->title ?? "Product {$sku}";
                        }
                        
                        // Fallback to a default title if still empty
                        if (empty($itemTitle)) {
                            $itemTitle = 'Unknown Product';
                        }
                        
                        OrderItem::create([
                            'order_id' => $order->id,
                            'item_id' => $item['item_id'] ?? null,
                            'sku' => $sku,
                            'item_title' => $itemTitle,
                            'quantity' => $item['quantity'] ?? 0,
                            'unit_cost' => $item['unit_cost'] ?? 0,
                            'price_per_unit' => $item['price_per_unit'] ?? 0,
                            'line_total' => $item['line_total'] ?? 0,
                            'category_name' => $item['category_name'] ?? null,
                        ]);
                    }
                }

                $synced++;
            } catch (\Exception $e) {
                $errors++;
                $this->newLine();
                $this->error("❌ Error syncing order {$order->id}: " . $e->getMessage());
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine();

        if ($dryRun) {
            $this->info("🔍 Would sync {$synced} orders");
        } else {
            $this->info("✅ Successfully synced {$synced} orders");
        }

        if ($errors > 0) {
            $this->error("❌ {$errors} orders had errors");
        }

        // Show some statistics
        $this->newLine();
        $this->info('📈 Statistics:');
        $this->line("   Total OrderItem records: " . OrderItem::count());
        $this->line("   Orders with items: " . Order::whereHas('orderItems')->count());
        $this->line("   Orders without items: " . Order::whereDoesntHave('orderItems')->count());

        return 0;
    }
}