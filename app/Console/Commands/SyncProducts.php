<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\SyncProductsJob;
use Illuminate\Console\Command;

final class SyncProducts extends Command
{
    protected $signature = 'sync:products
                            {--max=10000 : Maximum number of products to sync}
                            {--sync : Run synchronously instead of dispatching to queue}';

    protected $description = 'Sync product inventory data from Linnworks (pricing, stock levels, descriptions)';

    public function handle(): int
    {
        $maxProducts = (int) $this->option('max');

        if ($this->option('sync')) {
            $this->info('Running product sync synchronously...');
            SyncProductsJob::dispatchSync(startedBy: 'command', maxProducts: $maxProducts);
            $this->info('Product sync completed.');

            return self::SUCCESS;
        }

        $this->info("Dispatching product sync job (max: {$maxProducts} products)...");

        SyncProductsJob::dispatch(startedBy: 'command', maxProducts: $maxProducts);

        $this->info('Product sync job dispatched successfully.');
        $this->info('Use "php artisan queue:work --queue=medium" to process the job.');

        return self::SUCCESS;
    }
}
