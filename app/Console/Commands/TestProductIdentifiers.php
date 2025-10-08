<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Linnworks\Products\ProductIdentifierService;
use App\ValueObjects\Inventory\ProductIdentifier;
use App\ValueObjects\Inventory\ProductIdentifierCollection;
use App\ValueObjects\Inventory\ProductIdentifierType;
use Illuminate\Console\Command;

class TestProductIdentifiers extends Command
{
    protected $signature = 'test:product-identifiers
                            {--stock-item-id= : Stock item ID to test with}
                            {--add-barcode= : Add a barcode to the product}
                            {--add-gtin= : Add a GTIN to the product}
                            {--types : List available identifier types}
                            {--dry-run : Validate without making API calls}';

    protected $description = 'Test product identifier operations with value objects';

    public function handle(ProductIdentifierService $identifierService): int
    {
        $this->info('🏷️  Testing Product Identifier Service');
        $this->newLine();

        $stockItemId = $this->option('stock-item-id');
        $addBarcode = $this->option('add-barcode');
        $addGtin = $this->option('add-gtin');
        $showTypes = $this->option('types');
        $dryRun = $this->option('dry-run');

        try {
            // Test 1: Show available identifier types
            if ($showTypes) {
                $this->testIdentifierTypes($identifierService);
                return self::SUCCESS;
            }

            // Test 2: Validate and potentially add identifiers
            if ($addBarcode || $addGtin) {
                if (!$stockItemId) {
                    $this->error('❌ --stock-item-id is required when adding identifiers');
                    return self::FAILURE;
                }

                return $this->testAddIdentifiers(
                    $identifierService,
                    $stockItemId,
                    $addBarcode,
                    $addGtin,
                    $dryRun
                );
            }

            // Test 3: Get existing identifiers (default)
            if ($stockItemId) {
                $this->testGetIdentifiers($identifierService, $stockItemId);
                return self::SUCCESS;
            }

            // Test 4: Demo validation
            $this->testValidation();

        } catch (\Exception $e) {
            $this->error('❌ Test failed: ' . $e->getMessage());
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function testIdentifierTypes(ProductIdentifierService $identifierService): void
    {
        $this->info('📋 Available Product Identifier Types:');
        $this->newLine();

        $this->info('🔹 Built-in Types (from enum):');
        $this->table(
            ['Type', 'Name', 'Description', 'Globally Unique', 'Expected Length'],
            collect(ProductIdentifierType::cases())->map(fn ($type) => [
                $type->value,
                $type->name,
                $type->description(),
                $type->isGloballyUnique() ? 'Yes' : 'No',
                $type->expectedLength() ?? 'Variable',
            ])->toArray()
        );

        $this->newLine();
        $this->info('🔹 API Types (fetching from Linnworks...):');

        try {
            $types = $identifierService->getProductIdentifierTypes(userId: 1);
            $this->line(json_encode($types, JSON_PRETTY_PRINT));
        } catch (\Exception $e) {
            $this->warn('Could not fetch API types: ' . $e->getMessage());
        }
    }

    private function testGetIdentifiers(ProductIdentifierService $identifierService, string $stockItemId): void
    {
        $this->info("📦 Fetching identifiers for stock item: {$stockItemId}");
        $this->newLine();

        $identifiers = $identifierService->getProductIdentifiers(
            userId: 1,
            stockItemId: $stockItemId,
        );

        if ($identifiers->isEmpty()) {
            $this->warn('No identifiers found for this product');
            return;
        }

        $this->info("Found {$identifiers->count()} identifier(s):");
        $this->newLine();

        $this->table(
            ['Type', 'Value', 'Source', 'Default', 'Valid', 'Globally Unique'],
            $identifiers->map(fn (ProductIdentifier $id) => [
                $id->type->value,
                $id->value,
                $id->source ?? 'N/A',
                $id->isDefault ? '✓' : '',
                $id->isValid() ? '✓' : '✗',
                $id->isGloballyUnique() ? '✓' : '',
            ])->toArray()
        );

        $this->newLine();
        $this->displayStatistics($identifiers);
    }

    private function testAddIdentifiers(
        ProductIdentifierService $identifierService,
        string $stockItemId,
        ?string $barcodeValue,
        ?string $gtinValue,
        bool $dryRun
    ): int {
        $identifiers = [];

        if ($barcodeValue) {
            $identifiers[] = new ProductIdentifier(
                type: ProductIdentifierType::BARCODE,
                value: $barcodeValue,
                isDefault: false,
            );
        }

        if ($gtinValue) {
            $identifiers[] = new ProductIdentifier(
                type: ProductIdentifierType::GTIN,
                value: $gtinValue,
                isDefault: false,
            );
        }

        $collection = new ProductIdentifierCollection($identifiers);

        $this->info('📝 Validating identifiers...');
        $this->newLine();

        // Show identifiers to be added
        $this->table(
            ['Type', 'Value', 'Valid', 'Clean Value'],
            $collection->map(fn (ProductIdentifier $id) => [
                $id->type->value,
                $id->value,
                $id->isValid() ? '✓' : '✗',
                $id->cleanValue(),
            ])->toArray()
        );

        // Check validation
        $errors = $collection->validationErrors();
        if (!empty($errors)) {
            $this->error('❌ Validation errors:');
            foreach ($errors as $field => $fieldErrors) {
                foreach ($fieldErrors as $error) {
                    $this->line("  • {$field}: {$error}");
                }
            }
            return self::FAILURE;
        }

        $this->info('✅ All identifiers are valid!');
        $this->newLine();

        if ($dryRun) {
            $this->warn('🔍 DRY RUN MODE - No API calls will be made');
            return self::SUCCESS;
        }

        $this->info("🚀 Adding identifiers to stock item: {$stockItemId}");

        $result = $identifierService->addProductIdentifiers(
            userId: 1,
            stockItemId: $stockItemId,
            identifiers: $collection,
        );

        $successful = $result['Successful'] ?? [];
        $failed = $result['Failed'] ?? [];

        if (!empty($successful)) {
            $this->newLine();
            $this->info('✅ Successfully added:');
            $this->table(
                ['Type', 'Value'],
                collect($successful)->map(fn ($item) => [
                    $item['Type'] ?? 'N/A',
                    $item['Value'] ?? 'N/A',
                ])->toArray()
            );
        }

        if (!empty($failed)) {
            $this->newLine();
            $this->error('❌ Failed to add:');
            $this->table(
                ['Type', 'Value', 'Error'],
                collect($failed)->map(fn ($item) => [
                    $item['Type'] ?? 'N/A',
                    $item['Value'] ?? 'N/A',
                    $item['Error'] ?? 'Unknown error',
                ])->toArray()
            );

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('🎉 All identifiers added successfully!');

        return self::SUCCESS;
    }

    private function testValidation(): void
    {
        $this->info('🧪 Testing Identifier Validation:');
        $this->newLine();

        $testCases = [
            ['type' => ProductIdentifierType::EAN, 'value' => '1234567890123', 'expected' => true],
            ['type' => ProductIdentifierType::EAN, 'value' => '123', 'expected' => false],
            ['type' => ProductIdentifierType::UPC, 'value' => '123456789012', 'expected' => true],
            ['type' => ProductIdentifierType::UPC, 'value' => '12345', 'expected' => false],
            ['type' => ProductIdentifierType::GTIN, 'value' => '12345678', 'expected' => true],
            ['type' => ProductIdentifierType::GTIN, 'value' => '1234567890123', 'expected' => true],
            ['type' => ProductIdentifierType::GTIN, 'value' => '123', 'expected' => false],
            ['type' => ProductIdentifierType::ASIN, 'value' => 'B08N5WRWNW', 'expected' => true],
            ['type' => ProductIdentifierType::ASIN, 'value' => 'ABC', 'expected' => false],
        ];

        $this->table(
            ['Type', 'Value', 'Expected', 'Result', 'Status'],
            collect($testCases)->map(function ($test) {
                $identifier = new ProductIdentifier(
                    type: $test['type'],
                    value: $test['value'],
                );

                $isValid = $identifier->isValid();
                $passed = $isValid === $test['expected'];

                return [
                    $test['type']->value,
                    $test['value'],
                    $test['expected'] ? 'Valid' : 'Invalid',
                    $isValid ? 'Valid' : 'Invalid',
                    $passed ? '✓' : '✗',
                ];
            })->toArray()
        );

        $this->newLine();
        $this->info('✨ Validation test completed!');
    }

    private function displayStatistics(ProductIdentifierCollection $identifiers): void
    {
        $stats = $identifiers->statistics();

        $this->info('📊 Statistics:');
        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Identifiers', $stats['total']],
                ['Valid', $stats['valid']],
                ['Invalid', $stats['invalid']],
                ['Globally Unique', $stats['globally_unique']],
                ['Has Default', $stats['has_default'] ? 'Yes' : 'No'],
            ]
        );

        if (!empty($stats['types'])) {
            $this->newLine();
            $this->info('📈 Type Distribution:');
            $this->table(
                ['Type', 'Count'],
                collect($stats['types'])->map(fn ($count, $type) => [$type, $count])->toArray()
            );
        }
    }
}
