<?php

namespace App\Services\Linnworks\Orders;

use App\Services\Linnworks\Auth\SessionManager;
use App\Services\Linnworks\Concerns\HandlesApiRetries;
use App\Services\Linnworks\Core\LinnworksClient;
use App\ValueObjects\Linnworks\ApiRequest;
use App\ValueObjects\Linnworks\ApiResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class OpenOrdersService
{
    use HandlesApiRetries;

    public function __construct(
        private readonly LinnworksClient $client,
        private readonly SessionManager $sessionManager,
        private readonly LocationsService $locations,
        private readonly ViewsService $views,
    ) {}

    /**
     * Get open orders with date filtering support.
     *
     * This method uses the filters.DateFields parameter to filter by received date,
     * enabling true incremental sync for open orders.
     */
    public function getOpenOrdersInDateRange(
        int $userId,
        \Carbon\Carbon $from,
        \Carbon\Carbon $to,
        int $entriesPerPage = 200,
        int $maxOrders = 5000,
        ?int $viewId = null,
        ?string $locationId = null
    ): Collection {
        $sessionToken = $this->sessionManager->getValidSessionToken($userId);

        if (! $sessionToken) {
            Log::error('No valid session token for date-filtered open orders', ['user_id' => $userId]);

            return collect();
        }

        // Auto-detect location and view
        if ($locationId === null) {
            $locations = $this->locations->getLocations($userId);

            if ($locations->isNotEmpty()) {
                $detectedLocation = $locations->first(fn ($loc) => ($loc['IsDefault'] ?? false) === true) ?? $locations->first();
                if ($detectedLocation) {
                    $locationId = $detectedLocation['StockLocationId'] ?? $detectedLocation['LocationId'] ?? '00000000-0000-0000-0000-000000000000';
                }
            }
        }

        $viewId = $viewId ?? 4;
        $locationId = $locationId ?? '00000000-0000-0000-0000-000000000000';

        Log::info('Fetching date-filtered open orders', [
            'user_id' => $userId,
            'from' => $from->toISOString(),
            'to' => $to->toISOString(),
            'view_id' => $viewId,
            'location_id' => $locationId,
        ]);

        // Paginate through GetOpenOrders with date filtering
        $allOrders = collect();
        $page = 1;

        do {
            $payload = [
                'ViewId' => $viewId,
                'LocationId' => $locationId,
                'EntriesPerPage' => $entriesPerPage,
                'PageNumber' => $page,
                'Filters' => [
                    'DateFields' => [
                        [
                            'FieldCode' => 'GENERAL_INFO_DATE',
                            'Type' => 'Range',
                            'DateFrom' => $from->copy()->utc()->toISOString(),
                            'DateTo' => $to->copy()->utc()->toISOString(),
                        ],
                    ],
                ],
            ];

            $request = ApiRequest::post('OpenOrders/GetOpenOrders', $payload)->asJson();
            $response = $this->client->makeRequest($request, $sessionToken);

            if ($response->isError()) {
                Log::warning('Failed to fetch date-filtered open orders page', [
                    'user_id' => $userId,
                    'page' => $page,
                    'error' => $response->error,
                ]);
                break;
            }

            $data = $response->getData();
            $pageOrders = $data->has('Data')
                ? collect($data->get('Data'))
                : $data;

            if ($pageOrders->isEmpty()) {
                break;
            }

            $allOrders = $allOrders->merge($pageOrders);

            Log::info('Fetched date-filtered open orders page', [
                'page' => $page,
                'orders_in_page' => $pageOrders->count(),
                'total_fetched' => $allOrders->count(),
            ]);

            $page++;

            // Stop if we've reached maxOrders or if page returned fewer than requested
            if ($allOrders->count() >= $maxOrders || $pageOrders->count() < $entriesPerPage) {
                break;
            }
        } while (true);

        return $allOrders
            ->take($maxOrders)
            ->map(fn ($order) => is_array($order) ? $order : (array) $order);
    }

    /**
     * Get all open orders with proper pagination using GetViewStats + GetOpenOrders.
     */
    public function getAllOpenOrders(
        int $userId,
        int $entriesPerPage = 200,
        int $maxOrders = 5000,
        ?int $viewId = null,
        ?string $locationId = null
    ): Collection {
        $sessionToken = $this->sessionManager->getValidSessionToken($userId);

        if (! $sessionToken) {
            Log::error('No valid session token for all open orders', ['user_id' => $userId]);

            return collect();
        }

        // Auto-detect location only (ViewId is hardcoded to 4 for now)
        if ($locationId === null) {
            $locations = $this->locations->getLocations($userId);

            if ($locations->isNotEmpty()) {
                $detectedLocation = $locations->first(fn ($loc) => ($loc['IsDefault'] ?? false) === true) ?? $locations->first();
                if ($detectedLocation) {
                    $locationId = $detectedLocation['StockLocationId'] ?? $detectedLocation['LocationId'] ?? '00000000-0000-0000-0000-000000000000';
                }
            }
        }

        $viewId = $viewId ?? 4;
        $locationId = $locationId ?? '00000000-0000-0000-0000-000000000000';

        $entriesPerPage = $entriesPerPage > 0
            ? $entriesPerPage
            : (int) data_get(config('linnworks.open_orders'), 'entries_per_page', 200);

        // Step 1: Get view stats to determine total orders and pages needed
        $stats = $this->getViewStats($userId, $viewId, $locationId);

        if (! $stats) {
            Log::warning('Failed to get view stats', ['user_id' => $userId, 'view_id' => $viewId]);

            return collect();
        }

        $totalOrders = $stats['TotalOrders'] ?? 0;
        $totalPages = (int) ceil($totalOrders / $entriesPerPage);
        $pagesToFetch = min($totalPages, (int) ceil($maxOrders / $entriesPerPage));

        Log::info('Starting paginated open orders fetch', [
            'user_id' => $userId,
            'view_id' => $viewId,
            'location_id' => $locationId,
            'total_orders' => $totalOrders,
            'total_pages' => $totalPages,
            'pages_to_fetch' => $pagesToFetch,
            'entries_per_page' => $entriesPerPage,
        ]);

        if ($totalOrders === 0) {
            return collect();
        }

        // Step 2: Paginate through GetOpenOrders
        $allOrders = collect();

        for ($page = 1; $page <= $pagesToFetch; $page++) {
            $payload = [
                'ViewId' => $viewId,
                'LocationId' => $locationId,
                'EntriesPerPage' => $entriesPerPage,
                'PageNumber' => $page,
            ];

            $request = ApiRequest::post('OpenOrders/GetOpenOrders', $payload)->asJson();
            $response = $this->client->makeRequest($request, $sessionToken);

            if ($response->isError()) {
                Log::warning('Failed to fetch open orders page', [
                    'user_id' => $userId,
                    'page' => $page,
                    'error' => $response->error,
                ]);

                continue;
            }

            $data = $response->getData();
            $pageOrders = $data->has('Data')
                ? collect($data->get('Data'))
                : $data;

            $allOrders = $allOrders->merge($pageOrders);

            Log::info('Fetched open orders page', [
                'page' => $page,
                'orders_in_page' => $pageOrders->count(),
                'total_fetched' => $allOrders->count(),
            ]);

            // Stop if we've reached maxOrders
            if ($allOrders->count() >= $maxOrders) {
                break;
            }
        }

        return $allOrders
            ->take($maxOrders)
            ->map(fn ($order) => is_array($order) ? $order : (array) $order);
    }

    /**
     * Get view statistics including total order count.
     */
    public function getViewStats(int $userId, int $viewId, string $locationId): ?array
    {
        $sessionToken = $this->sessionManager->getValidSessionToken($userId);

        if (! $sessionToken) {
            return null;
        }

        try {
            return $this->withRetry(
                callback: function () use ($viewId, $locationId, $sessionToken, $userId) {
                    $payload = [
                        'ViewId' => $viewId,
                        'LocationId' => $locationId,
                    ];

                    $request = ApiRequest::post('OpenOrders/GetViewStats', $payload)->asJson();
                    $response = $this->client->makeRequest($request, $sessionToken);

                    if ($response->isError()) {
                        Log::warning('Failed to get view stats', [
                            'user_id' => $userId,
                            'view_id' => $viewId,
                            'error' => $response->error,
                        ]);

                        return null;
                    }

                    $data = $response->getData();

                    // Response is an array, find the matching view
                    $viewStats = $data->first(fn ($stat) => ($stat['ViewId'] ?? null) === $viewId);

                    if (! $viewStats) {
                        Log::warning('View stats not found in response', [
                            'user_id' => $userId,
                            'view_id' => $viewId,
                            'available_views' => $data->pluck('ViewId')->toArray(),
                        ]);

                        return null;
                    }

                    return is_array($viewStats) ? $viewStats : (array) $viewStats;
                },
                backoffSchedule: [1, 3, 10],
                operation: 'GetViewStats'
            );
        } catch (\Throwable $e) {
            Log::error('Failed to get view stats after retries', [
                'user_id' => $userId,
                'view_id' => $viewId,
                'exception' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Retrieve ALL open order identifiers in a single call (no pagination).
     *
     * Uses Orders/GetAllOpenOrders endpoint which returns all order IDs at once.
     * More accurate than paginated approach - no orders slip through gaps.
     * Higher rate limit: 250/min vs 150/min for GetOpenOrderIds.
     *
     * Simple config-driven approach - no database preferences, no auto-detection.
     */
    public function getOpenOrderIds(
        int $userId,
        ?string $locationId = null
    ): Collection {
        $sessionToken = $this->sessionManager->getValidSessionToken($userId);

        if (! $sessionToken) {
            Log::error('No valid session token for open order IDs', ['user_id' => $userId]);

            return collect();
        }

        // Use config value with sensible default
        $locationId ??= config('linnworks.open_orders.location_id', '00000000-0000-0000-0000-000000000000');

        Log::info('Fetching ALL open order IDs (single call)', [
            'user_id' => $userId,
            'location_id' => $locationId,
        ]);

        try {
            $request = ApiRequest::post('Orders/GetAllOpenOrders', [
                'fulfilmentCenter' => $locationId,
            ]);

            $response = $this->client->makeRequest($request, $sessionToken);

            if ($response->isError()) {
                Log::error('Failed to fetch all open order IDs', [
                    'user_id' => $userId,
                    'error' => $response->error,
                    'status_code' => $response->statusCode,
                ]);

                return collect();
            }

            $data = $response->getData();

            // Response is a flat array of order ID strings
            $ids = collect($data->toArray())
                ->filter(fn ($id) => ! is_null($id))
                ->map(fn ($id) => (string) $id)
                ->unique()
                ->values();

            Log::info('Fetched all open order IDs', [
                'user_id' => $userId,
                'total_ids' => $ids->count(),
            ]);

            return $ids;
        } catch (\Throwable $e) {
            Log::error('Exception fetching all open order IDs', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
                'exception_class' => get_class($e),
            ]);

            return collect();
        }
    }

    /**
     * Retrieve paginated open order identifiers (LEGACY - prefer getOpenOrderIds).
     *
     * @deprecated Use getOpenOrderIds() instead - uses GetAllOpenOrders (no pagination, more accurate)
     */
    public function getOpenOrderIdsPaginated(
        int $userId,
        int $entriesPerPage = 200,
        ?int $viewId = null,
        ?string $locationId = null
    ): Collection {
        $sessionToken = $this->sessionManager->getValidSessionToken($userId);

        if (! $sessionToken) {
            Log::warning('DEPRECATED: getOpenOrderIdsPaginated() called - use getOpenOrderIds() instead', [
                'user_id' => $userId,
            ]);

            return collect();
        }

        // Use config values with sensible defaults
        $viewId ??= config('linnworks.open_orders.view_id', 4);
        $locationId ??= config('linnworks.open_orders.location_id', '00000000-0000-0000-0000-000000000000');
        $entriesPerPage = $entriesPerPage > 0 ? $entriesPerPage : config('linnworks.open_orders.entries_per_page', 200);

        $ids = collect();
        $page = 1;
        $totalPages = 1;

        Log::info('Fetching open order IDs', [
            'user_id' => $userId,
            'view_id' => $viewId,
            'location_id' => $locationId,
            'entries_per_page' => $entriesPerPage,
        ]);

        do {
            $payload = [
                'ViewId' => $viewId,
                'LocationId' => $locationId ?? '00000000-0000-0000-0000-000000000000',
                'EntriesPerPage' => $entriesPerPage,
                'PageNumber' => $page,
            ];

            $request = ApiRequest::post('OpenOrders/GetOpenOrderIds', $payload)->asJson();

            $response = $this->client->makeRequest($request, $sessionToken);

            if ($response->isError()) {
                Log::warning('Failed to fetch open order IDs page', [
                    'user_id' => $userId,
                    'page' => $page,
                    'error' => $response->error,
                    'status_code' => $response->statusCode,
                    'payload' => $response->getData()->toArray(),
                ]);
                break;
            }

            $data = $response->getData();
            $pageIds = collect($data->get('Data', []))
                ->filter(fn ($id) => ! is_null($id))
                ->map(fn ($id) => (string) $id);

            if ($pageIds->isEmpty() && $data->has('Results')) {
                $pageIds = collect($data->get('Results', []))
                    ->filter(fn ($id) => ! is_null($id))
                    ->map(fn ($id) => (string) $id);
            }

            if ($pageIds->isEmpty()) {
                Log::info('Open order IDs page returned empty', [
                    'user_id' => $userId,
                    'page' => $page,
                    'payload' => $data->toArray(),
                ]);
                break;
            }

            $ids = $ids->merge($pageIds)->unique()->values();

            Log::info('Fetched open order IDs page', [
                'user_id' => $userId,
                'page' => $page,
                'ids_in_page' => $pageIds->count(),
                'total_ids' => $ids->count(),
                'total_entries' => $data->get('TotalEntries'),
                'total_pages' => $data->get('TotalPages'),
            ]);

            $page++;

            $totalPages = (int) ($data->get('TotalPages') ?? $page - 1);
        } while ($pageIds->isNotEmpty() && $page <= max(1, $totalPages));

        return $ids->values();
    }

    /**
     * Get open order details by ID
     */
    public function getOpenOrderDetails(int $userId, string $orderId): ApiResponse
    {
        $sessionToken = $this->sessionManager->getValidSessionToken($userId);

        if (! $sessionToken) {
            return ApiResponse::error('No valid session token available');
        }

        $request = ApiRequest::post('OpenOrders/GetOpenOrdersDetails', [
            'OrderIds' => [$orderId],
        ]);

        Log::info('Fetching open order details', [
            'user_id' => $userId,
            'order_id' => $orderId,
        ]);

        return $this->client->makeRequest($request, $sessionToken);
    }

    /**
     * Get multiple open order details by IDs
     */
    public function getMultipleOpenOrderDetails(int $userId, array $orderIds): Collection
    {
        $sessionToken = $this->sessionManager->getValidSessionToken($userId);

        if (! $sessionToken) {
            Log::error('No valid session token for multiple open order details', ['user_id' => $userId]);

            return collect();
        }

        $orderDetails = collect();

        Log::info('Fetching multiple open order details', [
            'user_id' => $userId,
            'order_count' => count($orderIds),
        ]);

        // Process in batches to avoid overwhelming the API
        $chunks = array_chunk($orderIds, 10);

        foreach ($chunks as $chunkIndex => $chunk) {
            $response = $this->client->makeRequest(
                ApiRequest::post('OpenOrders/GetOpenOrdersDetails', [
                    'OrderIds' => $chunk,
                ]),
                $sessionToken
            );

            if ($response->isError()) {
                Log::warning('Failed to fetch open order details chunk', [
                    'user_id' => $userId,
                    'chunk' => $chunkIndex + 1,
                    'error' => $response->error,
                ]);

                continue;
            }

            $data = $response->getData();
            $rawOrders = $data->get('Orders', $data->get('Data', []));
            if (empty($rawOrders) && $data->count() > 0) {
                $rawOrders = $data->values();
            }

            $orders = collect($rawOrders)->map(function ($order) {
                if ($order instanceof Collection) {
                    return $order->toArray();
                }

                return is_array($order) ? $order : (array) $order;
            });

            $orderDetails = $orderDetails->merge($orders);

            Log::info('Processed open order details chunk', [
                'user_id' => $userId,
                'chunk' => $chunkIndex + 1,
                'chunk_size' => count($chunk),
                'successful' => $orders->count(),
                'total_processed' => $orderDetails->count(),
                'payload_keys' => $data->keys()->toArray(),
            ]);
        }

        return $orderDetails;
    }

    /**
     * Get open orders by channel
     */
    public function getOpenOrdersByChannel(int $userId, string $channel): Collection
    {
        $allOrders = $this->getAllOpenOrders($userId);

        // Filter by channel
        $channelOrders = $allOrders->filter(function ($order) use ($channel) {
            return isset($order['Channel']) && $order['Channel'] === $channel;
        });

        Log::info('Filtered open orders by channel', [
            'user_id' => $userId,
            'channel' => $channel,
            'total_orders' => $allOrders->count(),
            'channel_orders' => $channelOrders->count(),
        ]);

        return $channelOrders;
    }

    /**
     * Get open orders statistics
     */
    public function getOpenOrdersStats(int $userId): array
    {
        $orders = $this->getAllOpenOrders($userId);

        if ($orders->isEmpty()) {
            return [
                'total_orders' => 0,
                'total_value' => 0,
                'average_value' => 0,
                'channels' => [],
                'status_breakdown' => [],
            ];
        }

        $totalValue = $orders->sum('TotalValue');
        $totalOrders = $orders->count();

        $channelStats = $orders->groupBy('Channel')
            ->map(function ($channelOrders) {
                return [
                    'count' => $channelOrders->count(),
                    'total_value' => $channelOrders->sum('TotalValue'),
                    'average_value' => $channelOrders->avg('TotalValue'),
                ];
            });

        $statusStats = $orders->groupBy('Status')
            ->map(function ($statusOrders) {
                return [
                    'count' => $statusOrders->count(),
                    'total_value' => $statusOrders->sum('TotalValue'),
                    'average_value' => $statusOrders->avg('TotalValue'),
                ];
            });

        return [
            'total_orders' => $totalOrders,
            'total_value' => $totalValue,
            'average_value' => $totalOrders > 0 ? $totalValue / $totalOrders : 0,
            'channels' => $channelStats->toArray(),
            'status_breakdown' => $statusStats->toArray(),
            'fetched_at' => now()->toISOString(),
        ];
    }

    /**
     * Process (mark as processed) an open order
     */
    public function processOrder(int $userId, string $orderId): ApiResponse
    {
        $sessionToken = $this->sessionManager->getValidSessionToken($userId);

        if (! $sessionToken) {
            return ApiResponse::error('No valid session token available');
        }

        $request = ApiRequest::post('Orders/ProcessOrder', [
            'orderId' => $orderId,
        ]);

        Log::info('Processing open order', [
            'user_id' => $userId,
            'order_id' => $orderId,
        ]);

        return $this->client->makeRequest($request, $sessionToken);
    }

    /**
     * Batch process multiple open orders
     */
    public function batchProcessOrders(int $userId, array $orderIds): array
    {
        $results = [];

        Log::info('Batch processing open orders', [
            'user_id' => $userId,
            'order_count' => count($orderIds),
        ]);

        foreach ($orderIds as $orderId) {
            $response = $this->processOrder($userId, $orderId);

            $results[$orderId] = [
                'success' => $response->isSuccess(),
                'error' => $response->error,
                'status_code' => $response->statusCode,
            ];
        }

        $successful = collect($results)->where('success', true)->count();
        $failed = count($results) - $successful;

        Log::info('Batch processing completed', [
            'user_id' => $userId,
            'total_orders' => count($orderIds),
            'successful' => $successful,
            'failed' => $failed,
        ]);

        return [
            'results' => $results,
            'summary' => [
                'total' => count($orderIds),
                'successful' => $successful,
                'failed' => $failed,
                'success_rate' => count($orderIds) > 0 ? ($successful / count($orderIds)) * 100 : 0,
            ],
        ];
    }

    /**
     * Get order identifiers (tags) for multiple orders by their IDs.
     * Returns a collection keyed by order ID with arrays of identifiers.
     */
    public function getIdentifiersByOrderIds(int $userId, array $orderIds): Collection
    {
        if (empty($orderIds)) {
            return collect();
        }

        $sessionToken = $this->sessionManager->getValidSessionToken($userId);

        if (! $sessionToken) {
            Log::error('No valid session token for order identifiers', ['user_id' => $userId]);

            return collect();
        }

        Log::info('Fetching order identifiers', [
            'user_id' => $userId,
            'order_count' => count($orderIds),
        ]);

        $request = ApiRequest::post('OpenOrders/GetIdentifiersByOrderIds', $orderIds)->asJson();
        $response = $this->client->makeRequest($request, $sessionToken);

        if ($response->isError()) {
            Log::warning('Failed to fetch order identifiers', [
                'user_id' => $userId,
                'order_count' => count($orderIds),
                'error' => $response->error,
            ]);

            return collect();
        }

        $data = $response->getData();

        Log::info('Order identifiers fetched', [
            'user_id' => $userId,
            'response_keys' => $data->keys()->toArray(),
            'data_count' => $data->count(),
        ]);

        // The API returns an object keyed by order ID, with each value being an array of identifier objects
        // Convert to collection for easier handling
        return collect($data->toArray());
    }
}
