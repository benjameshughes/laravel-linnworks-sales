<?php

namespace App\Console\Commands;

use App\Models\OrderItem;
use App\Services\Linnworks\Products\ProductsService;
use Illuminate\Console\Command;

class SyncVariationGroupMappings extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:variation-mappings
                            {--user-id=1 : The Linnworks user ID to use}
                            {--update-existing : Update existing order items with parent SKU}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync variation group mappings from Linnworks and populate parent_sku in order_items';

    /**
     * Execute the console command.
     */
    public function handle(ProductsService $productsService): int
    {
        $userId = (int) $this->option('user-id');
        $updateExisting = $this->option('update-existing');

        $this->info('Starting variation group mapping sync...');
        $this->newLine();

        // Step 1: Get all variation groups from Linnworks
        $this->info('Fetching all variation groups from Linnworks...');
        $variationGroups = $productsService->getVariationId($userId, 'ParentSKU', '');

        if ($variationGroups->isEmpty()) {
            $this->error('No variation groups found.');

            return self::FAILURE;
        }

        $this->info("Found {$variationGroups->count()} variation groups");
        $this->newLine();

        // Step 2: For each group, get all variation items and build mapping
        $this->info('Fetching variation items for each group...');
        $progressBar = $this->output->createProgressBar($variationGroups->count());
        $progressBar->start();

        $mappings = [];
        $totalItems = 0;

        foreach ($variationGroups as $group) {
            $parentSku = $group['VariationSKU'];
            $variationId = $group['pkVariationItemId'];

            // Get all items in this variation group
            $items = $productsService->getVariationGroupItems($userId, $variationId);

            foreach ($items as $item) {
                $childSku = $item['ItemNumber'];
                $mappings[$childSku] = $parentSku;
                $totalItems++;
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        $this->info("Built mapping for {$totalItems} variation items across {$variationGroups->count()} groups");
        $this->newLine();

        // Step 3: Update order_items table
        if ($updateExisting) {
            $this->info('Updating existing order items with parent_sku...');

            $updated = 0;
            $batchSize = 1000;
            $chunks = array_chunk($mappings, $batchSize, true);

            $progressBar = $this->output->createProgressBar(count($chunks));
            $progressBar->start();

            foreach ($chunks as $batch) {
                foreach ($batch as $childSku => $parentSku) {
                    $count = OrderItem::where('sku', $childSku)
                        ->whereNull('parent_sku')
                        ->update(['parent_sku' => $parentSku]);

                    $updated += $count;
                }

                $progressBar->advance();
            }

            $progressBar->finish();
            $this->newLine(2);

            $this->info("Updated {$updated} order items with parent_sku");
        }

        // Step 4: Store mappings for future use (cache or database table)
        $this->info('Caching variation mappings...');
        cache()->put('variation_group_mappings', $mappings, now()->addDays(7));

        $this->newLine();
        $this->info('✓ Variation group mapping sync completed successfully!');
        $this->newLine();

        // Display some stats
        $this->table(
            ['Metric', 'Value'],
            [
                ['Variation Groups', $variationGroups->count()],
                ['Total Variation Items', $totalItems],
                ['Order Items Updated', $updateExisting ? $updated : 'N/A (use --update-existing)'],
            ]
        );

        return self::SUCCESS;
    }
}
