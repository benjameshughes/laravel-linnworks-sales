<?php

declare(strict_types=1);

namespace App\Services\Linnworks\AuditTrail;

use App\Services\Linnworks\Auth\SessionManager;
use App\Services\Linnworks\Core\LinnworksClient;
use App\ValueObjects\AuditTrail\AuditTrailCollection;
use App\ValueObjects\Linnworks\ApiRequest;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Modern audit trail service for tracking system changes.
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
class AuditTrailService
{
    private const MAX_PAGE_SIZE = 100;

    private const DEFAULT_PAGE_SIZE = 50;

    public function __construct(
        private readonly LinnworksClient $client,
        private readonly SessionManager $sessionManager,
    ) {}

    /**
     * Get inventory audit trail with pagination.
     *
     * @param  array<string>  $actionFilter  Filter by specific actions (e.g., ['Update', 'Delete'])
     */
    public function getInventoryAuditTrail(
        int $userId,
        string $stockItemId,
        ?Carbon $dateFrom = null,
        ?Carbon $dateTo = null,
        int $pageNumber = 1,
        int $pageSize = self::DEFAULT_PAGE_SIZE,
        array $actionFilter = [],
    ): AuditTrailCollection {
        $startTime = microtime(true);

        Log::info('Fetching inventory audit trail', [
            'user_id' => $userId,
            'stock_item_id' => $stockItemId,
            'date_from' => $dateFrom?->toISOString(),
            'date_to' => $dateTo?->toISOString(),
            'page' => $pageNumber,
            'page_size' => $pageSize,
        ]);

        $sessionToken = $this->sessionManager->getValidSessionToken($userId);
        if (! $sessionToken) {
            throw new \RuntimeException('No valid session token available');
        }

        $pageSize = min($pageSize, self::MAX_PAGE_SIZE);

        $request = ApiRequest::post('Inventory/GetInventoryItemAuditTrailPaged', [
            'stockItemId' => $stockItemId,
            'dateFrom' => $dateFrom?->utc()->toISOString() ?? now()->subDays(30)->utc()->toISOString(),
            'dateTo' => $dateTo?->utc()->toISOString() ?? now()->utc()->toISOString(),
            'pageNumber' => $pageNumber,
            'pageSize' => $pageSize,
            'actionFilter' => $actionFilter,
        ])->asJson();

        $response = $this->client->makeRequest($request, $sessionToken);

        if ($response->isError()) {
            Log::error('Failed to fetch inventory audit trail', [
                'user_id' => $userId,
                'stock_item_id' => $stockItemId,
                'error' => $response->error,
            ]);

            throw new \RuntimeException('Failed to fetch inventory audit trail: '.$response->error);
        }

        $data = $response->getData();
        $events = $data['Data'] ?? [];

        $executionTime = (microtime(true) - $startTime) * 1000;

        Log::info('Inventory audit trail fetched successfully', [
            'user_id' => $userId,
            'stock_item_id' => $stockItemId,
            'event_count' => count($events),
            'total_results' => $data['TotalResults'] ?? 0,
            'execution_time_ms' => round($executionTime, 2),
        ]);

        return AuditTrailCollection::fromApiResponse($events, 'inventory');
    }

    /**
     * Get order audit trail.
     */
    public function getOrderAuditTrail(
        int $userId,
        string $orderId,
    ): AuditTrailCollection {
        $startTime = microtime(true);

        Log::info('Fetching order audit trail', [
            'user_id' => $userId,
            'order_id' => $orderId,
        ]);

        $sessionToken = $this->sessionManager->getValidSessionToken($userId);
        if (! $sessionToken) {
            throw new \RuntimeException('No valid session token available');
        }

        $request = ApiRequest::post('Orders/GetOrderAuditTrail', [
            'orderId' => $orderId,
        ])->asJson();

        $response = $this->client->makeRequest($request, $sessionToken);

        if ($response->isError()) {
            Log::error('Failed to fetch order audit trail', [
                'user_id' => $userId,
                'order_id' => $orderId,
                'error' => $response->error,
            ]);

            throw new \RuntimeException('Failed to fetch order audit trail: '.$response->error);
        }

        $events = $response->getData()->toArray();

        $executionTime = (microtime(true) - $startTime) * 1000;

        Log::info('Order audit trail fetched successfully', [
            'user_id' => $userId,
            'order_id' => $orderId,
            'event_count' => count($events),
            'execution_time_ms' => round($executionTime, 2),
        ]);

        return AuditTrailCollection::fromApiResponse($events, 'order');
    }

    /**
     * Get audit trails for multiple orders in batch.
     *
     * @param  array<string>  $orderIds
     */
    public function getOrderAuditTrailsBatch(
        int $userId,
        array $orderIds,
    ): AuditTrailCollection {
        $startTime = microtime(true);

        Log::info('Fetching order audit trails batch', [
            'user_id' => $userId,
            'order_count' => count($orderIds),
        ]);

        $sessionToken = $this->sessionManager->getValidSessionToken($userId);
        if (! $sessionToken) {
            throw new \RuntimeException('No valid session token available');
        }

        $request = ApiRequest::post('Orders/GetOrderAuditTrailsByIds', [
            'orderIds' => $orderIds,
        ])->asJson();

        $response = $this->client->makeRequest($request, $sessionToken);

        if ($response->isError()) {
            Log::error('Failed to fetch order audit trails batch', [
                'user_id' => $userId,
                'order_count' => count($orderIds),
                'error' => $response->error,
            ]);

            throw new \RuntimeException('Failed to fetch order audit trails: '.$response->error);
        }

        // Response is an array of order audit trail arrays
        $allEvents = [];
        $orderAuditData = $response->getData()->toArray();

        foreach ($orderAuditData as $orderEvents) {
            if (is_array($orderEvents)) {
                $allEvents = array_merge($allEvents, $orderEvents);
            }
        }

        $executionTime = (microtime(true) - $startTime) * 1000;

        Log::info('Order audit trails batch fetched successfully', [
            'user_id' => $userId,
            'order_count' => count($orderIds),
            'total_events' => count($allEvents),
            'execution_time_ms' => round($executionTime, 2),
        ]);

        return AuditTrailCollection::fromApiResponse($allEvents, 'order');
    }

    /**
     * Get processed order audit trail with pagination.
     */
    public function getProcessedOrderAuditTrail(
        int $userId,
        ?Carbon $dateFrom = null,
        ?Carbon $dateTo = null,
        int $pageNumber = 1,
        int $pageSize = self::DEFAULT_PAGE_SIZE,
    ): AuditTrailCollection {
        $startTime = microtime(true);

        Log::info('Fetching processed order audit trail', [
            'user_id' => $userId,
            'date_from' => $dateFrom?->toISOString(),
            'date_to' => $dateTo?->toISOString(),
            'page' => $pageNumber,
            'page_size' => $pageSize,
        ]);

        $sessionToken = $this->sessionManager->getValidSessionToken($userId);
        if (! $sessionToken) {
            throw new \RuntimeException('No valid session token available');
        }

        $pageSize = min($pageSize, self::MAX_PAGE_SIZE);

        $request = ApiRequest::post('ProcessedOrders/GetProcessedAuditTrail', [
            'from' => $dateFrom?->utc()->toISOString() ?? now()->subDays(30)->utc()->toISOString(),
            'to' => $dateTo?->utc()->toISOString() ?? now()->utc()->toISOString(),
            'pageNumber' => $pageNumber,
            'entriesPerPage' => $pageSize,
        ])->asJson();

        $response = $this->client->makeRequest($request, $sessionToken);

        if ($response->isError()) {
            Log::error('Failed to fetch processed order audit trail', [
                'user_id' => $userId,
                'error' => $response->error,
            ]);

            throw new \RuntimeException('Failed to fetch processed order audit trail: '.$response->error);
        }

        $data = $response->getData();
        $events = $data['Data'] ?? [];

        $executionTime = (microtime(true) - $startTime) * 1000;

        Log::info('Processed order audit trail fetched successfully', [
            'user_id' => $userId,
            'event_count' => count($events),
            'total_results' => $data['TotalResults'] ?? 0,
            'execution_time_ms' => round($executionTime, 2),
        ]);

        return AuditTrailCollection::fromApiResponse($events, 'order');
    }

    /**
     * Get recent critical events across all audit trails.
     */
    public function getRecentCriticalEvents(
        int $userId,
        int $hours = 24,
        int $maxEvents = 100,
    ): AuditTrailCollection {
        $dateFrom = now()->subHours($hours);
        $dateTo = now();

        Log::info('Fetching recent critical events', [
            'user_id' => $userId,
            'hours' => $hours,
            'max_events' => $maxEvents,
        ]);

        // Get processed order audit trail (most comprehensive)
        $events = $this->getProcessedOrderAuditTrail(
            userId: $userId,
            dateFrom: $dateFrom,
            dateTo: $dateTo,
            pageSize: $maxEvents,
        );

        return $events->critical()->take($maxEvents);
    }

    /**
     * Get activity summary for a specific resource.
     */
    public function getResourceActivity(
        int $userId,
        string $resourceId,
        string $resourceType = 'inventory',
        ?Carbon $dateFrom = null,
        ?Carbon $dateTo = null,
    ): array {
        $events = match ($resourceType) {
            'inventory' => $this->getInventoryAuditTrail(
                userId: $userId,
                stockItemId: $resourceId,
                dateFrom: $dateFrom,
                dateTo: $dateTo,
            ),
            'order' => $this->getOrderAuditTrail(
                userId: $userId,
                orderId: $resourceId,
            ),
            default => throw new \InvalidArgumentException("Unknown resource type: {$resourceType}"),
        };

        return [
            'resource_id' => $resourceId,
            'resource_type' => $resourceType,
            'summary' => $events->summary(),
            'type_statistics' => $events->typeStatistics(),
            'user_statistics' => $events->userStatistics(),
            'recent_critical' => $events->critical()->take(10)->toArray(),
            'recent_events' => $events->take(20)->toArray(),
        ];
    }

    /**
     * Get user activity across all resources.
     */
    public function getUserActivity(
        int $userId,
        string $userName,
        ?Carbon $dateFrom = null,
        ?Carbon $dateTo = null,
    ): array {
        // Fetch processed order audit trail (includes all activities)
        $events = $this->getProcessedOrderAuditTrail(
            userId: $userId,
            dateFrom: $dateFrom ?? now()->subDays(30),
            dateTo: $dateTo,
            pageSize: self::MAX_PAGE_SIZE,
        );

        // Filter by user
        $userEvents = $events->byUser($userName);

        return [
            'user_name' => $userName,
            'date_range' => [
                'from' => $dateFrom?->toISOString() ?? now()->subDays(30)->toISOString(),
                'to' => $dateTo?->toISOString() ?? now()->toISOString(),
            ],
            'summary' => $userEvents->summary(),
            'type_statistics' => $userEvents->typeStatistics(),
            'timeline' => $userEvents->timeline('day'),
            'recent_events' => $userEvents->take(50)->toArray(),
        ];
    }

    /**
     * Get system-wide audit trail statistics.
     */
    public function getSystemStatistics(
        int $userId,
        ?Carbon $dateFrom = null,
        ?Carbon $dateTo = null,
    ): array {
        $events = $this->getProcessedOrderAuditTrail(
            userId: $userId,
            dateFrom: $dateFrom ?? now()->subDays(7),
            dateTo: $dateTo,
            pageSize: self::MAX_PAGE_SIZE,
        );

        return [
            'date_range' => [
                'from' => $dateFrom?->toISOString() ?? now()->subDays(7)->toISOString(),
                'to' => $dateTo?->toISOString() ?? now()->toISOString(),
            ],
            'summary' => $events->summary(),
            'type_statistics' => $events->typeStatistics(),
            'user_statistics' => $events->userStatistics(),
            'timeline' => $events->timeline('day'),
            'critical_events' => $events->critical()->count(),
            'order_events' => $events->orderEvents()->count(),
            'inventory_events' => $events->inventoryEvents()->count(),
        ];
    }
}
