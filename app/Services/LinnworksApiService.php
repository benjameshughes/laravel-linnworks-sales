<?php

namespace App\Services;

use App\Actions\Linnworks\Orders\CheckAndUpdateProcessedOrders;
use App\Actions\Linnworks\Orders\FetchOrdersWithDetails;
use App\DataTransferObjects\LinnworksOrder;
use App\DataTransferObjects\Linnworks\ProcessedOrdersResult;
use App\Models\LinnworksConnection;
use App\Services\Linnworks\Auth\AuthenticationService;
use App\Services\Linnworks\Auth\SessionManager;
use App\Services\Linnworks\Orders\OpenOrdersService;
use App\Services\Linnworks\Orders\OrdersApiService;
use App\Services\Linnworks\Orders\ProcessedOrdersService;
use App\Services\Linnworks\Products\ProductsApiService;
use App\ValueObjects\Linnworks\SessionToken;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class LinnworksApiService
{
    private const DEFAULT_ORDER_PAGE_SIZE = 200;
    private const DEFAULT_PRODUCT_PAGE_SIZE = 200;

    public function __construct(
        private readonly AuthenticationService $authenticationService,
        private readonly SessionManager $sessions,
        private readonly OrdersApiService $orders,
        private readonly ProcessedOrdersService $processedOrders,
        private readonly OpenOrdersService $openOrders,
        private readonly ProductsApiService $products,
        private readonly FetchOrdersWithDetails $fetchOrdersWithDetails,
        private readonly CheckAndUpdateProcessedOrders $checkProcessedOrders,
    ) {}

    /**
     * Determine if the newer Linnworks stack is ready for use.
     */
    public function isConfigured(): bool
    {
        return LinnworksConnection::query()->active()->exists();
    }

    /**
     * Attempt to authenticate (refresh the session) for the current tenant.
     */
    public function authenticate(?int $userId = null): bool
    {
        try {
            $userId = $this->resolveUserId($userId);
            return $this->ensureSession($userId) !== null;
        } catch (RuntimeException $exception) {
            Log::warning('Cannot authenticate without an active Linnworks connection.', [
                'error' => $exception->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Fetch a page of orders and return DTOs for downstream consumers.
     */
    public function getOrders(
        ?Carbon $from = null,
        ?Carbon $to = null,
        int $pageNumber = 1,
        int $entriesPerPage = 100,
        ?int $userId = null
    ): Collection {
        try {
            $userId = $this->resolveUserId($userId);
            $from ??= Carbon::now()->subDays(config('linnworks.sync.default_date_range', 30));
            $to ??= Carbon::now();

            $response = $this->orders->getOrders($userId, $from, $to, $pageNumber, $entriesPerPage);

            if ($response->isError()) {
                Log::error('Failed to fetch Linnworks orders.', [
                    'user_id' => $userId,
                    'error' => $response->error,
                    'status' => $response->statusCode,
                ]);
                return collect();
            }

            $payload = $response->getData();
            $orders = collect($payload->get('Data', []));

            return $orders->map(fn (array $order) => LinnworksOrder::fromArray($order));
        } catch (\Throwable $exception) {
            Log::error('Unhandled error fetching Linnworks orders.', [
                'error' => $exception->getMessage(),
            ]);
            return collect();
        }
    }

    /**
     * Paginated processed order search with meta information.
     */
    public function getProcessedOrders(
        ?Carbon $from = null,
        ?Carbon $to = null,
        int $pageNumber = 1,
        int $entriesPerPage = 200,
        ?int $userId = null
    ): ProcessedOrdersResult {
        try {
            $userId = $this->resolveUserId($userId);
            $from ??= Carbon::now()->subDays(config('linnworks.sync.default_date_range', 30));
            $to ??= Carbon::now();

            $response = $this->processedOrders->searchProcessedOrders(
                $userId,
                $from,
                $to,
                filters: [],
                page: $pageNumber,
                entriesPerPage: $entriesPerPage,
            );

            if ($response->isError()) {
                Log::error('Failed to fetch processed orders.', [
                    'user_id' => $userId,
                    'error' => $response->error,
                ]);

                return new ProcessedOrdersResult(
                    orders: collect(),
                    hasMorePages: false,
                    totalResults: 0,
                    currentPage: $pageNumber,
                    entriesPerPage: $entriesPerPage,
                );
            }

            $data = $response->getData();
            $ordersArray = $this->normaliseResultsPayload($data);

            $orders = collect($ordersArray)
                ->map(fn (array $order) => LinnworksOrder::fromArray($order));

            // Handle different API response structures for total count
            $processedOrders = $data->get('ProcessedOrders');
            $totalResults = (int) (
                $data->get('ResultsCount')
                ?? (is_array($processedOrders) ? ($processedOrders['TotalEntries'] ?? null) : null)
                ?? count($ordersArray)
            );
            // Calculate total pages needed and continue until we've attempted them all
            $totalPages = (int) ceil($totalResults / $entriesPerPage);
            $hasMore = $pageNumber < $totalPages && !$orders->isEmpty();

            Log::info('Processed orders page fetched', [
                'user_id' => $userId,
                'page' => $pageNumber,
                'entries_per_page' => $entriesPerPage,
                'orders_in_page' => $orders->count(),
                'total_results' => $totalResults,
                'has_more_pages' => $hasMore,
                'from' => $from->toISOString(),
                'to' => $to->toISOString(),
                'payload_keys' => $data->keys()->toArray(),
            ]);

            if ($orders->isEmpty()) {
                Log::debug('Processed orders payload snapshot', [
                    'raw' => $data->toArray(),
                ]);
            }

            return new ProcessedOrdersResult(
                orders: $orders,
                hasMorePages: $hasMore,
                totalResults: $totalResults,
                currentPage: $pageNumber,
                entriesPerPage: $entriesPerPage,
            );
        } catch (\Throwable $exception) {
            Log::error('Unhandled error fetching processed orders.', [
                'error' => $exception->getMessage(),
            ]);

            return new ProcessedOrdersResult(
                orders: collect(),
                hasMorePages: false,
                totalResults: 0,
                currentPage: $pageNumber,
                entriesPerPage: $entriesPerPage,
            );
        }
    }

    private function normaliseResultsPayload(Collection $payload): array
    {
        $arrayPayload = $payload->toArray();

        // Try ProcessedOrders response structure first
        if (isset($arrayPayload['ProcessedOrders']['Data'])) {
            return $arrayPayload['ProcessedOrders']['Data'];
        }

        // Try Results (for other endpoints)
        $raw = $arrayPayload['Results'] ?? null;

        if ($raw === null && isset($arrayPayload['Data'])) {
            $raw = $arrayPayload['Data']['Results'] ?? $arrayPayload['Data'];
        }

        if ($raw === null && isset($arrayPayload[0]) && is_array($arrayPayload[0])) {
            $raw = $arrayPayload;
        }

        return is_array($raw) ? $raw : [];
    }

    /**
     * Retrieve all processed orders within a window.
     */
    public function getAllProcessedOrders(
        ?Carbon $from = null,
        ?Carbon $to = null,
        array $filters = [],
        int $maxOrders = 10_000,
        ?int $userId = null
    ): Collection {
        try {
            $userId = $this->resolveUserId($userId);
            $from ??= Carbon::now()->subDays(config('linnworks.sync.default_date_range', 30));
            $to ??= Carbon::now();

            $orders = $this->processedOrders->getAllProcessedOrders(
                $userId,
                $from,
                $to,
                $filters,
                $maxOrders,
            );
            $mapped = $orders
                ->map(fn ($order) => $order instanceof LinnworksOrder ? $order : LinnworksOrder::fromArray(is_array($order) ? $order : (array) $order))
                ->take($maxOrders);

            Log::info('All processed orders aggregated', [
                'user_id' => $userId,
                'from' => $from->toISOString(),
                'to' => $to->toISOString(),
                'raw_count' => $orders->count(),
                'mapped_count' => $mapped->count(),
                'max_orders' => $maxOrders,
            ]);

            return $mapped;
        } catch (\Throwable $exception) {
            Log::error('Unhandled error fetching all processed orders.', [
                'error' => $exception->getMessage(),
            ]);
            return collect();
        }
    }

    /**
     * Fetch detailed order information for a single order.
     */
    public function getOrderDetails(string $orderId, ?int $userId = null): ?LinnworksOrder
    {
        try {
            $userId = $this->resolveUserId($userId);
            $response = $this->orders->getOrderById($userId, $orderId);

            if ($response->isError()) {
                Log::warning('Failed to retrieve order details from Linnworks.', [
                    'order_id' => $orderId,
                    'error' => $response->error,
                ]);
                return null;
            }

            $data = $response->getData()->first();

            return $data ? LinnworksOrder::fromArray($data) : null;
        } catch (\Throwable $exception) {
            Log::error('Unhandled error fetching order details.', [
                'order_id' => $orderId,
                'error' => $exception->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Fetch all orders with their expanded details (items, totals, etc.).
     */
    public function getAllOrdersWithDetails(
        Carbon $from = null,
        Carbon $to = null,
        ?int $userId = null
    ): array {
        $from ??= Carbon::now()->subDays(config('linnworks.sync.default_date_range', 30));
        $to ??= Carbon::now();

        try {
            $userId = $this->resolveUserId($userId);

            return $this->fetchOrdersWithDetails->handle(
                $userId,
                $from,
                $to,
                self::DEFAULT_ORDER_PAGE_SIZE,
            );
        } catch (\Throwable $exception) {
            Log::error('Unhandled error fetching orders with details.', [
                'error' => $exception->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Verify connectivity to Linnworks.
     */
    public function testConnection(?int $userId = null): bool
    {
        try {
            $userId = $this->resolveUserId($userId);
            $sessionToken = $this->ensureSession($userId);

            if (!$sessionToken) {
                return false;
            }

            return $this->authenticationService->testConnection($sessionToken);
        } catch (\Throwable $exception) {
            Log::error('Linnworks connection test failed.', [
                'error' => $exception->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Retrieve processed orders with full details (including line items) by IDs.
     */
    public function getProcessedOrdersWithDetails(array $orderIds, ?int $userId = null): Collection
    {
        if (empty($orderIds)) {
            return collect();
        }

        try {
            $userId = $this->resolveUserId($userId);
            return $this->processedOrders->getProcessedOrdersWithDetails($userId, $orderIds);
        } catch (\Throwable $exception) {
            Log::error('Failed to fetch processed orders with details.', [
                'order_count' => count($orderIds),
                'error' => $exception->getMessage(),
            ]);
            return collect();
        }
    }

    /**
     * Retrieve all open order identifiers.
     */
    public function getAllOpenOrderIds(?int $userId = null): Collection
    {
        try {
            $userId = $this->resolveUserId($userId);
            return $this->openOrders->getOpenOrderIds($userId);
        } catch (\Throwable $exception) {
            Log::error('Failed to gather open order identifiers.', [
                'error' => $exception->getMessage(),
            ]);
            return collect();
        }
    }

    /**
     * Retrieve order identifiers (tags) for multiple orders by their IDs.
     * Returns a collection keyed by order ID with arrays of identifiers.
     */
    public function getIdentifiersByOrderIds(array $orderIds, ?int $userId = null): Collection
    {
        if (empty($orderIds)) {
            return collect();
        }

        try {
            $userId = $this->resolveUserId($userId);
            return $this->openOrders->getIdentifiersByOrderIds($userId, $orderIds);
        } catch (\Throwable $exception) {
            Log::error('Failed to fetch order identifiers.', [
                'order_count' => count($orderIds),
                'error' => $exception->getMessage(),
            ]);
            return collect();
        }
    }

    /**
     * Retrieve details for one or more open orders.
     */
    public function getOpenOrderDetails(array $orderUuids, ?int $userId = null): Collection
    {
        if (empty($orderUuids)) {
            return collect();
        }

        try {
            $userId = $this->resolveUserId($userId);
            $details = $this->openOrders->getMultipleOpenOrderDetails($userId, $orderUuids);

            return $details->map(fn ($order) => LinnworksOrder::fromArray(is_array($order) ? $order : $order->toArray()));
        } catch (\Throwable $exception) {
            Log::error('Failed to fetch open order details.', [
                'error' => $exception->getMessage(),
            ]);
            return collect();
        }
    }

    /**
     * Convenience wrapper for fetching order details by IDs.
     */
    public function getOrdersByIds(array $orderIds, ?int $userId = null): Collection
    {
        if (empty($orderIds)) {
            return collect();
        }

        try {
            $userId = $this->resolveUserId($userId);
            $response = $this->orders->getOrdersByIds($userId, $orderIds);

            if ($response->isError()) {
                Log::warning('Failed to fetch order details by IDs.', [
                    'order_count' => count($orderIds),
                    'error' => $response->error,
                ]);
                return collect();
            }

            $rawData = $response->getData();

            // DEBUG: Log a sample order to see what fields are present
            if ($rawData->isNotEmpty()) {
                $sampleOrder = $rawData->first();
                $sampleArray = is_array($sampleOrder) ? $sampleOrder : (array) $sampleOrder;
                Log::info('getOrdersByIds: Sample API response', [
                    'order_count' => $rawData->count(),
                    'sample_keys' => array_keys($sampleArray),
                    'has_ShippingInfo' => isset($sampleArray['ShippingInfo']),
                    'has_Notes' => isset($sampleArray['Notes']),
                    'has_ExtendedProperties' => isset($sampleArray['ExtendedProperties']),
                    'has_OrderIdentifiers' => isset($sampleArray['OrderIdentifiers']),
                ]);
            }

            return $rawData->map(fn ($order) => LinnworksOrder::fromArray(is_array($order) ? $order : (array) $order));
        } catch (\Throwable $exception) {
            $context = [
                'error' => $exception->getMessage(),
                'exception_class' => get_class($exception),
            ];

            // If it's a LinnworksApiException, get the full context including API response
            if (method_exists($exception, 'context')) {
                $context = array_merge($context, $exception->context());
            }

            Log::error('Unhandled error fetching order details by IDs.', $context);
            return collect();
        }
    }

    /**
     * Retrieve all open orders with full detail payloads.
     */
    public function getAllOpenOrders(?int $userId = null): Collection
    {
        $orderIds = $this->getAllOpenOrderIds($userId);

        if ($orderIds->isEmpty()) {
            return collect();
        }

        $orders = collect();

        foreach ($orderIds->chunk(50) as $chunk) {
            $orders = $orders->merge($this->getOrdersByIds($chunk->toArray(), $userId));
            usleep(200_000); // Respect rate limits between batches
        }

        return $orders;
    }

    /**
     * Retrieve open orders received within the supplied window.
     */
    public function getRecentOpenOrders(
        ?int $userId = null,
        int $days = 7,
        int $maxOrders = 500
    ): Collection {
        try {
            $userId = $this->resolveUserId($userId);
            $orders = $this->openOrders->getAllOpenOrders(
                $userId,
                entriesPerPage: self::DEFAULT_ORDER_PAGE_SIZE,
                maxOrders: $maxOrders,
            );

            $cutoff = Carbon::now()->subDays(max(1, $days))->startOfDay();

            $filtered = $orders
                ->map(fn ($order) => $order instanceof LinnworksOrder
                    ? $order
                    : LinnworksOrder::fromArray(is_array($order) ? $order : (array) $order))
                ->filter(fn (LinnworksOrder $order) =>
                    $order->receivedDate === null || $order->receivedDate->greaterThanOrEqualTo($cutoff))
                ->values();

            Log::info('Recent open orders fetched', [
                'user_id' => $userId,
                'requested_days' => $days,
                'max_orders' => $maxOrders,
                'fetched_count' => $orders->count(),
                'filtered_count' => $filtered->count(),
                'cutoff' => $cutoff->toISOString(),
            ]);

            return $filtered;
        } catch (\Throwable $exception) {
            Log::error('Failed to fetch recent open orders.', [
                'error' => $exception->getMessage(),
            ]);

            return collect();
        }
    }

    /**
     * Retrieve inventory catalog entries (basic view).
     */
    public function getAllInventoryItems(?int $userId = null): Collection
    {
        try {
            $userId = $this->resolveUserId($userId);
            return $this->products->getAllProducts($userId, self::DEFAULT_PRODUCT_PAGE_SIZE);
        } catch (\Throwable $exception) {
            Log::error('Failed to fetch inventory items.', [
                'error' => $exception->getMessage(),
            ]);
            return collect();
        }
    }

    /**
     * Retrieve full inventory details in bulk.
     */
    public function getAllInventoryItemsFull(?int $userId = null): Collection
    {
        try {
            $userId = $this->resolveUserId($userId);
            $page = 1;
            $items = collect();

            do {
                $response = $this->products->getInventoryPaginated($userId, $page, self::DEFAULT_PRODUCT_PAGE_SIZE);

                if ($response->isError()) {
                    Log::warning('Failed to fetch detailed inventory page.', [
                        'page' => $page,
                        'error' => $response->error,
                    ]);
                    break;
                }

                $data = $response->getData();
                $pageItems = $data->get('Items') ?? $data->toArray();
                $collection = collect($pageItems);

                if ($collection->isEmpty()) {
                    break;
                }

                $items = $items->merge($collection);
                $page++;
                usleep(400_000); // respect rate limits
            } while ($collection->count() === self::DEFAULT_PRODUCT_PAGE_SIZE);

            return $items;
        } catch (\Throwable $exception) {
            Log::error('Unhandled error fetching detailed inventory.', [
                'error' => $exception->getMessage(),
            ]);
            return collect();
        }
    }

    /**
     * Fetch detailed product information by stock IDs.
     */
    public function getStockItemsFullByIds(array $stockItemIds, ?int $userId = null): Collection
    {
        if (empty($stockItemIds)) {
            return collect();
        }

        try {
            $userId = $this->resolveUserId($userId);
            return $this->products->getMultipleProductDetails($userId, $stockItemIds);
        } catch (\Throwable $exception) {
            Log::error('Failed to fetch full stock items by IDs.', [
                'error' => $exception->getMessage(),
            ]);
            return collect();
        }
    }

    /**
     * Synchronise processed flags for local orders.
     */
    public function checkAndUpdateProcessedOrders(?int $userId = null): bool
    {
        $orders = \App\Models\Order::where('received_date', '>=', Carbon::now()->subDays(90))
            ->whereNotNull('linnworks_order_id')
            ->pluck('linnworks_order_id')
            ->filter()
            ->values();

        if ($orders->isEmpty()) {
            return true;
        }

        try {
            $userId = $this->resolveUserId($userId);

            return $this->checkProcessedOrders->handle($userId, $orders);
        } catch (\Throwable $exception) {
            Log::error('Unhandled error while checking processed orders.', [
                'error' => $exception->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Resolve which user/connection should be used for API calls.
     */
    private function resolveUserId(?int $userId = null): int
    {
        if ($userId !== null) {
            return $userId;
        }

        $connection = LinnworksConnection::query()
            ->active()
            ->orderByDesc('updated_at')
            ->first();

        if (!$connection) {
            throw new RuntimeException('No active Linnworks connection configured.');
        }

        return $connection->user_id;
    }

    /**
     * Ensure a valid session exists for the given user.
     */
    private function ensureSession(int $userId): ?SessionToken
    {
        $sessionToken = $this->sessions->getValidSessionToken($userId);

        if ($sessionToken) {
            return $sessionToken;
        }

        return $this->sessions->refreshSessionToken($userId);
    }
}
