<?php

namespace App\Services\Linnworks\Orders;

use App\Services\Linnworks\Core\LinnworksClient;
use App\Services\Linnworks\Auth\SessionManager;
use App\ValueObjects\Linnworks\ApiRequest;
use App\ValueObjects\Linnworks\ApiResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ProcessedOrdersService
{
    public function __construct(
        private readonly LinnworksClient $client,
        private readonly SessionManager $sessionManager,
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
        
        if (!$sessionToken) {
            return ApiResponse::error('No valid session token available');
        }

        $searchParams = $this->buildSearchParams($from, $to, $filters, $page, $entriesPerPage);

        $request = ApiRequest::post('ProcessedOrders/SearchProcessedOrders', $searchParams);

        Log::info('Searching processed orders', [
            'user_id' => $userId,
            'from' => $from->toISOString(),
            'to' => $to->toISOString(),
            'filters' => $filters,
            'page' => $page,
            'entries_per_page' => $entriesPerPage,
        ]);

        return $this->client->makeRequest($request, $sessionToken);
    }

    /**
     * Get all processed orders in date range
     */
    public function getAllProcessedOrders(
        int $userId,
        Carbon $from,
        Carbon $to,
        array $filters = [],
        int $maxOrders = 10000
    ): Collection {
        $allOrders = collect();
        $page = 1;
        $entriesPerPage = 200;

        Log::info('Starting to fetch all processed orders', [
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

            $data = $response->getData();
            $orders = collect($data->get('Results', []));
            $allOrders = $allOrders->merge($orders);
            
            Log::info('Fetched processed orders page', [
                'user_id' => $userId,
                'page' => $page,
                'orders_in_page' => $orders->count(),
                'total_orders' => $allOrders->count(),
                'total_results' => $data->get('ResultsCount', 0),
            ]);

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
     * Get processed order by ID
     */
    public function getProcessedOrderById(int $userId, string $orderId): ApiResponse
    {
        $sessionToken = $this->sessionManager->getValidSessionToken($userId);
        
        if (!$sessionToken) {
            return ApiResponse::error('No valid session token available');
        }

        $request = ApiRequest::post('Orders/GetProcessedOrderById', [
            'orderId' => $orderId,
        ]);

        Log::info('Fetching processed order details', [
            'user_id' => $userId,
            'order_id' => $orderId,
        ]);

        return $this->client->makeRequest($request, $sessionToken);
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

        $data = $response->getData();
        $orders = $data->get('Results', collect());
        
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
            'results_count' => $data->get('ResultsCount', 0),
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
        $request = [
            'DateField' => 'received',  // Search by received date, not processed date
            'FromDate' => $from->copy()->utc()->format('Y-m-d\TH:i:s'),
            'ToDate' => $to->copy()->utc()->format('Y-m-d\TH:i:s'),
            'PageNumber' => $page,
            'ResultsPerPage' => $entriesPerPage,
            'SortColumn' => 'ReceivedDate',  // Sort by received date too
            'SortDirection' => 'DESC',
        ];

        $filterMap = [
            'channel' => 'Channel',
            'status' => 'Status',
            'reference' => 'Reference',
            'email' => 'Email',
            'minValue' => 'MinValue',
            'maxValue' => 'MaxValue',
            'country' => 'Country',
            'sku' => 'SKU',
            'tag' => 'Tag',
        ];

        foreach ($filterMap as $inputKey => $apiKey) {
            if (!empty($filters[$inputKey])) {
                $request[$apiKey] = $filters[$inputKey];
            }
        }

        return ['request' => $request];
    }
}
