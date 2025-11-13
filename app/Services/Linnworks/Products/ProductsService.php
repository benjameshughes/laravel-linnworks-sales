<?php

declare(strict_types=1);

namespace App\Services\Linnworks\Products;

use App\Services\Linnworks\Auth\SessionManager;
use App\Services\Linnworks\Core\LinnworksClient;
use App\ValueObjects\Linnworks\ApiRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

final class ProductsService
{
    public function __construct(
        private readonly LinnworksClient $client,
        private readonly SessionManager $sessionManager,
    ) {}

    /**
     * Get all stock items with pagination support
     */
    public function getAllStockItems(
        int $userId,
        int $entriesPerPage = 200,
        ?string $keyword = null,
        int $maxProducts = 10000
    ): Collection {
        $sessionToken = $this->sessionManager->getValidSessionToken($userId);

        if (! $sessionToken) {
            Log::error('No valid session token for stock items', ['user_id' => $userId]);

            return collect();
        }

        $allProducts = collect();
        $page = 1;

        Log::info('Starting stock items fetch', [
            'user_id' => $userId,
            'entries_per_page' => $entriesPerPage,
            'max_products' => $maxProducts,
            'keyword' => $keyword,
        ]);

        do {
            $payload = [
                'entriesPerPage' => $entriesPerPage,
                'pageNumber' => $page,
            ];

            if ($keyword) {
                $payload['keyword'] = $keyword;
            }

            $request = ApiRequest::get('Stock/GetStockItems', $payload);
            $response = $this->client->makeRequest($request, $sessionToken);

            if ($response->isError()) {
                Log::warning('Failed to fetch stock items page', [
                    'user_id' => $userId,
                    'page' => $page,
                    'error' => $response->error,
                    'status_code' => $response->statusCode,
                    'response_data' => $response->getData()->toArray(),
                    'payload' => $payload,
                ]);
                break;
            }

            $data = $response->getData();

            // Response is paginated - extract items
            $items = collect([]);

            if ($data->has('Items')) {
                $items = collect($data->get('Items'));
            } elseif ($data->isNotEmpty()) {
                $items = $data;
            }

            $allProducts = $allProducts->merge($items);

            Log::info('Fetched stock items page', [
                'page' => $page,
                'items_in_page' => $items->count(),
                'total_fetched' => $allProducts->count(),
                'has_more' => $items->count() >= $entriesPerPage,
            ]);

            // Stop if we've reached maxProducts or no more items
            if ($allProducts->count() >= $maxProducts || $items->count() < $entriesPerPage) {
                break;
            }

            $page++;
        } while (true);

        return $allProducts
            ->take($maxProducts)
            ->map(fn ($product) => is_array($product) ? $product : (array) $product);
    }

    /**
     * Get specific stock items by their IDs
     */
    public function getStockItemsByIds(int $userId, array $stockItemIds): Collection
    {
        $sessionToken = $this->sessionManager->getValidSessionToken($userId);

        if (! $sessionToken) {
            return collect();
        }

        $payload = [
            'stockItemIds' => $stockItemIds,
        ];

        $request = ApiRequest::post('Stock/GetStockItemsByIds', $payload)->asJson();
        $response = $this->client->makeRequest($request, $sessionToken);

        if ($response->isError()) {
            Log::warning('Failed to fetch stock items by IDs', [
                'user_id' => $userId,
                'ids_count' => count($stockItemIds),
                'error' => $response->error,
            ]);

            return collect();
        }

        return $response->getData()
            ->map(fn ($product) => is_array($product) ? $product : (array) $product);
    }

    /**
     * Get variation group id from a search term
     *
     * @params searchType<array>
     * @params searchText<string>
     * @params pageNumber<int>
     * @params enteriesPerPage<int>
     */
    public function getVariationId(int $userId, string $searchType, string $searchText, int $pageNumber = 1, int $entriesPerPage = 100): Collection
    {
        $sessionToken = $this->sessionManager->getValidSessionToken($userId);
        if (! $sessionToken) {
            Log::warning('No valid session token for variation items', ['user_id' => $userId]);

            return collect();
        }
        $payload = [
            'searchType' => $searchType,
            'searchText' => $searchText,
            'pageNumber' => $pageNumber,
            'entriesPerPage' => $entriesPerPage,
        ];

        $request = ApiRequest::post('Stock/SearchVariationGroups', $payload)->asJson();
        $response = $this->client->makeRequest($request, $sessionToken);

        if ($response->isError()) {
            Log::warning('Failed to fetch variation items by ID', [
                'user_id' => $userId,
                'error' => $response->error,
            ]);

            return collect();
        }

        $data = $response->getData();

        // Extract the Data array from the paginated response
        if ($data->has('Data')) {
            return collect($data->get('Data'))
                ->map(fn ($variation) => is_array($variation) ? $variation : (array) $variation);
        }

        return $data->map(fn ($variation) => is_array($variation) ? $variation : (array) $variation);
    }

    /**
     * Get variation group items by variation item ID
     */
    public function getVariationGroupItems(int $userId, string $pkVariationItemId): Collection
    {
        $sessionToken = $this->sessionManager->getValidSessionToken($userId);
        if (! $sessionToken) {
            Log::warning('No valid session token for variation items', ['user_id' => $userId]);

            return collect();
        }

        $payload = [
            'pkVariationItemId' => $pkVariationItemId,
        ];

        $request = ApiRequest::post('Stock/GetVariationItems', $payload)->asJson();
        $response = $this->client->makeRequest($request, $sessionToken);
        if ($response->isError()) {
            Log::warning('Failed to fetch variation items by ID', [
                'user_id' => $userId,
                'pk_variation_item_id' => $pkVariationItemId,
                'error' => $response->error,
            ]);

            return collect();
        }

        return $response->getData()->map(fn ($variation) => is_array($variation) ? $variation : (array) $variation);
    }
}
