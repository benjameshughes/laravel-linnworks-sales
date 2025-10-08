<?php

declare(strict_types=1);

namespace App\Services\Linnworks\Products;

use App\Services\Linnworks\Core\LinnworksClient;
use App\Services\Linnworks\Auth\SessionManager;
use App\ValueObjects\Linnworks\ApiRequest;
use App\ValueObjects\Linnworks\ApiResponse;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Modern Stock API service using GetStockItemsFull with granular data loading.
 *
 * Supports the dataRequirements parameter to fetch only needed fields,
 * reducing payload size and improving performance.
 */
class StockService
{
    public function __construct(
        private readonly LinnworksClient $client,
        private readonly SessionManager $sessionManager,
    ) {}

    /**
     * Get stock items with granular data requirements.
     *
     * Available data requirements:
     * - StockLevels: Stock level information for each location
     * - Pricing: Pricing information
     * - Supplier: Supplier information
     * - ShippingInformation: Shipping details
     * - ChannelTitle: Channel-specific titles
     * - ChannelDescription: Channel-specific descriptions
     * - ChannelPrice: Channel-specific pricing
     * - ExtendedProperties: Custom extended properties
     * - Images: Product images
     */
    public function getStockItems(
        int $userId,
        ?string $keyword = null,
        array $dataRequirements = [],
        array $searchTypes = ['SKU', 'Title', 'Barcode'],
        bool $loadCompositeParents = false,
        bool $loadVariationParents = false,
        int $entriesPerPage = 200,
        int $pageNumber = 1
    ): ApiResponse {
        $sessionToken = $this->sessionManager->getValidSessionToken($userId);

        if (!$sessionToken) {
            return ApiResponse::error('No valid session token available');
        }

        // Build request payload
        $payload = [
            'entriesPerPage' => min($entriesPerPage, 200), // API max is 200
            'pageNumber' => $pageNumber,
            'loadCompositeParents' => $loadCompositeParents,
            'loadVariationParents' => $loadVariationParents,
        ];

        if ($keyword !== null && $keyword !== '') {
            $payload['keyword'] = $keyword;
        }

        if (!empty($dataRequirements)) {
            $payload['dataRequirements'] = $dataRequirements;
        }

        if (!empty($searchTypes)) {
            $payload['searchTypes'] = $searchTypes;
        }

        $request = ApiRequest::post('Stock/GetStockItemsFull', $payload)->asJson();

        Log::info('Fetching stock items with granular data', [
            'user_id' => $userId,
            'keyword' => $keyword,
            'data_requirements' => $dataRequirements,
            'search_types' => $searchTypes,
            'page' => $pageNumber,
            'entries_per_page' => $entriesPerPage,
        ]);

        return $this->client->makeRequest($request, $sessionToken);
    }

    /**
     * Get all stock items with pagination support.
     */
    public function getAllStockItems(
        int $userId,
        array $dataRequirements = [],
        ?string $keyword = null,
        int $maxItems = 10000,
        int $entriesPerPage = 200
    ): Collection {
        $allItems = collect();
        $page = 1;

        Log::info('Starting paginated stock items fetch', [
            'user_id' => $userId,
            'keyword' => $keyword,
            'data_requirements' => $dataRequirements,
            'max_items' => $maxItems,
        ]);

        do {
            $response = $this->getStockItems(
                userId: $userId,
                keyword: $keyword,
                dataRequirements: $dataRequirements,
                entriesPerPage: $entriesPerPage,
                pageNumber: $page
            );

            if ($response->isError()) {
                Log::warning('Failed to fetch stock items page', [
                    'user_id' => $userId,
                    'page' => $page,
                    'error' => $response->error,
                ]);
                break;
            }

            $data = $response->getData();

            // The response is an array of stock items
            $pageItems = is_array($data->toArray()) ? collect($data->toArray()) : $data;

            if ($pageItems->isEmpty()) {
                break;
            }

            $allItems = $allItems->merge($pageItems);

            Log::info('Fetched stock items page', [
                'page' => $page,
                'items_in_page' => $pageItems->count(),
                'total_fetched' => $allItems->count(),
            ]);

            $page++;

            // Stop if we've reached maxItems or page returned fewer than requested
            if ($allItems->count() >= $maxItems || $pageItems->count() < $entriesPerPage) {
                break;
            }
        } while (true);

        return $allItems->take($maxItems);
    }

    /**
     * Get stock items by IDs.
     */
    public function getStockItemsByIds(
        int $userId,
        array $stockItemIds,
        array $dataRequirements = []
    ): ApiResponse {
        $sessionToken = $this->sessionManager->getValidSessionToken($userId);

        if (!$sessionToken) {
            return ApiResponse::error('No valid session token available');
        }

        $payload = ['stockItemIds' => $stockItemIds];

        if (!empty($dataRequirements)) {
            $payload['dataRequirements'] = $dataRequirements;
        }

        $request = ApiRequest::post('Stock/GetStockItemsFullByIds', $payload)->asJson();

        Log::info('Fetching stock items by IDs', [
            'user_id' => $userId,
            'stock_item_count' => count($stockItemIds),
            'data_requirements' => $dataRequirements,
        ]);

        return $this->client->makeRequest($request, $sessionToken);
    }

    /**
     * Get stock items with only essential data (minimal payload).
     */
    public function getStockItemsMinimal(
        int $userId,
        ?string $keyword = null,
        int $entriesPerPage = 200,
        int $pageNumber = 1
    ): ApiResponse {
        return $this->getStockItems(
            userId: $userId,
            keyword: $keyword,
            dataRequirements: [], // No extra data
            entriesPerPage: $entriesPerPage,
            pageNumber: $pageNumber
        );
    }

    /**
     * Get stock items with full data (all available fields).
     */
    public function getStockItemsComplete(
        int $userId,
        ?string $keyword = null,
        int $entriesPerPage = 200,
        int $pageNumber = 1
    ): ApiResponse {
        return $this->getStockItems(
            userId: $userId,
            keyword: $keyword,
            dataRequirements: [
                'StockLevels',
                'Pricing',
                'Supplier',
                'ShippingInformation',
                'ChannelTitle',
                'ChannelDescription',
                'ChannelPrice',
                'ExtendedProperties',
                'Images',
            ],
            entriesPerPage: $entriesPerPage,
            pageNumber: $pageNumber
        );
    }

    /**
     * Get stock items for sales analytics (optimized for reporting).
     */
    public function getStockItemsForAnalytics(
        int $userId,
        ?string $keyword = null,
        int $maxItems = 5000
    ): Collection {
        return $this->getAllStockItems(
            userId: $userId,
            dataRequirements: [
                'StockLevels',
                'Pricing',
                'ChannelTitle',
            ],
            keyword: $keyword,
            maxItems: $maxItems
        );
    }

    /**
     * Get stock items for catalog sync (with images and descriptions).
     */
    public function getStockItemsForCatalog(
        int $userId,
        ?string $keyword = null,
        int $maxItems = 1000
    ): Collection {
        return $this->getAllStockItems(
            userId: $userId,
            dataRequirements: [
                'Images',
                'ChannelTitle',
                'ChannelDescription',
                'ChannelPrice',
                'StockLevels',
            ],
            keyword: $keyword,
            maxItems: $maxItems
        );
    }

    /**
     * Search stock items by keyword.
     */
    public function searchStockItems(
        int $userId,
        string $keyword,
        array $searchTypes = ['SKU', 'Title', 'Barcode'],
        array $dataRequirements = [],
        int $maxResults = 100
    ): Collection {
        $items = collect();
        $page = 1;

        do {
            $response = $this->getStockItems(
                userId: $userId,
                keyword: $keyword,
                dataRequirements: $dataRequirements,
                searchTypes: $searchTypes,
                entriesPerPage: min($maxResults, 200),
                pageNumber: $page
            );

            if ($response->isError()) {
                break;
            }

            $data = $response->getData();
            $pageItems = is_array($data->toArray()) ? collect($data->toArray()) : $data;

            if ($pageItems->isEmpty()) {
                break;
            }

            $items = $items->merge($pageItems);

            if ($items->count() >= $maxResults || $pageItems->count() < 200) {
                break;
            }

            $page++;
        } while (true);

        return $items->take($maxResults);
    }

    /**
     * Get stock items with low stock levels.
     */
    public function getLowStockItems(
        int $userId,
        int $threshold = 10,
        array $dataRequirements = ['StockLevels', 'Pricing']
    ): Collection {
        $allItems = $this->getAllStockItems(
            userId: $userId,
            dataRequirements: $dataRequirements,
            maxItems: 5000
        );

        return $allItems->filter(function ($item) use ($threshold) {
            $stockLevel = $item['StockLevel'] ?? 0;
            return $stockLevel > 0 && $stockLevel <= $threshold;
        })->values();
    }

    /**
     * Get out of stock items.
     */
    public function getOutOfStockItems(
        int $userId,
        array $dataRequirements = ['StockLevels', 'Pricing']
    ): Collection {
        $allItems = $this->getAllStockItems(
            userId: $userId,
            dataRequirements: $dataRequirements,
            maxItems: 5000
        );

        return $allItems->filter(function ($item) {
            $stockLevel = $item['StockLevel'] ?? null;
            return $stockLevel === 0 || $stockLevel === null;
        })->values();
    }

    /**
     * Get stock statistics.
     */
    public function getStockStatistics(int $userId): array
    {
        $items = $this->getAllStockItems(
            userId: $userId,
            dataRequirements: ['StockLevels', 'Pricing'],
            maxItems: 10000
        );

        if ($items->isEmpty()) {
            return [
                'total_products' => 0,
                'total_stock_value' => 0,
                'total_stock_units' => 0,
                'out_of_stock_count' => 0,
                'low_stock_count' => 0,
                'average_stock_level' => 0,
                'average_unit_value' => 0,
            ];
        }

        $totalUnits = $items->sum('StockLevel');
        $totalValue = $items->sum(function ($item) {
            return ($item['PurchasePrice'] ?? 0) * ($item['StockLevel'] ?? 0);
        });

        $outOfStock = $items->filter(fn ($item) => ($item['StockLevel'] ?? 0) === 0)->count();
        $lowStock = $items->filter(function ($item) {
            $level = $item['StockLevel'] ?? 0;
            return $level > 0 && $level <= 10;
        })->count();

        return [
            'total_products' => $items->count(),
            'total_stock_value' => round($totalValue, 2),
            'total_stock_units' => $totalUnits,
            'out_of_stock_count' => $outOfStock,
            'low_stock_count' => $lowStock,
            'average_stock_level' => $items->count() > 0 ? round($totalUnits / $items->count(), 2) : 0,
            'average_unit_value' => $totalUnits > 0 ? round($totalValue / $totalUnits, 2) : 0,
            'generated_at' => now()->toISOString(),
        ];
    }
}
