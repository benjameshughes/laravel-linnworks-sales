<?php

namespace App\Services\Linnworks\Orders;

use App\Services\Linnworks\Auth\SessionManager;
use App\Services\Linnworks\Core\LinnworksClient;
use App\ValueObjects\Linnworks\ApiRequest;
use App\ValueObjects\Linnworks\ApiResponse;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class OrdersApiService
{
    public function __construct(
        private readonly LinnworksClient $client,
        private readonly SessionManager $sessionManager,
    ) {}

    /**
     * Get orders with date range and pagination
     */
    public function getOrders(
        int $userId,
        Carbon $from,
        Carbon $to,
        int $page = 1,
        int $entriesPerPage = 200
    ): ApiResponse {
        $sessionToken = $this->sessionManager->getValidSessionToken($userId);

        if (! $sessionToken) {
            return ApiResponse::error('No valid session token available');
        }

        $request = ApiRequest::post('Orders/GetOrders', [
            'from' => $from->copy()->utc()->format('Y-m-d\TH:i:s.v\Z'),
            'to' => $to->copy()->utc()->format('Y-m-d\TH:i:s.v\Z'),
            'pageNumber' => $page,
            'entriesPerPage' => $entriesPerPage,
            'fulfilmentCenter' => '',
            'searchField' => '',
            'searchTerm' => '',
            'sorting' => [
                'Direction' => 0,
                'Field' => 'dReceivedDate',
            ],
        ]);

        Log::info('Fetching orders from Linnworks', [
            'user_id' => $userId,
            'from' => $from->toISOString(),
            'to' => $to->toISOString(),
            'page' => $page,
            'entries_per_page' => $entriesPerPage,
        ]);

        return $this->client->makeRequest($request, $sessionToken);
    }

    /**
     * Get order details by ID
     */
    public function getOrderById(int $userId, string $orderId): ApiResponse
    {
        $sessionToken = $this->sessionManager->getValidSessionToken($userId);

        if (! $sessionToken) {
            return ApiResponse::error('No valid session token available');
        }

        $response = $this->getOrdersByIds($userId, [$orderId]);

        if ($response->isError()) {
            return $response;
        }

        $order = $response->getData()->first();

        return ApiResponse::success($order ? [$order] : []);
    }

    /**
     * Get multiple orders by IDs
     */
    public function getOrdersByIds(int $userId, array $orderIds): ApiResponse
    {
        $sessionToken = $this->sessionManager->getValidSessionToken($userId);

        if (! $sessionToken) {
            return ApiResponse::error('No valid session token available');
        }

        $request = ApiRequest::post('Orders/GetOrdersById', [
            'pkOrderIds' => array_values($orderIds),
        ]);

        Log::info('Fetching multiple orders from Linnworks', [
            'user_id' => $userId,
            'order_count' => count($orderIds),
        ]);

        return $this->client->makeRequest($request, $sessionToken);
    }

    /**
     * Get all orders in date range (handles pagination automatically)
     */
    public function getAllOrders(
        int $userId,
        Carbon $from,
        Carbon $to,
        int $maxOrders = 5000
    ): Collection {
        $allOrders = collect();
        $page = 1;
        $entriesPerPage = 200;

        Log::info('Starting to fetch all orders', [
            'user_id' => $userId,
            'from' => $from->toISOString(),
            'to' => $to->toISOString(),
            'max_orders' => $maxOrders,
        ]);

        do {
            $response = $this->getOrders($userId, $from, $to, $page, $entriesPerPage);

            if ($response->isError()) {
                Log::error('Error fetching orders page', [
                    'user_id' => $userId,
                    'page' => $page,
                    'error' => $response->error,
                ]);
                break;
            }

            $payload = $response->getData();
            $orders = collect($payload->get('Data', []));
            $totalResults = $payload->get('TotalResults', $orders->count());
            $allOrders = $allOrders->merge($orders);

            Log::info('Fetched orders page', [
                'user_id' => $userId,
                'page' => $page,
                'orders_in_page' => $orders->count(),
                'total_orders' => $allOrders->count(),
                'total_results' => $totalResults,
            ]);

            $page++;

            // Safety checks
            if ($allOrders->count() >= $maxOrders) {
                Log::warning('Maximum orders limit reached', [
                    'user_id' => $userId,
                    'total_orders' => $allOrders->count(),
                    'max_orders' => $maxOrders,
                ]);
                break;
            }

            if ($orders->count() < $entriesPerPage) {
                Log::info('All orders fetched (last page)', [
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
     * Update order status
     */
    public function updateOrderStatus(int $userId, string $orderId, string $status): ApiResponse
    {
        $sessionToken = $this->sessionManager->getValidSessionToken($userId);

        if (! $sessionToken) {
            return ApiResponse::error('No valid session token available');
        }

        $request = ApiRequest::post('Orders/UpdateOrderStatus', [
            'orderId' => $orderId,
            'status' => $status,
        ]);

        Log::info('Updating order status', [
            'user_id' => $userId,
            'order_id' => $orderId,
            'status' => $status,
        ]);

        return $this->client->makeRequest($request, $sessionToken);
    }

    /**
     * Add order note
     */
    public function addOrderNote(int $userId, string $orderId, string $note): ApiResponse
    {
        $sessionToken = $this->sessionManager->getValidSessionToken($userId);

        if (! $sessionToken) {
            return ApiResponse::error('No valid session token available');
        }

        $request = ApiRequest::post('Orders/AddOrderNote', [
            'orderId' => $orderId,
            'note' => $note,
        ]);

        Log::info('Adding order note', [
            'user_id' => $userId,
            'order_id' => $orderId,
            'note_length' => strlen($note),
        ]);

        return $this->client->makeRequest($request, $sessionToken);
    }

    /**
     * Get order notes
     */
    public function getOrderNotes(int $userId, string $orderId): ApiResponse
    {
        $sessionToken = $this->sessionManager->getValidSessionToken($userId);

        if (! $sessionToken) {
            return ApiResponse::error('No valid session token available');
        }

        $request = ApiRequest::post('Orders/GetOrderNotes', [
            'orderId' => $orderId,
        ]);

        Log::info('Fetching order notes', [
            'user_id' => $userId,
            'order_id' => $orderId,
        ]);

        return $this->client->makeRequest($request, $sessionToken);
    }

    /**
     * Get order statistics for a date range
     */
    public function getOrderStats(int $userId, Carbon $from, Carbon $to): array
    {
        $response = $this->getOrders($userId, $from, $to, 1, 1000);

        if ($response->isError()) {
            return [
                'total_orders' => 0,
                'total_value' => 0,
                'average_value' => 0,
                'error' => $response->error,
            ];
        }

        $orders = $response->getData();
        $totalValue = $orders->sum('TotalValue');
        $totalOrders = $orders->count();

        return [
            'total_orders' => $totalOrders,
            'total_value' => $totalValue,
            'average_value' => $totalOrders > 0 ? $totalValue / $totalOrders : 0,
            'date_range' => [
                'from' => $from->toISOString(),
                'to' => $to->toISOString(),
            ],
        ];
    }
}
