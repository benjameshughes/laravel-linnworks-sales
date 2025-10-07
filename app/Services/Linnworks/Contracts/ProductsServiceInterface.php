<?php

namespace App\Services\Linnworks\Contracts;

use App\ValueObjects\Linnworks\ApiResponse;
use Illuminate\Support\Collection;

interface ProductsServiceInterface
{
    /**
     * Get all products
     */
    public function getAllProducts(
        int $userId,
        int $entriesPerPage = 200,
        int $maxProducts = 10000
    ): Collection;

    /**
     * Get detailed product information
     */
    public function getProductDetails(int $userId, string $stockItemId): ApiResponse;

    /**
     * Search products by criteria
     */
    public function searchProducts(
        int $userId,
        string $searchTerm,
        array $filters = [],
        int $entriesPerPage = 200
    ): ApiResponse;

    /**
     * Get product statistics
     */
    public function getProductStats(int $userId): array;

    /**
     * Get low stock products
     */
    public function getLowStockProducts(int $userId, int $threshold = 10): Collection;

    /**
     * Get out of stock products
     */
    public function getOutOfStockProducts(int $userId): Collection;
}