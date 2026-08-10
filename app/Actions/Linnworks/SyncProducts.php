<?php

declare(strict_types=1);

namespace App\Actions\Linnworks;

use Throwable;
use App\Models\Product;
use App\Models\SyncCheckpoint;
use Laravel\Scout\ModelObserver;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use BenHughes\Linnworks\LinnworksClient;

/**
 * Pull the Linnworks catalogue into the products table.
 *
 * Streams a page at a time rather than collecting the lot - a full catalogue
 * will not fit in memory, and writing as we go means an interrupted run still
 * leaves useful data behind.
 */
final readonly class SyncProducts
{
    public function __construct(
        private LinnworksClient $linnworks,
    ) {}

    /**
     * @param  array<int, string>  $dataRequirements
     * @return array{synced: int, created: int, updated: int, failed: int}
     */
    public function __invoke(array $dataRequirements = ['StockLevels', 'Pricing'], int $batchSize = 200): array
    {
        $checkpoint = SyncCheckpoint::getOrCreateCheckpoint('products', 'linnworks');
        $checkpoint->startSync();

        // Scout would index every write individually, which turns a catalogue
        // sync into thousands of search calls.
        ModelObserver::disableSyncingFor(Product::class);

        $synced = $created = $updated = $failed = 0;

        $stream = $this->linnworks->stock()->streamAll(
            dataRequirements: $dataRequirements,
            pageSize: $batchSize,
        );

        foreach ($stream as $page) {
            $result = $this->writePage($page);

            $synced += $page->count();
            $created += $result['created'];
            $updated += $result['updated'];
            $failed += $result['failed'];
        }

        ModelObserver::enableSyncingFor(Product::class);

        $checkpoint->completeSync($synced, $created, $updated, $failed);

        Log::info('Product sync completed', compact('synced', 'created', 'updated', 'failed'));

        return compact('synced', 'created', 'updated', 'failed');
    }

    /**
     * @param  Collection<int, array>  $page
     * @return array{created: int, updated: int, failed: int}
     */
    private function writePage(Collection $page): array
    {
        $created = $updated = $failed = 0;

        DB::transaction(function () use ($page, &$created, &$updated, &$failed): void {
            $page->each(function (array $item) use (&$created, &$updated, &$failed): void {
                // One malformed item should not abandon the rest of the page,
                // so it is counted and skipped rather than thrown.
                try {
                    $product = Product::updateOrCreate(
                        ['sku' => $item['SKU'] ?? $item['ItemNumber'] ?? null],
                        $this->toProduct($item),
                    );

                    $product->wasRecentlyCreated ? $created++ : $updated++;
                } catch (Throwable $e) {
                    $failed++;

                    Log::warning('Failed to process product', [
                        'sku' => $item['SKU'] ?? 'unknown',
                        'error' => $e->getMessage(),
                    ]);
                }
            });
        });

        return compact('created', 'updated', 'failed');
    }

    /**
     * @return array<string, mixed>
     */
    private function toProduct(array $item): array
    {
        $purchasePrice = $item['PurchasePrice'] ?? null;

        return [
            'linnworks_id' => $item['StockItemId'] ?? null,
            'sku' => $item['SKU'] ?? $item['ItemNumber'] ?? null,
            'title' => $item['ItemTitle'] ?? null,
            'description' => $item['MetaData'] ?? null,
            'barcode' => $item['BarcodeNumber'] ?? null,
            'category_id' => $item['CategoryId'] ?? null,
            'category_name' => $item['CategoryName'] ?? null,
            // Linnworks reports "no cost recorded" as zero, which would
            // otherwise look like a genuinely free product downstream.
            'purchase_price' => $purchasePrice > 0 ? $purchasePrice : null,
            'retail_price' => $item['RetailPrice'] ?? null,
            'weight' => $item['Weight'] ?? null,
            'dimensions' => [
                'height' => $item['Height'] ?? null,
                'width' => $item['Width'] ?? null,
                'depth' => $item['Depth'] ?? null,
            ],
            'stock_level' => $item['StockLevel'] ?? $item['Quantity'] ?? 0,
            'stock_minimum' => $item['MinimumLevel'] ?? 0,
            'stock_in_orders' => $item['InOrder'] ?? 0,
            'stock_due' => $item['Due'] ?? 0,
            'stock_available' => $item['Available'] ?? 0,
            'is_active' => ! ($item['IsArchived'] ?? false),
            'last_synced_at' => now(),
        ];
    }
}
