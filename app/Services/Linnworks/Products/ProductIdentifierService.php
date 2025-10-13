<?php

declare(strict_types=1);

namespace App\Services\Linnworks\Products;

use App\Services\Linnworks\Auth\SessionManager;
use App\Services\Linnworks\Core\LinnworksClient;
use App\ValueObjects\Inventory\ProductIdentifier;
use App\ValueObjects\Inventory\ProductIdentifierCollection;
use App\ValueObjects\Inventory\ProductIdentifierType;
use App\ValueObjects\Linnworks\ApiRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Modern product identifier service.
 *
 * Manages product identifiers (GTIN, EAN, UPC, ASIN, etc.) with modern PHP patterns.
 *
 * Leverages PHP 8.2+ features:
 * - Readonly properties
 * - Named arguments
 * - Match expressions
 * - Constructor property promotion
 *
 * Laravel best practices:
 * - Dependency injection
 * - Logging
 * - Collection pipeline
 */
class ProductIdentifierService
{
    public function __construct(
        private readonly LinnworksClient $client,
        private readonly SessionManager $sessionManager,
    ) {}

    /**
     * Get all product identifiers for a stock item.
     */
    public function getProductIdentifiers(
        int $userId,
        string $stockItemId,
    ): ProductIdentifierCollection {
        $startTime = microtime(true);

        Log::info('Fetching product identifiers', [
            'user_id' => $userId,
            'stock_item_id' => $stockItemId,
        ]);

        $sessionToken = $this->sessionManager->getValidSessionToken($userId);
        if (! $sessionToken) {
            throw new \RuntimeException('No valid session token available');
        }

        $request = ApiRequest::post('Inventory/GetProductIdentifiersByStockItemId', [
            'StockItemId' => $stockItemId,
        ])->asJson();

        $response = $this->client->makeRequest($request, $sessionToken);

        if ($response->isError()) {
            Log::error('Failed to fetch product identifiers', [
                'user_id' => $userId,
                'stock_item_id' => $stockItemId,
                'error' => $response->error,
            ]);

            throw new \RuntimeException('Failed to fetch product identifiers: '.$response->error);
        }

        $executionTime = (microtime(true) - $startTime) * 1000;

        $identifiers = ProductIdentifierCollection::fromApiResponse($response->getData()->toArray());

        Log::info('Product identifiers fetched successfully', [
            'user_id' => $userId,
            'stock_item_id' => $stockItemId,
            'identifier_count' => $identifiers->count(),
            'execution_time_ms' => round($executionTime, 2),
        ]);

        return $identifiers;
    }

    /**
     * Add product identifiers to a stock item.
     *
     * @param  ProductIdentifierCollection|Collection<int, ProductIdentifier>  $identifiers
     */
    public function addProductIdentifiers(
        int $userId,
        string $stockItemId,
        ProductIdentifierCollection|Collection $identifiers,
    ): array {
        $startTime = microtime(true);

        Log::info('Adding product identifiers', [
            'user_id' => $userId,
            'stock_item_id' => $stockItemId,
            'identifier_count' => $identifiers->count(),
        ]);

        // Validate identifiers
        $validationErrors = $identifiers->validationErrors();
        if (! empty($validationErrors)) {
            Log::warning('Product identifier validation failed', [
                'errors' => $validationErrors,
            ]);

            throw new \InvalidArgumentException('Invalid product identifiers: '.json_encode($validationErrors));
        }

        $sessionToken = $this->sessionManager->getValidSessionToken($userId);
        if (! $sessionToken) {
            throw new \RuntimeException('No valid session token available');
        }

        $request = ApiRequest::post('Inventory/AddProductIdentifiers', [
            'StockItemId' => $stockItemId,
            'ProductIdentifiers' => $identifiers->toApiFormat(),
        ])->asJson();

        $response = $this->client->makeRequest($request, $sessionToken);

        if ($response->isError()) {
            Log::error('Failed to add product identifiers', [
                'user_id' => $userId,
                'stock_item_id' => $stockItemId,
                'error' => $response->error,
            ]);

            throw new \RuntimeException('Failed to add product identifiers: '.$response->error);
        }

        $executionTime = (microtime(true) - $startTime) * 1000;
        $result = $response->getData()->toArray();

        Log::info('Product identifiers added successfully', [
            'user_id' => $userId,
            'stock_item_id' => $stockItemId,
            'successful' => count($result['Successful'] ?? []),
            'failed' => count($result['Failed'] ?? []),
            'execution_time_ms' => round($executionTime, 2),
        ]);

        return $result;
    }

    /**
     * Update existing product identifiers.
     *
     * @param  ProductIdentifierCollection|Collection<int, ProductIdentifier>  $identifiers
     */
    public function updateProductIdentifiers(
        int $userId,
        string $stockItemId,
        ProductIdentifierCollection|Collection $identifiers,
    ): array {
        $startTime = microtime(true);

        Log::info('Updating product identifiers', [
            'user_id' => $userId,
            'stock_item_id' => $stockItemId,
            'identifier_count' => $identifiers->count(),
        ]);

        // Validate identifiers
        $validationErrors = $identifiers->validationErrors();
        if (! empty($validationErrors)) {
            Log::warning('Product identifier validation failed', [
                'errors' => $validationErrors,
            ]);

            throw new \InvalidArgumentException('Invalid product identifiers: '.json_encode($validationErrors));
        }

        $sessionToken = $this->sessionManager->getValidSessionToken($userId);
        if (! $sessionToken) {
            throw new \RuntimeException('No valid session token available');
        }

        $request = ApiRequest::post('Inventory/UpdateProductIdentifiers', [
            'StockItemId' => $stockItemId,
            'ProductIdentifiers' => $identifiers->toApiFormat(),
        ])->asJson();

        $response = $this->client->makeRequest($request, $sessionToken);

        if ($response->isError()) {
            Log::error('Failed to update product identifiers', [
                'user_id' => $userId,
                'stock_item_id' => $stockItemId,
                'error' => $response->error,
            ]);

            throw new \RuntimeException('Failed to update product identifiers: '.$response->error);
        }

        $executionTime = (microtime(true) - $startTime) * 1000;
        $result = $response->getData()->toArray();

        Log::info('Product identifiers updated successfully', [
            'user_id' => $userId,
            'stock_item_id' => $stockItemId,
            'execution_time_ms' => round($executionTime, 2),
        ]);

        return $result;
    }

    /**
     * Delete product identifiers.
     *
     * @param  ProductIdentifierCollection|Collection<int, ProductIdentifier>  $identifiers
     */
    public function deleteProductIdentifiers(
        int $userId,
        string $stockItemId,
        ProductIdentifierCollection|Collection $identifiers,
    ): array {
        $startTime = microtime(true);

        Log::info('Deleting product identifiers', [
            'user_id' => $userId,
            'stock_item_id' => $stockItemId,
            'identifier_count' => $identifiers->count(),
        ]);

        $sessionToken = $this->sessionManager->getValidSessionToken($userId);
        if (! $sessionToken) {
            throw new \RuntimeException('No valid session token available');
        }

        $request = ApiRequest::post('Inventory/DeleteProductIdentifiers', [
            'StockItemId' => $stockItemId,
            'ProductIdentifiers' => $identifiers->toApiFormat(),
        ])->asJson();

        $response = $this->client->makeRequest($request, $sessionToken);

        if ($response->isError()) {
            Log::error('Failed to delete product identifiers', [
                'user_id' => $userId,
                'stock_item_id' => $stockItemId,
                'error' => $response->error,
            ]);

            throw new \RuntimeException('Failed to delete product identifiers: '.$response->error);
        }

        $executionTime = (microtime(true) - $startTime) * 1000;
        $result = $response->getData()->toArray();

        Log::info('Product identifiers deleted successfully', [
            'user_id' => $userId,
            'stock_item_id' => $stockItemId,
            'execution_time_ms' => round($executionTime, 2),
        ]);

        return $result;
    }

    /**
     * Get available product identifier types from API.
     */
    public function getProductIdentifierTypes(int $userId): array
    {
        Log::info('Fetching product identifier types', [
            'user_id' => $userId,
        ]);

        $sessionToken = $this->sessionManager->getValidSessionToken($userId);
        if (! $sessionToken) {
            throw new \RuntimeException('No valid session token available');
        }

        $request = ApiRequest::get('Inventory/GetProductIdentifierTypes');

        $response = $this->client->makeRequest($request, $sessionToken);

        if ($response->isError()) {
            Log::error('Failed to fetch product identifier types', [
                'user_id' => $userId,
                'error' => $response->error,
            ]);

            throw new \RuntimeException('Failed to fetch product identifier types: '.$response->error);
        }

        return $response->getData()->toArray();
    }

    /**
     * Get extended product identifier types from API.
     */
    public function getProductIdentifierExtendedTypes(int $userId): array
    {
        Log::info('Fetching extended product identifier types', [
            'user_id' => $userId,
        ]);

        $sessionToken = $this->sessionManager->getValidSessionToken($userId);
        if (! $sessionToken) {
            throw new \RuntimeException('No valid session token available');
        }

        $request = ApiRequest::get('Inventory/GetProductIdentifierExtendedTypes');

        $response = $this->client->makeRequest($request, $sessionToken);

        if ($response->isError()) {
            Log::error('Failed to fetch extended product identifier types', [
                'user_id' => $userId,
                'error' => $response->error,
            ]);

            throw new \RuntimeException('Failed to fetch extended product identifier types: '.$response->error);
        }

        return $response->getData()->toArray();
    }

    /**
     * Add a single barcode to a product.
     */
    public function addBarcode(
        int $userId,
        string $stockItemId,
        string $barcodeValue,
        bool $isDefault = false,
    ): array {
        $identifier = new ProductIdentifier(
            type: ProductIdentifierType::BARCODE,
            value: $barcodeValue,
            isDefault: $isDefault,
        );

        $collection = new ProductIdentifierCollection([$identifier]);

        return $this->addProductIdentifiers($userId, $stockItemId, $collection);
    }

    /**
     * Add a GTIN to a product.
     */
    public function addGTIN(
        int $userId,
        string $stockItemId,
        string $gtinValue,
        bool $isDefault = false,
    ): array {
        $identifier = new ProductIdentifier(
            type: ProductIdentifierType::GTIN,
            value: $gtinValue,
            isDefault: $isDefault,
        );

        $collection = new ProductIdentifierCollection([$identifier]);

        return $this->addProductIdentifiers($userId, $stockItemId, $collection);
    }

    /**
     * Set the default identifier for a product.
     */
    public function setDefaultIdentifier(
        int $userId,
        string $stockItemId,
        ProductIdentifier $identifier,
    ): array {
        // Create new identifier with isDefault = true
        $defaultIdentifier = new ProductIdentifier(
            type: $identifier->type,
            value: $identifier->value,
            source: $identifier->source,
            isDefault: true,
        );

        $collection = new ProductIdentifierCollection([$defaultIdentifier]);

        return $this->updateProductIdentifiers($userId, $stockItemId, $collection);
    }
}
