<?php

declare(strict_types=1);

namespace App\Services\Linnworks\Products;

use App\Models\Product;
use App\Models\SyncCheckpoint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Product sync service with batch operations and checkpoint support.
 */
class ProductSyncService
{
    public function __construct(
        private readonly StockService $stockService,
    ) {}

    /**
     * Sync products from Linnworks with granular data control.
     */
    public function syncProducts(
        int $userId,
        array $dataRequirements = ['StockLevels', 'Pricing', 'ChannelTitle'],
        ?string $keyword = null,
        int $batchSize = 200,
        int $maxProducts = 5000
    ): array {
        $checkpoint = SyncCheckpoint::getOrCreateCheckpoint('products', 'linnworks');
        $checkpoint->startSync();

        Log::info('Starting product sync', [
            'user_id' => $userId,
            'data_requirements' => $dataRequirements,
            'keyword' => $keyword,
            'batch_size' => $batchSize,
            'max_products' => $maxProducts,
        ]);

        try {
            $products = $this->stockService->getAllStockItems(
                userId: $userId,
                dataRequirements: $dataRequirements,
                keyword: $keyword,
                maxItems: $maxProducts,
                entriesPerPage: $batchSize
            );

            if ($products->isEmpty()) {
                Log::info('No products found to sync');
                $checkpoint->completeSync(0, 0, 0, 0);

                return ['synced' => 0, 'created' => 0, 'updated' => 0, 'failed' => 0];
            }

            Log::info("Fetched {$products->count()} products from Linnworks");

            // Process in batches
            $created = 0;
            $updated = 0;
            $failed = 0;

            foreach ($products->chunk($batchSize) as $batch) {
                $result = $this->processBatch($batch);
                $created += $result['created'];
                $updated += $result['updated'];
                $failed += $result['failed'];

                Log::info('Processed product batch', [
                    'batch_size' => $batch->count(),
                    'created' => $result['created'],
                    'updated' => $result['updated'],
                    'failed' => $result['failed'],
                ]);
            }

            $checkpoint->completeSync(
                synced: $products->count(),
                created: $created,
                updated: $updated,
                failed: $failed
            );

            Log::info('Product sync completed', [
                'total' => $products->count(),
                'created' => $created,
                'updated' => $updated,
                'failed' => $failed,
            ]);

            return [
                'synced' => $products->count(),
                'created' => $created,
                'updated' => $updated,
                'failed' => $failed,
            ];
        } catch (\Throwable $e) {
            $checkpoint->failSync($e->getMessage());
            Log::error('Product sync failed', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Sync only products with low or out of stock levels.
     */
    public function syncLowStockProducts(
        int $userId,
        int $threshold = 10
    ): array {
        Log::info('Syncing low stock products', [
            'user_id' => $userId,
            'threshold' => $threshold,
        ]);

        $lowStockItems = $this->stockService->getLowStockItems(
            userId: $userId,
            threshold: $threshold,
            dataRequirements: ['StockLevels', 'Pricing']
        );

        $outOfStockItems = $this->stockService->getOutOfStockItems(
            userId: $userId,
            dataRequirements: ['StockLevels', 'Pricing']
        );

        $allItems = $lowStockItems->merge($outOfStockItems)->unique('StockItemId');

        Log::info("Found {$allItems->count()} products needing stock updates", [
            'low_stock' => $lowStockItems->count(),
            'out_of_stock' => $outOfStockItems->count(),
        ]);

        $created = 0;
        $updated = 0;
        $failed = 0;

        foreach ($allItems->chunk(200) as $batch) {
            $result = $this->processBatch($batch);
            $created += $result['created'];
            $updated += $result['updated'];
            $failed += $result['failed'];
        }

        return [
            'synced' => $allItems->count(),
            'created' => $created,
            'updated' => $updated,
            'failed' => $failed,
            'low_stock_count' => $lowStockItems->count(),
            'out_of_stock_count' => $outOfStockItems->count(),
        ];
    }

    /**
     * Process a batch of products.
     */
    private function processBatch(Collection $batch): array
    {
        $created = 0;
        $updated = 0;
        $failed = 0;

        DB::transaction(function () use ($batch, &$created, &$updated, &$failed) {
            foreach ($batch as $item) {
                try {
                    $productData = $this->mapStockItemToProduct($item);

                    $product = Product::updateOrCreate(
                        ['sku' => $productData['sku']],
                        $productData
                    );

                    if ($product->wasRecentlyCreated) {
                        $created++;
                    } else {
                        $updated++;
                    }
                } catch (\Throwable $e) {
                    $failed++;
                    Log::warning('Failed to process product', [
                        'sku' => $item['SKU'] ?? 'unknown',
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        });

        return compact('created', 'updated', 'failed');
    }

    /**
     * Map Linnworks stock item to Product model data.
     */
    private function mapStockItemToProduct(array $item): array
    {
        return [
            'sku' => $item['SKU'] ?? $item['ItemNumber'] ?? null,
            'title' => $item['ItemTitle'] ?? null,
            'barcode' => $item['BarcodeNumber'] ?? null,
            'purchase_price' => $item['PurchasePrice'] ?? null,
            'retail_price' => $item['RetailPrice'] ?? null,
            'stock_level' => $item['StockLevel'] ?? 0,
            'meta_data' => $item['MetaData'] ?? null,
            'category_name' => $item['CategoryName'] ?? null,
            'weight' => $item['Weight'] ?? null,
            'height' => $item['Height'] ?? null,
            'width' => $item['Width'] ?? null,
            'depth' => $item['Depth'] ?? null,
            'last_synced_at' => now(),
        ];
    }

    /**
     * Get sync statistics.
     */
    public function getSyncStatistics(): array
    {
        $checkpoint = SyncCheckpoint::query()
            ->where('sync_type', 'products')
            ->where('source', 'linnworks')
            ->first();

        if (! $checkpoint) {
            return [
                'never_synced' => true,
                'last_sync' => null,
                'status' => 'pending',
            ];
        }

        return [
            'never_synced' => false,
            'last_sync' => $checkpoint->last_sync_at?->toISOString(),
            'status' => $checkpoint->status,
            'records_synced' => $checkpoint->records_synced,
            'records_created' => $checkpoint->records_created,
            'records_updated' => $checkpoint->records_updated,
            'records_failed' => $checkpoint->records_failed,
            'sync_duration' => $checkpoint->sync_duration,
            'success_rate' => $checkpoint->success_rate,
        ];
    }

    /**
     * Sync products with minimal data (SKU, title, price only).
     */
    public function syncProductsMinimal(
        int $userId,
        int $maxProducts = 5000
    ): array {
        return $this->syncProducts(
            userId: $userId,
            dataRequirements: [], // Minimal data
            maxProducts: $maxProducts
        );
    }

    /**
     * Sync products with full catalog data (images, descriptions, etc.).
     */
    public function syncProductsCatalog(
        int $userId,
        int $maxProducts = 1000
    ): array {
        return $this->syncProducts(
            userId: $userId,
            dataRequirements: [
                'Images',
                'ChannelTitle',
                'ChannelDescription',
                'ChannelPrice',
                'StockLevels',
                'Pricing',
            ],
            maxProducts: $maxProducts
        );
    }

    /**
     * Sync specific product by SKU.
     */
    public function syncProductBySku(
        int $userId,
        string $sku
    ): ?Product {
        Log::info('Syncing product by SKU', [
            'user_id' => $userId,
            'sku' => $sku,
        ]);

        $items = $this->stockService->searchStockItems(
            userId: $userId,
            keyword: $sku,
            searchTypes: ['SKU'],
            dataRequirements: ['StockLevels', 'Pricing', 'Images'],
            maxResults: 1
        );

        if ($items->isEmpty()) {
            Log::warning('Product not found in Linnworks', ['sku' => $sku]);

            return null;
        }

        $item = $items->first();
        $productData = $this->mapStockItemToProduct($item);

        $product = Product::updateOrCreate(
            ['sku' => $productData['sku']],
            $productData
        );

        Log::info('Product synced successfully', [
            'sku' => $sku,
            'product_id' => $product->id,
            'was_created' => $product->wasRecentlyCreated,
        ]);

        return $product;
    }
}
