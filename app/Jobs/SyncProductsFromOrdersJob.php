<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Linnworks\Products\ImportProducts;
use App\DataTransferObjects\LinnworksProduct;
use App\Models\OrderItem;
use App\Models\Product;
use App\Services\Linnworks\Products\ProductsApiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Sync product details from Linnworks for SKUs found in orders.
 *
 * This job is dispatched after order imports to ensure product data
 * (title, pricing, stock levels) is populated from the Linnworks Inventory API.
 *
 * Flow:
 * 1. Collect unique stock_item_ids from provided order items (or scan all missing)
 * 2. Filter to products that need updating (missing or "Unknown Product")
 * 3. Batch fetch product details from Linnworks API
 * 4. Transform via LinnworksProduct DTO
 * 5. Upsert into products table
 */
final class SyncProductsFromOrdersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 600; // 10 minutes

    public int $backoff = 30;

    /**
     * @param  array<string>  $stockItemIds  Optional specific stock item IDs to sync
     * @param  int|null  $userId  User ID for API authentication
     * @param  bool  $scanMissing  If true, scan for all products with missing/bad titles
     */
    public function __construct(
        public readonly array $stockItemIds = [],
        public readonly ?int $userId = null,
        public readonly bool $scanMissing = false,
    ) {
        $this->onQueue('default');
    }

    public function handle(
        ProductsApiService $apiService,
        ImportProducts $importer
    ): void {
        $userId = $this->userId ?? $this->getDefaultUserId();

        if (! $userId) {
            Log::warning('SyncProductsFromOrdersJob: No user ID available for API calls');

            return;
        }

        // Collect stock item IDs to process
        $stockItemIds = $this->collectStockItemIds();

        if ($stockItemIds->isEmpty()) {
            Log::debug('SyncProductsFromOrdersJob: No products to sync');

            return;
        }

        Log::info('SyncProductsFromOrdersJob: Starting product sync', [
            'stock_items_count' => $stockItemIds->count(),
            'scan_missing' => $this->scanMissing,
        ]);

        $totalFetched = 0;
        $totalCreated = 0;
        $totalUpdated = 0;
        $totalFailed = 0;

        // Process in batches of 50 (API rate limit friendly)
        $stockItemIds->chunk(50)->each(function (Collection $batch) use (
            $apiService,
            $importer,
            $userId,
            &$totalFetched,
            &$totalCreated,
            &$totalUpdated,
            &$totalFailed
        ) {
            $batchIds = $batch->values()->toArray();

            Log::debug('SyncProductsFromOrdersJob: Fetching batch', [
                'batch_size' => count($batchIds),
            ]);

            // Fetch product details from Linnworks
            $products = $apiService->getMultipleProductDetails($userId, $batchIds);

            if ($products->isEmpty()) {
                Log::warning('SyncProductsFromOrdersJob: No products returned from API', [
                    'requested_count' => count($batchIds),
                ]);

                return;
            }

            $totalFetched += $products->count();

            // Transform via DTO and import
            $transformedProducts = $products->map(function (array $productData) {
                $dto = LinnworksProduct::fromArray($productData);

                return $dto->toDatabaseFormat();
            });

            // Use existing ImportProducts action
            $result = $importer->handle($transformedProducts);

            $totalCreated += $result['created'];
            $totalUpdated += $result['updated'];
            $totalFailed += $result['failed'];

            Log::debug('SyncProductsFromOrdersJob: Batch imported', [
                'fetched' => $products->count(),
                'created' => $result['created'],
                'updated' => $result['updated'],
                'failed' => $result['failed'],
            ]);

            // Rate limit courtesy - pause between batches
            usleep(200000); // 200ms
        });

        Log::info('SyncProductsFromOrdersJob: Product sync completed', [
            'total_fetched' => $totalFetched,
            'total_created' => $totalCreated,
            'total_updated' => $totalUpdated,
            'total_failed' => $totalFailed,
        ]);
    }

    /**
     * Collect stock item IDs that need to be synced.
     *
     * @return Collection<int, string>
     */
    private function collectStockItemIds(): Collection
    {
        // If specific IDs were provided, filter to those needing updates
        if (! empty($this->stockItemIds)) {
            return $this->filterToNeedingUpdate(collect($this->stockItemIds));
        }

        // If scanning for missing, find all products with bad titles
        if ($this->scanMissing) {
            return $this->findProductsNeedingUpdate();
        }

        return collect();
    }

    /**
     * Filter provided IDs to those actually needing updates.
     *
     * @param  Collection<int, string>  $stockItemIds
     * @return Collection<int, string>
     */
    private function filterToNeedingUpdate(Collection $stockItemIds): Collection
    {
        // Get existing products with these IDs that have valid titles
        $existingValidProducts = Product::whereIn('linnworks_id', $stockItemIds)
            ->where('title', '!=', 'Unknown Product')
            ->whereNotNull('title')
            ->pluck('linnworks_id');

        // Return IDs not in valid products list
        return $stockItemIds->diff($existingValidProducts)->values();
    }

    /**
     * Find all products that need updating from orders.
     *
     * @return Collection<int, string>
     */
    private function findProductsNeedingUpdate(): Collection
    {
        // Get stock item IDs from order_items that either:
        // 1. Don't exist in products table
        // 2. Have "Unknown Product" as title
        $orderItemStockIds = OrderItem::whereNotNull('stock_item_id')
            ->distinct()
            ->pluck('stock_item_id');

        if ($orderItemStockIds->isEmpty()) {
            return collect();
        }

        // Find products needing update
        $needsUpdate = Product::whereIn('linnworks_id', $orderItemStockIds)
            ->where(function ($query) {
                $query->where('title', 'Unknown Product')
                    ->orWhereNull('title');
            })
            ->pluck('linnworks_id');

        // Also find stock IDs with no product at all
        $existingProductIds = Product::whereIn('linnworks_id', $orderItemStockIds)
            ->pluck('linnworks_id');

        $missingProducts = $orderItemStockIds->diff($existingProductIds);

        return $needsUpdate->merge($missingProducts)->unique()->values();
    }

    /**
     * Get the default user ID for API calls.
     */
    private function getDefaultUserId(): ?int
    {
        // Use the first user with Linnworks credentials
        return \App\Models\User::whereNotNull('linnworks_token_id')->first()?->id;
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('SyncProductsFromOrdersJob failed', [
            'error' => $exception->getMessage(),
            'stock_items_count' => count($this->stockItemIds),
            'scan_missing' => $this->scanMissing,
        ]);
    }
}
