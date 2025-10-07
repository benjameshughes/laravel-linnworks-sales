<?php

namespace App\Services\Linnworks\Products;

use App\Services\Linnworks\Core\LinnworksClient;
use App\Services\Linnworks\Auth\SessionManager;
use App\ValueObjects\Linnworks\ApiRequest;
use App\ValueObjects\Linnworks\ApiResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class ProductsApiService
{
    public function __construct(
        private readonly LinnworksClient $client,
        private readonly SessionManager $sessionManager,
    ) {}

    /**
     * Get inventory with pagination using GetStockItemsFull
     */
    public function getInventoryPaginated(
        int $userId,
        int $pageNumber = 1,
        ?int $entriesPerPage = null
    ): ApiResponse {
        $sessionToken = $this->sessionManager->getValidSessionToken($userId);
        
        if (!$sessionToken) {
            return ApiResponse::error('No valid session token available');
        }

        $entriesPerPage = $entriesPerPage ?? config('linnworks.pagination.inventory_page_size', 200);

        $data = [
            'loadCompositeParents' => false,
            'loadVariationParents' => false,
            'entriesPerPage' => $entriesPerPage,
            'pageNumber' => $pageNumber,
            'dataRequirements' => ['StockLevels'],
        ];

        $request = ApiRequest::post('Stock/GetStockItemsFull', $data);

        Log::info('Fetching paginated inventory', [
            'user_id' => $userId,
            'page_number' => $pageNumber,
            'entries_per_page' => $entriesPerPage,
        ]);

        $response = $this->client->makeRequest($request, $sessionToken);
        
        if ($response->isSuccess()) {
            Log::info('Retrieved paginated inventory', [
                'user_id' => $userId,
                'page_number' => $pageNumber,
                'entries_per_page' => $entriesPerPage,
                'items_count' => $response->getData()->count(),
            ]);
        } else {
            Log::error('Failed to get paginated inventory', [
                'user_id' => $userId,
                'page_number' => $pageNumber,
                'entries_per_page' => $entriesPerPage,
                'error' => $response->error,
            ]);
        }

        return $response;
    }

    /**
     * Get all products (basic inventory items)
     */
    public function getAllProducts(
        int $userId,
        int $entriesPerPage = 200,
        int $maxProducts = 10000
    ): Collection {
        $sessionToken = $this->sessionManager->getValidSessionToken($userId);
        
        if (!$sessionToken) {
            Log::error('No valid session token for products', ['user_id' => $userId]);
            return collect();
        }

        Log::info('Fetching all products', [
            'user_id' => $userId,
            'entries_per_page' => $entriesPerPage,
            'max_products' => $maxProducts,
        ]);

        $request = ApiRequest::get('Stock/GetStockItems', [
            'entriesPerPage' => $entriesPerPage,
            'pageNumber' => 1,
        ]);

        $response = $this->client->makeRequest($request, $sessionToken);
        
        if ($response->isError()) {
            Log::error('Error fetching products', [
                'user_id' => $userId,
                'error' => $response->error,
            ]);
            return collect();
        }

        $payload = $response->getData();
        $products = collect($payload->get('Items', $payload->toArray()));
        
        Log::info('Fetched products', [
            'user_id' => $userId,
            'product_count' => $products->count(),
        ]);

        // If we have more than the max, truncate
        if ($products->count() > $maxProducts) {
            Log::warning('Products truncated due to max limit', [
                'user_id' => $userId,
                'actual_count' => $products->count(),
                'max_products' => $maxProducts,
            ]);
            $products = $products->take($maxProducts);
        }

        return $products;
    }

    /**
     * Get detailed product information
     */
    public function getProductDetails(int $userId, string $stockItemId): ApiResponse
    {
        $sessionToken = $this->sessionManager->getValidSessionToken($userId);
        
        if (!$sessionToken) {
            return ApiResponse::error('No valid session token available');
        }

        $request = ApiRequest::post('Inventory/GetStockItemFull', [
            'stockItemId' => $stockItemId,
        ]);

        Log::info('Fetching product details', [
            'user_id' => $userId,
            'stock_item_id' => $stockItemId,
        ]);

        return $this->client->makeRequest($request, $sessionToken);
    }

    /**
     * Get multiple product details by IDs
     */
    public function getMultipleProductDetails(int $userId, array $stockItemIds): Collection
    {
        $sessionToken = $this->sessionManager->getValidSessionToken($userId);
        
        if (!$sessionToken) {
            Log::error('No valid session token for multiple product details', ['user_id' => $userId]);
            return collect();
        }

        $request = ApiRequest::post('Inventory/GetStockItemsFullByIds', [
            'stockItemIds' => $stockItemIds,
        ]);

        Log::info('Fetching multiple product details', [
            'user_id' => $userId,
            'product_count' => count($stockItemIds),
        ]);

        $response = $this->client->makeRequest($request, $sessionToken);
        
        if ($response->isError()) {
            Log::error('Error fetching multiple product details', [
                'user_id' => $userId,
                'error' => $response->error,
            ]);
            return collect();
        }

        return $response->getData();
    }

    /**
     * Search products by criteria
     */
    public function searchProducts(
        int $userId,
        string $searchTerm,
        array $filters = [],
        int $entriesPerPage = 200
    ): ApiResponse {
        $sessionToken = $this->sessionManager->getValidSessionToken($userId);
        
        if (!$sessionToken) {
            return ApiResponse::error('No valid session token available');
        }

        $searchParams = [
            'searchTerm' => $searchTerm,
            'entriesPerPage' => $entriesPerPage,
            'pageNumber' => 1,
        ];

        // Add filters if provided
        if (!empty($filters['category'])) {
            $searchParams['category'] = $filters['category'];
        }

        if (!empty($filters['brand'])) {
            $searchParams['brand'] = $filters['brand'];
        }

        if (!empty($filters['minPrice'])) {
            $searchParams['minPrice'] = $filters['minPrice'];
        }

        if (!empty($filters['maxPrice'])) {
            $searchParams['maxPrice'] = $filters['maxPrice'];
        }

        $request = ApiRequest::post('Inventory/SearchStockItems', $searchParams);

        Log::info('Searching products', [
            'user_id' => $userId,
            'search_term' => $searchTerm,
            'filters' => $filters,
            'entries_per_page' => $entriesPerPage,
        ]);

        return $this->client->makeRequest($request, $sessionToken);
    }

    /**
     * Update product information
     */
    public function updateProduct(int $userId, string $stockItemId, array $updates): ApiResponse
    {
        $sessionToken = $this->sessionManager->getValidSessionToken($userId);
        
        if (!$sessionToken) {
            return ApiResponse::error('No valid session token available');
        }

        $updateParams = array_merge([
            'stockItemId' => $stockItemId,
        ], $updates);

        $request = ApiRequest::post('Inventory/UpdateStockItem', $updateParams);

        Log::info('Updating product', [
            'user_id' => $userId,
            'stock_item_id' => $stockItemId,
            'updates' => array_keys($updates),
        ]);

        return $this->client->makeRequest($request, $sessionToken);
    }

    /**
     * Get product categories
     */
    public function getProductCategories(int $userId): ApiResponse
    {
        $sessionToken = $this->sessionManager->getValidSessionToken($userId);
        
        if (!$sessionToken) {
            return ApiResponse::error('No valid session token available');
        }

        $request = ApiRequest::post('Inventory/GetCategories');

        Log::info('Fetching product categories', [
            'user_id' => $userId,
        ]);

        return $this->client->makeRequest($request, $sessionToken);
    }

    /**
     * Get product brands
     */
    public function getProductBrands(int $userId): ApiResponse
    {
        $sessionToken = $this->sessionManager->getValidSessionToken($userId);
        
        if (!$sessionToken) {
            return ApiResponse::error('No valid session token available');
        }

        $request = ApiRequest::post('Inventory/GetBrands');

        Log::info('Fetching product brands', [
            'user_id' => $userId,
        ]);

        return $this->client->makeRequest($request, $sessionToken);
    }

    /**
     * Get product statistics
     */
    public function getProductStats(int $userId): array
    {
        $products = $this->getAllProducts($userId, 1000);
        
        if ($products->isEmpty()) {
            return [
                'total_products' => 0,
                'total_value' => 0,
                'average_value' => 0,
                'categories' => [],
                'brands' => [],
                'stock_levels' => [],
            ];
        }

        $totalValue = $products->sum('PurchasePrice');
        $totalProducts = $products->count();
        
        $categoryStats = $products->groupBy('Category')
            ->map(function ($categoryProducts) {
                return [
                    'count' => $categoryProducts->count(),
                    'total_value' => $categoryProducts->sum('PurchasePrice'),
                    'average_value' => $categoryProducts->avg('PurchasePrice'),
                ];
            });

        $brandStats = $products->groupBy('Brand')
            ->map(function ($brandProducts) {
                return [
                    'count' => $brandProducts->count(),
                    'total_value' => $brandProducts->sum('PurchasePrice'),
                    'average_value' => $brandProducts->avg('PurchasePrice'),
                ];
            });

        $stockLevels = [
            'in_stock' => $products->where('StockLevel', '>', 0)->count(),
            'out_of_stock' => $products->where('StockLevel', '<=', 0)->count(),
            'low_stock' => $products->where('StockLevel', '>', 0)->where('StockLevel', '<=', 10)->count(),
        ];

        return [
            'total_products' => $totalProducts,
            'total_value' => $totalValue,
            'average_value' => $totalProducts > 0 ? $totalValue / $totalProducts : 0,
            'categories' => $categoryStats->toArray(),
            'brands' => $brandStats->toArray(),
            'stock_levels' => $stockLevels,
            'fetched_at' => now()->toISOString(),
        ];
    }

    /**
     * Get low stock products
     */
    public function getLowStockProducts(int $userId, int $threshold = 10): Collection
    {
        $allProducts = $this->getAllProducts($userId);
        
        $lowStockProducts = $allProducts->filter(function ($product) use ($threshold) {
            return isset($product['StockLevel']) && $product['StockLevel'] > 0 && $product['StockLevel'] <= $threshold;
        });

        Log::info('Filtered low stock products', [
            'user_id' => $userId,
            'threshold' => $threshold,
            'total_products' => $allProducts->count(),
            'low_stock_count' => $lowStockProducts->count(),
        ]);

        return $lowStockProducts;
    }

    /**
     * Get out of stock products
     */
    public function getOutOfStockProducts(int $userId): Collection
    {
        $allProducts = $this->getAllProducts($userId);
        
        $outOfStockProducts = $allProducts->filter(function ($product) {
            return isset($product['StockLevel']) && $product['StockLevel'] <= 0;
        });

        Log::info('Filtered out of stock products', [
            'user_id' => $userId,
            'total_products' => $allProducts->count(),
            'out_of_stock_count' => $outOfStockProducts->count(),
        ]);

        return $outOfStockProducts;
    }
}
