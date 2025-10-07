<?php

namespace App\Services\Linnworks\Products;

use App\Services\Linnworks\Core\LinnworksClient;
use App\Services\Linnworks\Auth\SessionManager;
use App\ValueObjects\Linnworks\ApiRequest;
use App\ValueObjects\Linnworks\ApiResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class InventoryService
{
    public function __construct(
        private readonly LinnworksClient $client,
        private readonly SessionManager $sessionManager,
    ) {}

    /**
     * Get current inventory levels
     */
    public function getInventoryLevels(int $userId): ApiResponse
    {
        $sessionToken = $this->sessionManager->getValidSessionToken($userId);
        
        if (!$sessionToken) {
            return ApiResponse::error('No valid session token available');
        }

        $request = ApiRequest::post('Inventory/GetStockLevels');

        Log::info('Fetching inventory levels', [
            'user_id' => $userId,
        ]);

        return $this->client->makeRequest($request, $sessionToken);
    }

    /**
     * Update inventory levels
     */
    public function updateInventoryLevel(
        int $userId,
        string $stockItemId,
        int $newLevel,
        string $reason = 'Manual adjustment'
    ): ApiResponse {
        $sessionToken = $this->sessionManager->getValidSessionToken($userId);
        
        if (!$sessionToken) {
            return ApiResponse::error('No valid session token available');
        }

        $request = ApiRequest::post('Inventory/UpdateStockLevel', [
            'stockItemId' => $stockItemId,
            'level' => $newLevel,
            'reason' => $reason,
        ]);

        Log::info('Updating inventory level', [
            'user_id' => $userId,
            'stock_item_id' => $stockItemId,
            'new_level' => $newLevel,
            'reason' => $reason,
        ]);

        return $this->client->makeRequest($request, $sessionToken);
    }

    /**
     * Batch update inventory levels
     */
    public function batchUpdateInventoryLevels(
        int $userId,
        array $updates,
        string $reason = 'Batch update'
    ): array {
        $results = [];
        
        Log::info('Batch updating inventory levels', [
            'user_id' => $userId,
            'update_count' => count($updates),
            'reason' => $reason,
        ]);

        foreach ($updates as $stockItemId => $newLevel) {
            $response = $this->updateInventoryLevel($userId, $stockItemId, $newLevel, $reason);
            
            $results[$stockItemId] = [
                'success' => $response->isSuccess(),
                'error' => $response->error,
                'status_code' => $response->statusCode,
                'new_level' => $newLevel,
            ];
        }

        $successful = collect($results)->where('success', true)->count();
        $failed = count($results) - $successful;

        Log::info('Batch inventory update completed', [
            'user_id' => $userId,
            'total_updates' => count($updates),
            'successful' => $successful,
            'failed' => $failed,
        ]);

        return [
            'results' => $results,
            'summary' => [
                'total' => count($updates),
                'successful' => $successful,
                'failed' => $failed,
                'success_rate' => count($updates) > 0 ? ($successful / count($updates)) * 100 : 0,
            ],
        ];
    }

    /**
     * Get inventory movements/history
     */
    public function getInventoryMovements(
        int $userId,
        string $stockItemId,
        ?\DateTime $from = null,
        ?\DateTime $to = null
    ): ApiResponse {
        $sessionToken = $this->sessionManager->getValidSessionToken($userId);
        
        if (!$sessionToken) {
            return ApiResponse::error('No valid session token available');
        }

        $params = [
            'stockItemId' => $stockItemId,
        ];

        if ($from) {
            $params['from'] = $from->format('c');
        }

        if ($to) {
            $params['to'] = $to->format('c');
        }

        $request = ApiRequest::post('Inventory/GetStockMovements', $params);

        Log::info('Fetching inventory movements', [
            'user_id' => $userId,
            'stock_item_id' => $stockItemId,
            'from' => $from?->format('c'),
            'to' => $to?->format('c'),
        ]);

        return $this->client->makeRequest($request, $sessionToken);
    }

    /**
     * Get inventory locations
     */
    public function getInventoryLocations(int $userId): ApiResponse
    {
        $sessionToken = $this->sessionManager->getValidSessionToken($userId);
        
        if (!$sessionToken) {
            return ApiResponse::error('No valid session token available');
        }

        $request = ApiRequest::post('Inventory/GetLocations');

        Log::info('Fetching inventory locations', [
            'user_id' => $userId,
        ]);

        return $this->client->makeRequest($request, $sessionToken);
    }

    /**
     * Get inventory by location
     */
    public function getInventoryByLocation(int $userId, string $locationId): ApiResponse
    {
        $sessionToken = $this->sessionManager->getValidSessionToken($userId);
        
        if (!$sessionToken) {
            return ApiResponse::error('No valid session token available');
        }

        $request = ApiRequest::post('Inventory/GetInventoryByLocation', [
            'locationId' => $locationId,
        ]);

        Log::info('Fetching inventory by location', [
            'user_id' => $userId,
            'location_id' => $locationId,
        ]);

        return $this->client->makeRequest($request, $sessionToken);
    }

    /**
     * Transfer inventory between locations
     */
    public function transferInventory(
        int $userId,
        string $stockItemId,
        string $fromLocationId,
        string $toLocationId,
        int $quantity,
        string $reason = 'Location transfer'
    ): ApiResponse {
        $sessionToken = $this->sessionManager->getValidSessionToken($userId);
        
        if (!$sessionToken) {
            return ApiResponse::error('No valid session token available');
        }

        $request = ApiRequest::post('Inventory/TransferStock', [
            'stockItemId' => $stockItemId,
            'fromLocationId' => $fromLocationId,
            'toLocationId' => $toLocationId,
            'quantity' => $quantity,
            'reason' => $reason,
        ]);

        Log::info('Transferring inventory', [
            'user_id' => $userId,
            'stock_item_id' => $stockItemId,
            'from_location' => $fromLocationId,
            'to_location' => $toLocationId,
            'quantity' => $quantity,
            'reason' => $reason,
        ]);

        return $this->client->makeRequest($request, $sessionToken);
    }

    /**
     * Get inventory valuation
     */
    public function getInventoryValuation(int $userId): array
    {
        $sessionToken = $this->sessionManager->getValidSessionToken($userId);
        
        if (!$sessionToken) {
            return [
                'total_value' => 0,
                'total_items' => 0,
                'average_value' => 0,
                'error' => 'No valid session token available',
            ];
        }

        $request = ApiRequest::post('Inventory/GetStockItems', [
            'entriesPerPage' => 1000,
            'pageNumber' => 1,
        ]);

        $response = $this->client->makeRequest($request, $sessionToken);
        
        if ($response->isError()) {
            return [
                'total_value' => 0,
                'total_items' => 0,
                'average_value' => 0,
                'error' => $response->error,
            ];
        }

        $items = $response->getData();
        
        $totalValue = $items->sum(function ($item) {
            return ($item['PurchasePrice'] ?? 0) * ($item['StockLevel'] ?? 0);
        });

        $totalItems = $items->sum('StockLevel');
        
        $valuationByCategory = $items->groupBy('Category')
            ->map(function ($categoryItems) {
                $categoryValue = $categoryItems->sum(function ($item) {
                    return ($item['PurchasePrice'] ?? 0) * ($item['StockLevel'] ?? 0);
                });
                
                return [
                    'total_value' => $categoryValue,
                    'total_items' => $categoryItems->sum('StockLevel'),
                    'product_count' => $categoryItems->count(),
                ];
            });

        Log::info('Calculated inventory valuation', [
            'user_id' => $userId,
            'total_value' => $totalValue,
            'total_items' => $totalItems,
            'product_count' => $items->count(),
        ]);

        return [
            'total_value' => $totalValue,
            'total_items' => $totalItems,
            'average_value' => $totalItems > 0 ? $totalValue / $totalItems : 0,
            'product_count' => $items->count(),
            'valuation_by_category' => $valuationByCategory->toArray(),
            'calculated_at' => now()->toISOString(),
        ];
    }

    /**
     * Get total inventory count
     */
    public function getInventoryCount(int $userId): int
    {
        $sessionToken = $this->sessionManager->getValidSessionToken($userId);
        
        if (!$sessionToken) {
            Log::error('No valid session token for inventory count', ['user_id' => $userId]);
            return 0;
        }

        $request = ApiRequest::get('Inventory/GetInventoryItemsCount');

        Log::info('Fetching inventory count', [
            'user_id' => $userId,
        ]);

        $response = $this->client->makeRequest($request, $sessionToken);
        
        if ($response->isError()) {
            Log::error('Failed to get inventory count', [
                'user_id' => $userId,
                'error' => $response->error,
            ]);
            return 0;
        }

        // This endpoint returns a plain number, not JSON
        $count = (int) $response->getRawResponse();

        Log::info("Retrieved inventory count: {$count}", [
            'user_id' => $userId,
            'count' => $count,
        ]);

        return $count;
    }

    /**
     * Get inventory alerts (low stock, out of stock, etc.)
     */
    public function getInventoryAlerts(int $userId, int $lowStockThreshold = 10): array
    {
        $sessionToken = $this->sessionManager->getValidSessionToken($userId);
        
        if (!$sessionToken) {
            return [
                'alerts' => [],
                'summary' => [
                    'total_alerts' => 0,
                    'out_of_stock' => 0,
                    'low_stock' => 0,
                ],
                'error' => 'No valid session token available',
            ];
        }

        $request = ApiRequest::post('Inventory/GetStockItems', [
            'entriesPerPage' => 1000,
            'pageNumber' => 1,
        ]);

        $response = $this->client->makeRequest($request, $sessionToken);
        
        if ($response->isError()) {
            return [
                'alerts' => [],
                'summary' => [
                    'total_alerts' => 0,
                    'out_of_stock' => 0,
                    'low_stock' => 0,
                ],
                'error' => $response->error,
            ];
        }

        $items = $response->getData();
        $alerts = collect();

        foreach ($items as $item) {
            $stockLevel = $item['StockLevel'] ?? 0;
            
            if ($stockLevel <= 0) {
                $alerts->push([
                    'type' => 'out_of_stock',
                    'severity' => 'high',
                    'stock_item_id' => $item['StockItemId'],
                    'sku' => $item['ItemNumber'] ?? $item['SKU'] ?? 'Unknown',
                    'title' => $item['ItemTitle'] ?? 'Unknown Product',
                    'current_stock' => $stockLevel,
                    'message' => 'Product is out of stock',
                ]);
            } elseif ($stockLevel <= $lowStockThreshold) {
                $alerts->push([
                    'type' => 'low_stock',
                    'severity' => 'medium',
                    'stock_item_id' => $item['StockItemId'],
                    'sku' => $item['ItemNumber'] ?? $item['SKU'] ?? 'Unknown',
                    'title' => $item['ItemTitle'] ?? 'Unknown Product',
                    'current_stock' => $stockLevel,
                    'threshold' => $lowStockThreshold,
                    'message' => "Stock level ({$stockLevel}) is below threshold ({$lowStockThreshold})",
                ]);
            }
        }

        $outOfStock = $alerts->where('type', 'out_of_stock')->count();
        $lowStock = $alerts->where('type', 'low_stock')->count();

        Log::info('Generated inventory alerts', [
            'user_id' => $userId,
            'total_alerts' => $alerts->count(),
            'out_of_stock' => $outOfStock,
            'low_stock' => $lowStock,
            'threshold' => $lowStockThreshold,
        ]);

        return [
            'alerts' => $alerts->toArray(),
            'summary' => [
                'total_alerts' => $alerts->count(),
                'out_of_stock' => $outOfStock,
                'low_stock' => $lowStock,
            ],
            'threshold' => $lowStockThreshold,
            'generated_at' => now()->toISOString(),
        ];
    }
}