<?php

declare(strict_types=1);

namespace App\Actions\Linnworks\Products;

use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class ImportProducts
{
    public function handle(iterable $products, bool $forceUpdate = false): array
    {
        $productsCollection = $products instanceof Collection ? $products : collect($products);

        if ($productsCollection->isEmpty()) {
            Log::info('ImportProducts: no products to import');
            return [
                'processed' => 0,
                'created' => 0,
                'updated' => 0,
                'skipped' => 0,
                'failed' => 0,
            ];
        }

        $counts = [
            'processed' => 0,
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'failed' => 0,
        ];

        $productsCollection->chunk(50)->each(function (Collection $chunk) use (&$counts, $forceUpdate) {
            DB::transaction(function () use ($chunk, &$counts, $forceUpdate) {
                foreach ($chunk as $productData) {
                    $counts['processed']++;

                    $productArray = is_array($productData) ? $productData : (array) $productData;

                    // Extract IDs for lookup
                    $linnworksId = $productArray['StockItemId'] ?? null;
                    $sku = $productArray['ItemNumber'] ?? null;

                    if (!$linnworksId && !$sku) {
                        $counts['skipped']++;
                        continue;
                    }

                    try {
                        // Check if product exists
                        $existingProduct = $this->findExistingProduct($linnworksId, $sku);

                        $productModel = Product::fromLinnworksDetailedInventory($productArray);

                        if ($existingProduct) {
                            $existingProduct->fill($productModel->getAttributes());

                            $shouldPersist = $forceUpdate || $existingProduct->isDirty();

                            if ($shouldPersist) {
                                $existingProduct->save();
                                $counts['updated']++;
                            } else {
                                $counts['skipped']++;
                            }

                            continue;
                        }

                        $productModel->save();
                        $counts['created']++;
                    } catch (\Throwable $exception) {
                        $counts['failed']++;

                        Log::error('Failed to persist Linnworks product', [
                            'linnworks_id' => $linnworksId,
                            'sku' => $sku,
                            'message' => $exception->getMessage(),
                        ]);
                    }
                }
            }, 5);
        });

        return $counts;
    }

    private function findExistingProduct(?string $linnworksId, ?string $sku): ?Product
    {
        $existingProduct = null;

        if ($linnworksId) {
            $existingProduct = Product::where('linnworks_id', $linnworksId)->first();
        }

        if (!$existingProduct && $sku) {
            $existingProduct = Product::where('sku', $sku)->first();
        }

        return $existingProduct;
    }
}
