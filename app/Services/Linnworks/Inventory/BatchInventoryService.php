<?php

declare(strict_types=1);

namespace App\Services\Linnworks\Inventory;

use App\Services\Linnworks\Auth\SessionManager;
use App\Services\Linnworks\Core\LinnworksClient;
use App\ValueObjects\Inventory\BatchOperationResult;
use App\ValueObjects\Inventory\InventoryItem;
use App\ValueObjects\Linnworks\ApiRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Modern batch inventory operations service.
 *
 * Leverages PHP 8.2+ features:
 * - Readonly properties
 * - Named arguments
 * - Match expressions
 * - Constructor property promotion
 *
 * Laravel best practices:
 * - Dependency injection
 * - Validation
 * - Logging
 * - Collection pipeline
 */
class BatchInventoryService
{
    private const MAX_BATCH_SIZE = 200; // Linnworks API limit

    private const RATE_LIMIT_PER_MINUTE = 150;

    public function __construct(
        private readonly LinnworksClient $client,
        private readonly SessionManager $sessionManager,
    ) {}

    /**
     * Add multiple inventory items in batch.
     *
     * @param  Collection<int, InventoryItem>  $items
     */
    public function addItemsBatch(
        int $userId,
        Collection $items,
        bool $validateBeforeSend = true
    ): BatchOperationResult {
        $startTime = microtime(true);

        Log::info('Starting batch inventory add operation', [
            'user_id' => $userId,
            'item_count' => $items->count(),
            'validate' => $validateBeforeSend,
        ]);

        // Validate batch size
        if ($items->count() > self::MAX_BATCH_SIZE) {
            throw new \InvalidArgumentException(
                sprintf('Batch size cannot exceed %d items', self::MAX_BATCH_SIZE)
            );
        }

        // Validate items if requested
        if ($validateBeforeSend) {
            $this->validateItems($items);
        }

        // Get session token
        $sessionToken = $this->sessionManager->getValidSessionToken($userId);
        if (! $sessionToken) {
            throw new \RuntimeException('No valid session token available');
        }

        // Transform items to API format
        $apiItems = $items->map(fn (InventoryItem $item) => $item->toApiFormat())->toArray();

        // Make API request
        $request = ApiRequest::post('Inventory/AddInventoryItemBulk', [
            'inventoryItems' => $apiItems,
        ])->asJson();

        $response = $this->client->makeRequest($request, $sessionToken);

        if ($response->isError()) {
            $executionTime = (microtime(true) - $startTime) * 1000;

            Log::error('Batch inventory add failed', [
                'user_id' => $userId,
                'error' => $response->error,
                'execution_time_ms' => round($executionTime, 2),
            ]);

            throw new \RuntimeException('Batch add operation failed: '.$response->error);
        }

        $executionTime = (microtime(true) - $startTime) * 1000;
        $result = BatchOperationResult::fromApiResponse($response->getData()->toArray(), $executionTime);

        Log::info('Batch inventory add completed', [
            'user_id' => $userId,
            'summary' => $result->getSummary(),
        ]);

        return $result;
    }

    /**
     * Update inventory stock levels in batch.
     *
     * @param  array<string, int>  $stockUpdates  SKU => new stock level
     */
    public function updateStockLevelsBatch(
        int $userId,
        array $stockUpdates,
        string $locationId,
        string $changeSource = 'Batch Update'
    ): BatchOperationResult {
        $startTime = microtime(true);

        Log::info('Starting batch stock level update', [
            'user_id' => $userId,
            'update_count' => count($stockUpdates),
            'location_id' => $locationId,
            'change_source' => $changeSource,
        ]);

        $sessionToken = $this->sessionManager->getValidSessionToken($userId);
        if (! $sessionToken) {
            throw new \RuntimeException('No valid session token available');
        }

        // Transform stock updates to API format
        $updates = collect($stockUpdates)->map(function (int $newLevel, string $sku) use ($locationId, $changeSource) {
            return [
                'SKU' => $sku,
                'LocationId' => $locationId,
                'Level' => $newLevel,
                'ChangeSource' => $changeSource,
            ];
        })->values()->toArray();

        $request = ApiRequest::post('Stock/UpdateStockLevelsBulk', [
            'stockLevels' => $updates,
        ])->asJson();

        $response = $this->client->makeRequest($request, $sessionToken);

        if ($response->isError()) {
            $executionTime = (microtime(true) - $startTime) * 1000;

            Log::error('Batch stock level update failed', [
                'user_id' => $userId,
                'error' => $response->error,
                'execution_time_ms' => round($executionTime, 2),
            ]);

            throw new \RuntimeException('Batch stock update failed: '.$response->error);
        }

        $executionTime = (microtime(true) - $startTime) * 1000;
        $result = BatchOperationResult::fromApiResponse($response->getData()->toArray(), $executionTime);

        Log::info('Batch stock level update completed', [
            'user_id' => $userId,
            'summary' => $result->getSummary(),
        ]);

        return $result;
    }

    /**
     * Delete multiple inventory items in batch.
     *
     * @param  array<int, string>  $stockItemIds
     */
    public function deleteItemsBatch(
        int $userId,
        array $stockItemIds
    ): BatchOperationResult {
        $startTime = microtime(true);

        Log::info('Starting batch inventory delete', [
            'user_id' => $userId,
            'item_count' => count($stockItemIds),
        ]);

        $sessionToken = $this->sessionManager->getValidSessionToken($userId);
        if (! $sessionToken) {
            throw new \RuntimeException('No valid session token available');
        }

        $request = ApiRequest::post('Inventory/DeleteInventoryItemBulk', [
            'inventoryItemIds' => $stockItemIds,
        ])->asJson();

        $response = $this->client->makeRequest($request, $sessionToken);

        if ($response->isError()) {
            $executionTime = (microtime(true) - $startTime) * 1000;

            Log::error('Batch inventory delete failed', [
                'user_id' => $userId,
                'error' => $response->error,
                'execution_time_ms' => round($executionTime, 2),
            ]);

            throw new \RuntimeException('Batch delete failed: '.$response->error);
        }

        $executionTime = (microtime(true) - $startTime) * 1000;
        $result = BatchOperationResult::fromApiResponse($response->getData()->toArray(), $executionTime);

        Log::info('Batch inventory delete completed', [
            'user_id' => $userId,
            'summary' => $result->getSummary(),
        ]);

        return $result;
    }

    /**
     * Process large batch in chunks to respect API limits.
     *
     * @param  Collection<int, InventoryItem>  $items
     * @return Collection<int, BatchOperationResult>
     */
    public function addItemsInChunks(
        int $userId,
        Collection $items,
        int $chunkSize = self::MAX_BATCH_SIZE
    ): Collection {
        $chunkSize = min($chunkSize, self::MAX_BATCH_SIZE);

        Log::info('Starting chunked batch add operation', [
            'user_id' => $userId,
            'total_items' => $items->count(),
            'chunk_size' => $chunkSize,
            'expected_chunks' => ceil($items->count() / $chunkSize),
        ]);

        $results = collect();
        $chunkNumber = 1;

        foreach ($items->chunk($chunkSize) as $chunk) {
            Log::info("Processing chunk {$chunkNumber}", [
                'chunk_size' => $chunk->count(),
            ]);

            $result = $this->addItemsBatch($userId, $chunk);
            $results->push($result);

            // Rate limiting: wait between chunks if needed
            if ($chunkNumber < ceil($items->count() / $chunkSize)) {
                $delayMs = $this->calculateRateLimitDelay($chunkSize);
                if ($delayMs > 0) {
                    Log::debug("Rate limit delay: {$delayMs}ms");
                    usleep((int) ($delayMs * 1000));
                }
            }

            $chunkNumber++;
        }

        Log::info('Chunked batch add operation completed', [
            'user_id' => $userId,
            'total_chunks' => $results->count(),
            'total_successful' => $results->sum(fn (BatchOperationResult $r) => $r->successCount()),
            'total_failed' => $results->sum(fn (BatchOperationResult $r) => $r->failureCount()),
        ]);

        return $results;
    }

    /**
     * Validate inventory items before sending to API.
     *
     * @param  Collection<int, InventoryItem>  $items
     *
     * @throws ValidationException
     */
    private function validateItems(Collection $items): void
    {
        $errors = [];

        foreach ($items as $index => $item) {
            $itemErrors = $item->validate();
            if (! empty($itemErrors)) {
                $errors["item_{$index}"] = $itemErrors;
            }
        }

        if (! empty($errors)) {
            Log::warning('Batch validation failed', [
                'error_count' => count($errors),
                'errors' => $errors,
            ]);

            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * Calculate delay needed for rate limiting.
     */
    private function calculateRateLimitDelay(int $requestsMade): float
    {
        // Calculate delay to stay within rate limit
        $millisPerMinute = 60 * 1000;
        $millisPerRequest = $millisPerMinute / self::RATE_LIMIT_PER_MINUTE;

        // Add small buffer (10%)
        return $millisPerRequest * $requestsMade * 1.1;
    }

    /**
     * Get batch operation statistics.
     */
    public function getBatchStatistics(Collection $results): array
    {
        return [
            'total_batches' => $results->count(),
            'total_operations' => $results->sum(fn (BatchOperationResult $r) => $r->totalResults),
            'total_successful' => $results->sum(fn (BatchOperationResult $r) => $r->successCount()),
            'total_failed' => $results->sum(fn (BatchOperationResult $r) => $r->failureCount()),
            'overall_success_rate' => $this->calculateOverallSuccessRate($results),
            'total_execution_time_ms' => $results->sum(fn (BatchOperationResult $r) => $r->executionTimeMs),
            'average_batch_time_ms' => $results->avg(fn (BatchOperationResult $r) => $r->executionTimeMs),
        ];
    }

    /**
     * Calculate overall success rate across all batches.
     */
    private function calculateOverallSuccessRate(Collection $results): float
    {
        $totalOps = $results->sum(fn (BatchOperationResult $r) => $r->totalResults);

        if ($totalOps === 0) {
            return 0.0;
        }

        $totalSuccessful = $results->sum(fn (BatchOperationResult $r) => $r->successCount());

        return round(($totalSuccessful / $totalOps) * 100, 2);
    }
}
