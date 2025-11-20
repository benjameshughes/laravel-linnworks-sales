<?php

namespace App\Services\Linnworks\Orders;

use App\Services\Linnworks\Auth\SessionManager;
use App\Services\Linnworks\Core\LinnworksClient;
use App\Services\Linnworks\Parsers\ProcessedOrdersResponseParser;
use App\ValueObjects\Linnworks\ApiRequest;
use App\ValueObjects\Linnworks\ApiResponse;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class ProcessedOrdersService
{
    public function __construct(
        private readonly LinnworksClient $client,
        private readonly SessionManager $sessionManager,
        private readonly ProcessedOrdersResponseParser $parser,
    ) {}

    /**
     * Search processed orders with comprehensive filters
     */
    public function searchProcessedOrders(
        int $userId,
        Carbon $from,
        Carbon $to,
        array $filters = [],
        int $page = 1,
        int $entriesPerPage = 200
    ): ApiResponse {
        $sessionToken = $this->sessionManager->getValidSessionToken($userId);

        if (! $sessionToken) {
            return ApiResponse::error('No valid session token available');
        }

        $dateField = $filters['dateField'] ?? 'received';

        // Use Laravel HTTP client directly - matching working Guzzle example
        $body = [
            'request' => [
                'FromDate' => $from->copy()->utc()->toISOString(),
                'DateField' => $dateField,
                'ToDate' => $to->copy()->utc()->toISOString(),
                'PageNumber' => $page,
                'ResultsPerPage' => $entriesPerPage,
            ],
        ];

        Log::debug('Searching processed orders', [
            'user_id' => $userId,
            'from' => $from->toISOString(),
            'to' => $to->toISOString(),
            'filters' => $filters,
            'page' => $page,
            'entries_per_page' => $entriesPerPage,
        ]);

        $response = \Illuminate\Support\Facades\Http::withHeaders([
            'Authorization' => $sessionToken->token,
            'accept' => 'application/json',
            'content-type' => 'application/json',
        ])->post($sessionToken->getBaseUrl().'ProcessedOrders/SearchProcessedOrders', $body);

        if ($response->failed()) {
            return ApiResponse::error('API request failed: '.$response->body());
        }

        return ApiResponse::fromHttpResponse($response);
    }

    /**
     * Get all processed orders in date range
     *
     * @deprecated Use streamProcessedOrderIds() for memory-efficient historical imports
     */
    public function getAllProcessedOrders(
        int $userId,
        Carbon $from,
        Carbon $to,
        array $filters = [],
        int $maxOrders = 10000,
        ?\Closure $progressCallback = null
    ): Collection {
        $allOrders = collect();
        $page = 1;
        $entriesPerPage = 200;

        Log::debug('Starting to fetch all processed orders', [
            'user_id' => $userId,
            'from' => $from->toISOString(),
            'to' => $to->toISOString(),
            'filters' => $filters,
            'max_orders' => $maxOrders,
        ]);

        do {
            $response = $this->searchProcessedOrders($userId, $from, $to, $filters, $page, $entriesPerPage);

            if ($response->isError()) {
                Log::error('Error fetching processed orders page', [
                    'user_id' => $userId,
                    'page' => $page,
                    'error' => $response->error,
                ]);
                break;
            }

            // Use parser to extract data
            $orders = $this->parser->parseOrders($response);
            $allOrders = $allOrders->merge($orders);

            $totalResults = $this->parser->getTotalEntries($response);
            $totalPages = $this->parser->getTotalPages($response);

            Log::debug('Fetched processed orders page', [
                'user_id' => $userId,
                'page' => $page,
                'orders_in_page' => $orders->count(),
                'total_orders' => $allOrders->count(),
                'total_results' => $totalResults,
                'total_pages' => $totalPages,
            ]);

            // Call progress callback if provided
            if ($progressCallback) {
                $progressCallback($page, $totalPages, $allOrders->count(), $totalResults);
            }

            $page++;

            // Safety checks
            if ($allOrders->count() >= $maxOrders) {
                Log::warning('Maximum processed orders limit reached', [
                    'user_id' => $userId,
                    'total_orders' => $allOrders->count(),
                    'max_orders' => $maxOrders,
                ]);
                break;
            }

            if ($orders->count() < $entriesPerPage) {
                Log::info('All processed orders fetched', [
                    'user_id' => $userId,
                    'total_orders' => $allOrders->count(),
                    'total_pages' => $page - 1,
                ]);
                break;
            }

        } while ($orders->count() === $entriesPerPage);

        return $allOrders;
    }

    /**
     * Stream processed order IDs page by page (memory-efficient)
     *
     * Yields collections of order IDs without loading all orders into memory.
     * Inspired by Christoph Rumpel's "Refactoring to Collections" approach.
     *
     * No artificial limits - streams all orders in the date range.
     * Memory is controlled by batch size (200 orders per page).
     *
     * @return \Generator<int, Collection> Yields Collection of order IDs per page
     */
    public function streamProcessedOrderIds(
        int $userId,
        Carbon $from,
        Carbon $to,
        array $filters = [],
        ?\Closure $progressCallback = null
    ): \Generator {
        $page = 1;
        $entriesPerPage = 200;
        $totalFetched = 0;

        Log::info('Starting to stream processed order IDs', [
            'user_id' => $userId,
            'from' => $from->toISOString(),
            'to' => $to->toISOString(),
            'filters' => $filters,
        ]);

        do {
            $response = $this->searchProcessedOrders($userId, $from, $to, $filters, $page, $entriesPerPage);

            if ($response->isError()) {
                Log::error('Error fetching processed orders page', [
                    'user_id' => $userId,
                    'page' => $page,
                    'error' => $response->error,
                ]);
                break;
            }

            // Use parser to extract data
            $orders = $this->parser->parseOrders($response);
            $totalResults = $this->parser->getTotalEntries($response);
            $totalPages = $this->parser->getTotalPages($response);

            // Extract just the order IDs (memory-efficient)
            $orderIds = $orders->pluck('pkOrderID')
                ->filter()
                ->values();

            $totalFetched += $orderIds->count();

            Log::info('Streamed processed order IDs page', [
                'user_id' => $userId,
                'page' => $page,
                'ids_in_page' => $orderIds->count(),
                'total_fetched' => $totalFetched,
                'total_results' => $totalResults,
                'total_pages' => $totalPages,
            ]);

            // Call progress callback if provided
            if ($progressCallback) {
                $progressCallback($page, $totalPages, $totalFetched, $totalResults);
            }

            // Yield this page's order IDs
            if ($orderIds->isNotEmpty()) {
                yield $orderIds;
            }

            $page++;

            // Stop when we've fetched all pages
            if ($orders->count() < $entriesPerPage) {
                Log::info('All processed order IDs streamed', [
                    'user_id' => $userId,
                    'total_fetched' => $totalFetched,
                    'total_pages' => $page - 1,
                ]);
                break;
            }

            // Free memory after yielding
            unset($orders, $orderIds, $response);

        } while (true);
    }

    /**
     * Get processed order by ID
     */
    public function getProcessedOrderById(int $userId, string $orderId): ApiResponse
    {
        $sessionToken = $this->sessionManager->getValidSessionToken($userId);

        if (! $sessionToken) {
            return ApiResponse::error('No valid session token available');
        }

        $request = ApiRequest::post('Orders/GetOrdersById', [
            'pkOrderIds' => [$orderId],
        ]);

        Log::info('Fetching processed order details', [
            'user_id' => $userId,
            'order_id' => $orderId,
        ]);

        return $this->client->makeRequest($request, $sessionToken);
    }

    /**
     * Get processed orders with full details (including items) by IDs
     */
    public function getProcessedOrdersWithDetails(int $userId, array $orderIds): Collection
    {
        if (empty($orderIds)) {
            return collect();
        }

        $sessionToken = $this->sessionManager->getValidSessionToken($userId);

        if (! $sessionToken) {
            Log::error('No valid session token available for getting processed order details');

            return collect();
        }

        $orders = collect();

        Log::info('Fetching processed order details', [
            'user_id' => $userId,
            'order_count' => count($orderIds),
        ]);

        foreach ($orderIds as $orderId) {
            try {
                $response = $this->getProcessedOrderById($userId, $orderId);

                if ($response->isError()) {
                    Log::warning('Failed to fetch processed order details', [
                        'order_id' => $orderId,
                        'error' => $response->error,
                    ]);

                    continue;
                }

                $orderData = $response->getData();
                if ($orderData->isNotEmpty()) {
                    // GetOrdersById returns array of orders, get first one
                    $order = $orderData->first();
                    if ($order) {
                        $orders->push($order);
                    }
                }

                // Rate limiting - be nice to Linnworks API
                usleep(50000); // 50ms between requests

            } catch (\Throwable $e) {
                Log::error('Exception fetching processed order details', [
                    'order_id' => $orderId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('Fetched processed order details complete', [
            'user_id' => $userId,
            'requested_count' => count($orderIds),
            'fetched_count' => $orders->count(),
        ]);

        return $orders;
    }

    /**
     * Get processed orders by channel
     */
    public function getProcessedOrdersByChannel(
        int $userId,
        string $channel,
        Carbon $from,
        Carbon $to,
        int $maxOrders = 5000
    ): Collection {
        $filters = [
            'channel' => $channel,
        ];

        return $this->getAllProcessedOrders($userId, $from, $to, $filters, $maxOrders);
    }

    /**
     * Get processed orders statistics
     */
    public function getProcessedOrdersStats(
        int $userId,
        Carbon $from,
        Carbon $to,
        array $filters = []
    ): array {
        $response = $this->searchProcessedOrders($userId, $from, $to, $filters, 1, 1000);

        if ($response->isError()) {
            return [
                'total_orders' => 0,
                'total_value' => 0,
                'total_profit' => 0,
                'average_value' => 0,
                'average_profit' => 0,
                'channels' => [],
                'error' => $response->error,
            ];
        }

        // Use parser to extract orders
        $orders = $this->parser->parseOrders($response);

        $totalValue = $orders->sum('TotalValue');
        $totalProfit = $orders->sum('Profit');
        $totalOrders = $orders->count();

        $channelStats = $orders->groupBy('Channel')
            ->map(function ($channelOrders) {
                return [
                    'count' => $channelOrders->count(),
                    'total_value' => $channelOrders->sum('TotalValue'),
                    'total_profit' => $channelOrders->sum('Profit'),
                    'average_value' => $channelOrders->avg('TotalValue'),
                    'average_profit' => $channelOrders->avg('Profit'),
                ];
            });

        return [
            'total_orders' => $totalOrders,
            'total_value' => $totalValue,
            'total_profit' => $totalProfit,
            'average_value' => $totalOrders > 0 ? $totalValue / $totalOrders : 0,
            'average_profit' => $totalOrders > 0 ? $totalProfit / $totalOrders : 0,
            'channels' => $channelStats->toArray(),
            'date_range' => [
                'from' => $from->toISOString(),
                'to' => $to->toISOString(),
            ],
            'filters_applied' => $filters,
            'results_count' => $this->parser->getTotalEntries($response),
        ];
    }

    /**
     * Build search parameters for the API
     */
    private function buildSearchParams(
        Carbon $from,
        Carbon $to,
        array $filters,
        int $page,
        int $entriesPerPage
    ): array {
        // Use 'processed' date field if specified in filters, otherwise 'received'
        $dateField = $filters['dateField'] ?? 'received';

        // Match the working Guzzle example EXACTLY: {"request": {...}}
        return [
            'request' => [
                'FromDate' => $from->copy()->utc()->format('Y-m-d\TH:i:s.v\Z'),
                'DateField' => $dateField,
                'ToDate' => $to->copy()->utc()->format('Y-m-d\TH:i:s.v\Z'),
                'PageNumber' => $page,
                'ResultsPerPage' => $entriesPerPage,
            ],
        ];
    }
}
