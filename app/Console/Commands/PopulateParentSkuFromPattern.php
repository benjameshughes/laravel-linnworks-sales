<?php

namespace App\Console\Commands;

use App\Models\OrderItem;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PopulateParentSkuFromPattern extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:parent-sku-from-pattern
                            {--dry-run : Show what would be updated without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Populate parent_sku by extracting from child SKU pattern (e.g., "005-001" → "005")';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        $this->info('Populating parent_sku from SKU patterns...');
        $this->newLine();

        if ($dryRun) {
            $this->warn('DRY RUN MODE - No changes will be made');
            $this->newLine();
        }

        // Get all distinct SKUs with dashes (variation items)
        $this->info('Finding all variation item SKUs...');
        $skus = OrderItem::whereNotNull('sku')
            ->where('sku', 'like', '%-%')
            ->distinct()
            ->pluck('sku');

        $this->info("Found {$skus->count()} unique SKUs with variation pattern");
        $this->newLine();

        // Extract parent SKUs
        $this->info('Extracting parent SKUs from pattern...');
        $mappings = $skus->mapWithKeys(function ($sku) {
            // Extract parent by removing everything after last dash
            $parent = preg_replace('/-[^-]+$/', '', $sku);

            return [$sku => $parent];
        });

        $uniqueParents = $mappings->unique()->sort()->values();
        $this->info("Extracted {$uniqueParents->count()} unique parent SKUs");
        $this->newLine();

        // Show sample mappings
        $this->info('Sample mappings:');
        foreach ($mappings->take(10) as $child => $parent) {
            $this->line("  {$child} → {$parent}");
        }
        $this->newLine();

        if ($dryRun) {
            $this->info('Dry run complete. Use without --dry-run to apply changes.');

            return self::SUCCESS;
        }

        // Update order_items in batches using raw SQL for speed
        $this->info('Updating order_items with parent_sku...');

        $updated = 0;
        $progressBar = $this->output->createProgressBar($mappings->count());
        $progressBar->start();

        // Batch update using raw SQL for maximum performance
        foreach ($mappings->chunk(1000) as $batch) {
            // Build CASE statement for bulk update
            $cases = [];
            $skuList = [];

            foreach ($batch as $childSku => $parentSku) {
                $cases[] = 'WHEN '.DB::connection()->getPdo()->quote($childSku).' THEN '.DB::connection()->getPdo()->quote($parentSku);
                $skuList[] = DB::connection()->getPdo()->quote($childSku);
            }

            $sql = '
                UPDATE order_items
                SET parent_sku = CASE sku
                    '.implode("\n                    ", $cases).'
                END
                WHERE sku IN ('.implode(', ', $skuList).')
            ';

            $count = DB::update($sql);
            $updated += $count;

            $progressBar->advance($batch->count());
        }

        $progressBar->finish();
        $this->newLine(2);

        $this->info("✓ Updated {$updated} order items with parent_sku");
        $this->newLine();

        // Display statistics
        $this->table(
            ['Metric', 'Value'],
            [
                ['Unique Child SKUs', number_format($mappings->count())],
                ['Unique Parent SKUs', number_format($uniqueParents->count())],
                ['Order Items Updated', number_format($updated)],
            ]
        );

        return self::SUCCESS;
    }
}
