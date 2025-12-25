<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * One-time command to fix product titles from order_items data.
 *
 * The products table was populated with "Unknown Product" placeholders.
 * This command updates them using real titles and stock_item_ids from order_items.
 */
final class FixProductTitlesFromOrderItems extends Command
{
    protected $signature = 'products:fix-titles {--dry-run : Show what would be updated without making changes}';

    protected $description = 'Fix product titles using data from order_items table';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        $this->info($dryRun ? '🔍 Dry run - no changes will be made' : '🔧 Fixing product titles...');

        // First, get products that need fixing
        $productsToFix = DB::table('products')
            ->where('title', 'Unknown Product')
            ->orWhere(fn ($q) => $q->where('linnworks_id', 'like', 'UNKNOWN_%'))
            ->pluck('sku')
            ->toArray();

        $this->info('Products needing fix: '.count($productsToFix));

        if (empty($productsToFix)) {
            $this->info('✅ No products need fixing!');

            return self::SUCCESS;
        }

        // Get unique SKU data from order_items using aggregation (memory efficient)
        $this->info('Querying order_items for product data...');

        $orderItemData = DB::table('order_items')
            ->select('sku')
            ->selectRaw('MAX(item_title) as item_title')
            ->selectRaw('MAX(stock_item_id) as stock_item_id')
            ->selectRaw('MAX(category_name) as category_name')
            ->whereIn('sku', $productsToFix)
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->groupBy('sku')
            ->get();

        $this->info("Found {$orderItemData->count()} SKUs with data in order_items");

        $updated = 0;
        $skipped = 0;

        $progressBar = $this->output->createProgressBar($orderItemData->count());
        $progressBar->start();

        foreach ($orderItemData as $item) {
            $progressBar->advance();

            // Skip if no useful data
            if (empty($item->item_title) && empty($item->stock_item_id)) {
                $skipped++;

                continue;
            }

            // Build update data
            $updateData = [];

            if (! empty($item->item_title)) {
                $updateData['title'] = $item->item_title;
            }

            if (! empty($item->stock_item_id)) {
                $updateData['linnworks_id'] = $item->stock_item_id;
            }

            if (! empty($item->category_name)) {
                $updateData['category_name'] = $item->category_name;
            }

            if (empty($updateData)) {
                $skipped++;

                continue;
            }

            if (! $dryRun) {
                try {
                    $updateData['updated_at'] = now();
                    DB::table('products')
                        ->where('sku', $item->sku)
                        ->update($updateData);
                } catch (\Illuminate\Database\UniqueConstraintViolationException) {
                    // linnworks_id conflict - update without it
                    unset($updateData['linnworks_id']);
                    if (! empty($updateData)) {
                        DB::table('products')
                            ->where('sku', $item->sku)
                            ->update($updateData);
                    }
                }
            }

            $updated++;
        }

        $progressBar->finish();
        $this->newLine(2);

        $this->info("✅ Updated: {$updated}");
        $this->info("⏭️  Skipped (no data): {$skipped}");

        if ($dryRun && $updated > 0) {
            $this->newLine();
            $this->warn('Run without --dry-run to apply these changes');
        }

        return self::SUCCESS;
    }
}
