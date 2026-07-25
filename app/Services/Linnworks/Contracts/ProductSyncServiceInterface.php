<?php

declare(strict_types=1);

namespace App\Services\Linnworks\Contracts;

/**
 * Seam between queued jobs and the Linnworks product sync implementation.
 *
 * The concrete service is final, so consumers depend on this contract to keep
 * the adapter substitutable.
 */
interface ProductSyncServiceInterface
{
    /**
     * @param  array<int, string>  $dataRequirements
     * @return array{synced: int, created: int, updated: int, failed: int}
     */
    public function syncProducts(
        int $userId,
        array $dataRequirements = ['StockLevels', 'Pricing', 'ChannelTitle'],
        ?string $keyword = null,
        int $batchSize = 200,
        int $maxProducts = 5000
    ): array;
}
