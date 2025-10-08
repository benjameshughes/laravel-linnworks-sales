<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Linnworks\Inventory\BatchInventoryService;
use App\ValueObjects\Inventory\InventoryItem;
use Illuminate\Console\Command;

class TestBatchInventory extends Command
{
    protected $signature = 'test:batch-inventory
                            {--dry-run : Show what would be done without making changes}';

    protected $description = 'Test batch inventory operations with value objects';

    public function handle(BatchInventoryService $batchService): int
    {
        $this->info('🧪 Testing Batch Inventory Operations');
        $this->newLine();

        // Create test items using value objects
        $this->info('1️⃣  Creating test inventory items (value objects)...');

        $items = collect([
            InventoryItem::fromArray([
                'sku' => 'BATCH-TEST-' . time() . '-001',
                'title' => 'Batch Test Product 1',
                'barcode' => 'TEST-BARCODE-001',
                'purchase_price' => 10.00,
                'retail_price' => 19.99,
                'stock_level' => 100,
                'category_name' => 'Test Category',
                'weight' => 0.5,
            ]),
            InventoryItem::fromArray([
                'sku' => 'BATCH-TEST-' . time() . '-002',
                'title' => 'Batch Test Product 2',
                'barcode' => 'TEST-BARCODE-002',
                'purchase_price' => 15.00,
                'retail_price' => 29.99,
                'stock_level' => 50,
                'category_name' => 'Test Category',
                'weight' => 0.75,
            ]),
        ]);

        $this->info("✅ Created {$items->count()} test items");
        $this->newLine();

        // Validate items
        $this->info('2️⃣  Validating items...');

        $allValid = true;
        foreach ($items as $item) {
            $errors = $item->validate();
            if (empty($errors)) {
                $this->line("  ✅ {$item->sku}: Valid");
            } else {
                $this->error("  ❌ {$item->sku}: " . implode(', ', $errors));
                $allValid = false;
            }
        }

        if (!$allValid) {
            $this->error('Validation failed!');
            return self::FAILURE;
        }

        $this->newLine();

        // Show API format
        $this->info('3️⃣  API format preview:');
        $sample = $items->first();
        $this->table(
            ['Field', 'Value'],
            collect($sample->toApiFormat())->map(fn ($value, $key) => [$key, $value])->toArray()
        );

        $this->newLine();

        if ($this->option('dry-run')) {
            $this->warn('🔍 DRY RUN MODE - No actual API calls will be made');
            $this->info('✨ All validation checks passed!');
            return self::SUCCESS;
        }

        // Perform batch add (commented out for safety - uncomment when ready)
        /*
        $this->info('4️⃣  Performing batch add operation...');

        try {
            $result = $batchService->addItemsBatch(userId: 1, items: $items);

            $this->newLine();
            $this->info('📊 Batch Operation Results:');
            $this->table(
                ['Metric', 'Value'],
                [
                    ['Total Results', $result->totalResults],
                    ['Status', $result->status->name],
                    ['Successful', $result->successCount()],
                    ['Failed', $result->failureCount()],
                    ['Success Rate', round($result->successRate(), 2) . '%'],
                    ['Execution Time', round($result->executionTimeMs, 2) . 'ms'],
                ]
            );

            if ($result->hasFailed() || $result->isPartiallySuccessful()) {
                $this->newLine();
                $this->error('❌ Errors occurred:');
                foreach ($result->getErrorMessages() as $error) {
                    $this->line("  • {$error}");
                }
            }

            $this->newLine();
            $this->info($result->isFullySuccessful() ? '✅ All operations succeeded!' : '⚠️  Some operations failed');

        } catch (\Exception $e) {
            $this->error('❌ Batch operation failed: ' . $e->getMessage());
            return self::FAILURE;
        }
        */

        $this->warn('💡 Uncomment the batch add section in the command to test live API calls');
        $this->info('✨ Test completed successfully!');

        return self::SUCCESS;
    }
}
