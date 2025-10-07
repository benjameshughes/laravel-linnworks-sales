<?php

namespace App\Jobs;

use App\Models\User;
use App\Models\Product;
use App\Services\Linnworks\Products\ProductsApiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class SyncInventoryPaginatedJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private readonly int $userId,
        private readonly int $pageNumber = 1,
        private readonly int $entriesPerPage = 200,
        private readonly int $maxRetries = 3,
    ) {}

    public function handle(ProductsApiService $productsApiService): void
    {
        $user = User::find($this->userId);
        
        if (!$user) {
            Log::error('User not found for inventory sync job', [
                'user_id' => $this->userId,
                'page_number' => $this->pageNumber,
            ]);
            return;
        }

        Log::info('Starting inventory sync job', [
            'user_id' => $this->userId,
            'page_number' => $this->pageNumber,
            'entries_per_page' => $this->entriesPerPage,
            'attempt' => $this->attempts(),
        ]);

        try {
            $response = $productsApiService->getInventoryPaginated(
                $this->userId,
                $this->pageNumber,
                $this->entriesPerPage
            );

            if ($response->isError()) {
                throw new \Exception("API error: {$response->error}");
            }

            $items = $response->getData();
            $processedCount = 0;
            $updatedCount = 0;
            $createdCount = 0;

            DB::transaction(function () use ($items, &$processedCount, &$updatedCount, &$createdCount) {
                foreach ($items as $item) {
                    $stockItemId = $item['StockItemId'] ?? null;
                    
                    if (!$stockItemId) {
                        continue;
                    }

                    $productData = [
                        'stock_item_id' => $stockItemId,
                        'title' => $item['ItemTitle'] ?? 'Unknown Product',
                        'sku' => $item['ItemNumber'] ?? $item['SKU'] ?? null,
                        'barcode' => $item['BarcodeNumber'] ?? null,
                        'purchase_price' => (float) ($item['PurchasePrice'] ?? 0),
                        'retail_price' => (float) ($item['RetailPrice'] ?? 0),
                        'quantity' => (int) ($item['StockLevel'] ?? 0),
                        'category' => $item['Category'] ?? null,
                        'brand' => $item['Brand'] ?? null,
                        'weight' => is_array($item['Weight'] ?? null) ? json_encode($item['Weight']) : $item['Weight'],
                        'dimensions' => is_array($item['Dimensions'] ?? null) ? json_encode($item['Dimensions']) : $item['Dimensions'],
                        'description' => $item['Description'] ?? null,
                        'meta_title' => $item['MetaTitle'] ?? null,
                        'meta_description' => $item['MetaDescription'] ?? null,
                        'meta_keywords' => $item['MetaKeywords'] ?? null,
                        'last_updated_date' => isset($item['LastUpdateDate']) 
                            ? \Carbon\Carbon::parse($item['LastUpdateDate'])
                            : now(),
                        'created_date' => isset($item['CreatedDate'])
                            ? \Carbon\Carbon::parse($item['CreatedDate'])
                            : now(),
                        'synced_at' => now(),
                    ];

                    $product = Product::updateOrCreate(
                        ['stock_item_id' => $stockItemId],
                        $productData
                    );

                    if ($product->wasRecentlyCreated) {
                        $createdCount++;
                    } else {
                        $updatedCount++;
                    }

                    $processedCount++;
                }
            });

            Log::info('Inventory sync job completed successfully', [
                'user_id' => $this->userId,
                'page_number' => $this->pageNumber,
                'entries_per_page' => $this->entriesPerPage,
                'total_items' => $items->count(),
                'processed_count' => $processedCount,
                'created_count' => $createdCount,
                'updated_count' => $updatedCount,
                'attempt' => $this->attempts(),
            ]);

            // Dispatch next page if this page was full
            if ($items->count() >= $this->entriesPerPage) {
                dispatch(new self(
                    $this->userId,
                    $this->pageNumber + 1,
                    $this->entriesPerPage,
                    $this->maxRetries
                ))->delay(now()->addSeconds(30)); // Add delay to respect rate limits
            }

        } catch (\Exception $e) {
            Log::error('Inventory sync job failed', [
                'user_id' => $this->userId,
                'page_number' => $this->pageNumber,
                'entries_per_page' => $this->entriesPerPage,
                'attempt' => $this->attempts(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            if ($this->attempts() < $this->maxRetries) {
                $this->release(120); // Retry after 2 minutes
            } else {
                $this->fail($e);
            }
        }
    }

    public function retryUntil(): \DateTime
    {
        return now()->addHour();
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Inventory sync job failed permanently', [
            'user_id' => $this->userId,
            'page_number' => $this->pageNumber,
            'entries_per_page' => $this->entriesPerPage,
            'attempts' => $this->attempts(),
            'error' => $exception->getMessage(),
        ]);
    }
}