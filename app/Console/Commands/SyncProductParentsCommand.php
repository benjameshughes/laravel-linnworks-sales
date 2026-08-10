<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Actions\Linnworks\SyncProductParents;

final class SyncProductParentsCommand extends Command
{
    protected $signature = 'sync:product-parents
                            {--all : Re-link every child, not just products without a parent}';

    protected $description = 'Sync Linnworks variation groups and link each child product to its parent';

    public function handle(SyncProductParents $syncProductParents): int
    {
        $this->info('Syncing variation groups from Linnworks...');

        $result = $syncProductParents(onlyUnlinked: ! $this->option('all'));

        $this->newLine();
        $this->table(
            ['Metric', 'Value'],
            [
                ['Variation groups', number_format($result['groups'])],
                ['Products linked', number_format($result['linked'])],
                ['Children with no local product', number_format($result['unmatched'])],
            ],
        );

        if ($result['unmatched'] > 0) {
            $this->warn("{$result['unmatched']} variation children have no matching product locally. Run sync:products first.");
        }

        return self::SUCCESS;
    }
}
