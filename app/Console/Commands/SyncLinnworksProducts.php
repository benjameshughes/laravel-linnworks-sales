<?php

namespace App\Console\Commands;

use App\Actions\Linnworks\Products\ImportProducts;
use App\Services\LinnworksApiService;
use Illuminate\Console\Command;

class SyncLinnworksProducts extends Command
{
    protected $signature = 'sync:linnworks-products
                            {--limit=1000 : Maximum number of products to sync}
                            {--force : Force update even if products exist}';

    protected $description = 'Sync products/inventory from Linnworks API to local database';

    public function handle(LinnworksApiService $linnworksService, ImportProducts $importProducts): int
    {
        $limit = (int) $this->option('limit');
        $force = $this->option('force');

        $this->info('Starting Linnworks products sync...');

        if (!$linnworksService->isConfigured()) {
            $this->error('Linnworks API is not configured. Please check your credentials in .env file.');
            return self::FAILURE;
        }

        // Fetch products from Linnworks using the detailed endpoint
        $this->info('Fetching products from Linnworks API...');
        $products = $linnworksService->getAllInventoryItemsFull();

        $this->info("Found {$products->count()} products");

        if ($products->isEmpty()) {
            $this->warn('No products found to sync.');
            return self::SUCCESS;
        }

        // Limit products if needed
        if ($products->count() > $limit) {
            $this->info("Limiting to first {$limit} products");
            $products = $products->take($limit);
        }

        // Import products
        $this->info('Importing products to database...');
        $result = $importProducts->handle($products, $force);

        // Display results
        $this->newLine();
        $this->info('Sync completed!');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Processed', $result['processed']],
                ['Created', $result['created']],
                ['Updated', $result['updated']],
                ['Skipped', $result['skipped']],
                ['Failed', $result['failed']],
            ]
        );

        if ($result['failed'] > 0) {
            $this->warn('Some products failed to import. Check logs tagged with "Failed to persist Linnworks product".');
        }

        return self::SUCCESS;
    }
}
