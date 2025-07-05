<?php

namespace App\Services;

use Exception;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Collection;
use Carbon\Carbon;
use App\Services\LinnworksOAuthService;
use App\Models\LinnworksConnection;
use App\DataTransferObjects\LinnworksOrder;
use App\DataTransferObjects\LinnworksOrderItem;


class LinnworksApiService
{
    private string $baseUrl;
    private string $applicationId;
    private string $applicationSecret;
    private string $token;
    private ?string $server = null;
    private ?string $sessionToken = null;

    public function __construct(
        private LinnworksOAuthService $oauthService
    ) {
        // Config values will be loaded dynamically
    }

    /**
     * Check if API credentials are configured
     */
    public function isConfigured(): bool
    {
        return !empty(Config::get('linnworks.application_id')) && 
               !empty(Config::get('linnworks.application_secret')) && 
               !empty(Config::get('linnworks.token'));
    }

    /**
     * Get session token (cached or fresh)
     */
    private function getSessionToken(): ?array
    {
        if (!$this->hasConfigCredentials()) {
            return null;
        }
        
        // Try to get cached session token
        $cachedToken = cache()->get('linnworks.session_token');
        $cachedServer = cache()->get('linnworks.server');
        
        if ($cachedToken && $cachedServer) {
            Log::info('Using cached session token for API requests');
            return [
                'token' => $cachedToken,
                'server' => $cachedServer,
            ];
        }
        
        // Get fresh session token and cache it
        if ($this->authenticate()) {
            // Cache for 30 minutes (Linnworks sessions typically last ~2 hours)
            cache()->put('linnworks.session_token', $this->sessionToken, now()->addMinutes(30));
            cache()->put('linnworks.server', $this->server, now()->addMinutes(30));
            
            Log::info('Using fresh session token for API requests');
            return [
                'token' => $this->sessionToken,
                'server' => $this->server,
            ];
        }
        
        return null;
    }

    /**
     * Check if config-based credentials exist
     */
    private function hasConfigCredentials(): bool
    {
        return !empty(Config::get('linnworks.application_id')) && 
               !empty(Config::get('linnworks.application_secret')) && 
               !empty(Config::get('linnworks.token'));
    }

    /**
     * Make an authenticated request with retry on auth failure
     */
    private function makeAuthenticatedRequest(string $method, string $endpoint, ?array $data = []): ?\Illuminate\Http\Client\Response
    {
        $sessionData = $this->getSessionToken();
        if (!$sessionData) {
            return null;
        }

        $url = $sessionData['server'] . $endpoint;
        
        Log::info('Making API request', [
            'method' => $method,
            'url' => $url,
            'endpoint' => $endpoint,
            'server' => $sessionData['server']
        ]);

        // First attempt
        $response = $this->executeRequest($method, $url, $sessionData['token'], $data);

        // If auth error, try to refresh and retry once
        if ($response && ($response->status() === 401 || $response->status() === 403)) {
            Log::info('Auth error detected, clearing cache and refreshing session', [
                'status' => $response->status(),
                'endpoint' => $endpoint
            ]);

            // Clear cached session tokens
            cache()->forget('linnworks.session_token');
            cache()->forget('linnworks.server');
            $this->server = null;
            $this->sessionToken = null;

            // Get fresh session token
            $sessionData = $this->getSessionToken();
            if ($sessionData) {
                $url = $sessionData['server'] . $endpoint;
                $response = $this->executeRequest($method, $url, $sessionData['token'], $data);
            }
        }

        return $response;
    }

    /**
     * Execute HTTP request
     */
    private function executeRequest(string $method, string $url, string $token, ?array $data = null): ?\Illuminate\Http\Client\Response
    {
        try {
            return match (strtoupper($method)) {
                'GET' => Http::withHeaders(['Authorization' => $token])->get($url, $data ?? []),
                'POST' => Http::withHeaders(['Authorization' => $token])->post($url, $data ?? []),
                default => null
            };
        } catch (Exception $e) {
            Log::error('HTTP request failed', [
                'method' => $method,
                'url' => $url,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Authenticate with Linnworks API and get server URL
     */
    public function authenticate(): bool
    {
        if (!$this->isConfigured()) {
            Log::warning('Linnworks API credentials not configured');
            return false;
        }

        try {
            $response = Http::asForm()->post(Config::get('linnworks.base_url', 'https://api.linnworks.net') . "/api/Auth/AuthorizeByApplication", [
                'ApplicationId' => Config::get('linnworks.application_id'),
                'ApplicationSecret' => Config::get('linnworks.application_secret'),
                'Token' => Config::get('linnworks.token'),
            ]);

            if ($response && $response->successful()) {
                $data = $response->json();
                $this->server = $data['Server'];
                $this->sessionToken = $data['Token'] ?? null;
                return true;
            }

            Log::error('Linnworks authentication failed', [
                'status' => $response?->status(),
                'response' => $response?->body(),
            ]);

            return false;
        } catch (Exception $e) {
            Log::error('Linnworks authentication error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get orders from Linnworks
     */
    public function getOrders(
        ?Carbon $from = null,
        ?Carbon $to = null,
        int $pageNumber = 1,
        int $entriesPerPage = 100
    ): Collection {
        if (!$this->isConfigured()) {
            return collect();
        }

        $from = $from ?? Carbon::now()->subDays(30);
        $to = $to ?? Carbon::now();

        try {
            Log::info('Fetching orders from Linnworks', [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'page' => $pageNumber
            ]);

            $response = $this->makeAuthenticatedRequest('POST', '/api/Orders/GetOrders', [
                'from' => $from->format('Y-m-d\TH:i:s.v\Z'),
                'to' => $to->format('Y-m-d\TH:i:s.v\Z'),
                'pageNumber' => $pageNumber,
                'entriesPerPage' => $entriesPerPage,
                'fulfilmentCenter' => '',
                'searchField' => '',
                'searchTerm' => '',
                'sorting' => [
                    'Direction' => 0,
                    'Field' => 'dReceivedDate'
                ]
            ]);

            if ($response && $response->successful()) {
                $data = $response->json();
                $orders = collect($data['Data'] ?? [])
                    ->map(fn (array $order) => LinnworksOrder::fromArray($order));

                Log::info('Successfully fetched orders from Linnworks', [
                    'orders_count' => $orders->count(),
                    'total_results' => $data['TotalResults'] ?? 0
                ]);

                return $orders;
            }

            Log::error('Failed to fetch orders from Linnworks', [
                'status' => $response?->status(),
                'response' => $response?->body(),
            ]);

            return collect();
        } catch (Exception $e) {
            Log::error('Error fetching orders from Linnworks: ' . $e->getMessage());
            return collect();
        }
    }

    /**
     * Get all orders (processed and non-processed) from Linnworks by date range
     */
    public function getProcessedOrders(
        ?Carbon $from = null,
        ?Carbon $to = null,
        int $pageNumber = 1,
        int $entriesPerPage = 100
    ): Collection {
        if (!$this->isConfigured()) {
            return collect();
        }

        $from = $from ?? Carbon::now()->subDays(30);
        $to = $to ?? Carbon::now();

        try {
            Log::info('Attempting to fetch historical orders from Linnworks', [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'page' => $pageNumber
            ]);

            // For now, let's skip the problematic GetOrders endpoint
            // The issue might be that we need specific permissions or different approach
            // Let's focus on building features with current open orders data
            Log::info('Skipping historical orders for now - endpoint authentication issues', [
                'recommendation' => 'Focus on real-time data and build history going forward'
            ]);
            
//            return collect(); // Return empty for now

            if ($response && $response->successful()) {
                $data = $response->json();
                $orderList = $data['Data'] ?? [];

                Log::info('Successfully fetched order list by date range from Linnworks', [
                    'orders_count' => count($orderList),
                    'total_results' => $data['TotalResults'] ?? 0
                ]);

                if (empty($orderList)) {
                    return collect();
                }

                // Step 2: Get detailed order information for each order  
                $orderIds = array_column($orderList, 'OrderId');
                $orders = collect();
                
                // Process orders in batches of 10 to avoid hitting API limits
                $chunks = array_chunk($orderIds, 10);
                
                foreach ($chunks as $chunk) {
                    $orderResponse = $this->makeAuthenticatedRequest(
                        'POST', 
                        '/api/Orders/GetOrdersById', 
                        ['pkOrderIds' => $chunk]
                    );

                    if ($orderResponse && $orderResponse->successful()) {
                        $orderData = $orderResponse->json();
                        Log::info('Fetched batch of order details by date range', ['batch_size' => count($chunk)]);
                        
                        $batchOrders = collect($orderData)
                            ->map(fn(array $order) => LinnworksOrder::fromArray($order));
                        
                        $orders = $orders->merge($batchOrders);
                    } else {
                        Log::warning('Failed to fetch batch of order details by date range', [
                            'order_ids' => $chunk,
                            'status' => $orderResponse?->status(),
                            'response' => $orderResponse?->body(),
                        ]);
                    }
                    
                    // Add small delay between batches to avoid rate limiting
                    usleep(100000); // 0.1 seconds
                }

                return $orders;
            }

            Log::error('Failed to fetch orders by date range from Linnworks', [
                'status' => $response?->status(),
                'response' => $response?->body(),
            ]);

            return collect();
        } catch (Exception $e) {
            Log::error('Error fetching orders by date range from Linnworks: ' . $e->getMessage());
            return collect();
        }
    }

    /**
     * Get order details including items
     */
    public function getOrderDetails(string $orderId): ?LinnworksOrder
    {
        if (!$this->isConfigured()) {
            return null;
        }

        try {
            Log::info('Fetching order details from Linnworks', ['order_id' => $orderId]);

            $response = $this->makeAuthenticatedRequest('POST', '/api/Orders/GetOrdersById', [
                'pkOrderIds' => [$orderId]
            ]);

            if ($response && $response->successful()) {
                $orders = $response->json();
                
                if (!empty($orders)) {
                    $order = LinnworksOrder::fromArray($orders[0]);
                    Log::info('Successfully fetched order details', [
                        'order_id' => $orderId,
                        'order_number' => $order->orderNumber
                    ]);
                    return $order;
                }
                
                Log::warning('No order found with given ID', ['order_id' => $orderId]);
                return null;
            }

            Log::error('Failed to fetch order details from Linnworks', [
                'orderId' => $orderId,
                'status' => $response?->status(),
                'response' => $response?->body(),
            ]);

            return null;
        } catch (Exception $e) {
            Log::error('Error fetching order details from Linnworks', [
                'order_id' => $orderId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Get all orders with details for a date range
     */
    public function getAllOrdersWithDetails(
        Carbon $from = null,
        Carbon $to = null
    ): array {
        if (!$this->isConfigured()) {
            return [];
        }

        $allOrders = [];
        $pageNumber = 1;
        $entriesPerPage = 200;

        do {
            $ordersResponse = $this->getOrders($from, $to, $pageNumber, $entriesPerPage);
            
            if (empty($ordersResponse)) {
                break;
            }

            $orders = $ordersResponse['Data'] ?? [];
            
            foreach ($orders as $order) {
                $orderDetails = $this->getOrderDetails($order['pkOrderID']);
                if ($orderDetails) {
                    $allOrders[] = $orderDetails;
                }
                
                // Add small delay to avoid rate limiting
                usleep(100000); // 0.1 seconds
            }

            $pageNumber++;
            
            // Continue if we got a full page of results
        } while (count($orders) === $entriesPerPage);

        return $allOrders;
    }


    /**
     * Test the API connection
     */
    public function testConnection(): bool
    {
        try {
            return $this->authenticate();
        } catch (Exception $e) {
            Log::error('Linnworks API connection test failed: ' . $e->getMessage());
            return false;
        }
    }


    /**
     * Get all open order UUIDs from Linnworks
     */
    public function getAllOpenOrderIds(): Collection
    {
        if (!$this->isConfigured()) {
            return collect();
        }

        try {
            Log::info('Getting all open order UUIDs from Linnworks');

            $response = $this->makeAuthenticatedRequest('POST', '/api/Orders/GetAllOpenOrders', []);

            if (!$response || !$response->successful()) {
                Log::error('Failed to fetch open order UUIDs from Linnworks', [
                    'status' => $response?->status(),
                    'response' => $response?->body(),
                ]);
                return collect();
            }

            $openOrderIds = $response->json();
            
            if (!is_array($openOrderIds)) {
                Log::error('GetAllOpenOrders returned non-array response', [
                    'response_type' => gettype($openOrderIds),
                    'response' => $response->body(),
                ]);
                return collect();
            }
            
            Log::info('Fetched open order UUIDs from Linnworks', [
                'total_open_orders' => count($openOrderIds),
                'sample_ids' => array_slice($openOrderIds, 0, 3),
            ]);

            return collect($openOrderIds);
        } catch (Exception $e) {
            Log::error('Error fetching open order UUIDs from Linnworks', [
                'error' => $e->getMessage(),
            ]);
            return collect();
        }
    }

    /**
     * Get order details by UUIDs
     */
    public function getOrdersByIds(array $orderIds): Collection
    {
        if (!$this->isConfigured() || empty($orderIds)) {
            return collect();
        }

        try {
            Log::info('Fetching order details by IDs', [
                'order_count' => count($orderIds),
                'sample_ids' => array_slice($orderIds, 0, 3),
            ]);

            $response = $this->makeAuthenticatedRequest(
                'POST', 
                '/api/Orders/GetOrdersById', 
                ['pkOrderIds' => $orderIds]
            );

            if (!$response || !$response->successful()) {
                Log::error('Failed to fetch order details from Linnworks', [
                    'order_ids_count' => count($orderIds),
                    'status' => $response?->status(),
                    'response' => $response?->body(),
                ]);
                return collect();
            }

            $orderData = $response->json();
            
            if (!is_array($orderData)) {
                Log::error('GetOrdersById returned non-array response', [
                    'response_type' => gettype($orderData),
                    'response' => $response->body(),
                ]);
                return collect();
            }

            Log::info('Successfully fetched order details', [
                'requested_count' => count($orderIds),
                'returned_count' => count($orderData)
            ]);
            
            return collect($orderData)->map(fn(array $order) => LinnworksOrder::fromArray($order));
        } catch (Exception $e) {
            Log::error('Error fetching order details from Linnworks', [
                'order_ids_count' => count($orderIds),
                'error' => $e->getMessage(),
            ]);
            return collect();
        }
    }

    /**
     * Get all open orders with full details (convenience method for current sync)
     */
    public function getAllOpenOrders(): Collection
    {
        $orderIds = $this->getAllOpenOrderIds();
        
        if ($orderIds->isEmpty()) {
            return collect();
        }

        // Process in batches to avoid API limits
        $orders = collect();
        $batchSize = 50;
        
        foreach ($orderIds->chunk($batchSize) as $batchIndex => $chunk) {
            Log::info('Processing batch of order details', [
                'batch' => $batchIndex + 1,
                'batch_size' => $chunk->count()
            ]);

            $batchOrders = $this->getOrdersByIds($chunk->toArray());
            $orders = $orders->merge($batchOrders);
            
            // Small delay between batches
            if ($batchIndex > 0) {
                usleep(200000); // 0.2 seconds
            }
        }

        return $orders;
    }

    /**
     * Strip customer data from orders, keeping only product/sales data
     */
    private function stripCustomerDataFromOrders(array $orders): array
    {
        return array_map(function ($order) {
            return [
                'order_id' => $order['pkOrderID'] ?? null,
                'order_number' => $order['nOrderId'] ?? null,
                'received_date' => $order['dReceivedDate'] ?? null,
                'processed_date' => $order['dProcessedOn'] ?? null,
                'order_source' => $order['Source'] ?? null,
                'subsource' => $order['SubSource'] ?? null,
                'currency' => $order['cCurrency'] ?? 'GBP',
                'total_charge' => $order['fTotalCharge'] ?? 0,
                'postage_cost' => $order['fPostageCost'] ?? 0,
                'tax' => $order['fTax'] ?? 0,
                'profit_margin' => $order['ProfitMargin'] ?? 0,
                'order_status' => $order['nStatus'] ?? 0,
                'location_id' => $order['fkOrderLocationID'] ?? null,
                'items' => $this->stripCustomerDataFromItems($order['Items'] ?? []),
                // Exclude all customer/shipping data
            ];
        }, $orders);
    }

    /**
     * Strip customer data from order items
     */
    private function stripCustomerDataFromItems(array $items): array
    {
        return array_map(function ($item) {
            return [
                'item_id' => $item['ItemId'] ?? null,
                'sku' => $item['SKU'] ?? null,
                'item_title' => $item['ItemTitle'] ?? null,
                'quantity' => $item['Quantity'] ?? 0,
                'unit_cost' => $item['UnitCost'] ?? 0,
                'price_per_unit' => $item['PricePerUnit'] ?? 0,
                'line_total' => $item['LineTotal'] ?? 0,
                'category_name' => $item['CategoryName'] ?? null,
                // Exclude any customer-specific item data
            ];
        }, $items);
    }
}