<?php

namespace App\Services;

use Exception;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class LinnworksApiService
{
    private string $baseUrl;
    private string $applicationId;
    private string $applicationSecret;
    private string $token;
    private ?string $server = null;

    public function __construct(
        private LinnworksOAuthService $oauthService
    ) {
        $this->baseUrl = config('linnworks.base_url', 'https://api.linnworks.net');
        $this->applicationId = config('linnworks.application_id', '');
        $this->applicationSecret = config('linnworks.application_secret', '');
        $this->token = config('linnworks.token', '');
    }

    /**
     * Check if API credentials are configured (either via config or OAuth)
     */
    public function isConfigured(): bool
    {
        // Check if we have OAuth connection for current user
        if (auth()->check()) {
            return $this->oauthService->isConnected(auth()->id());
        }
        
        // Fallback to config-based credentials
        return !empty($this->applicationId) && 
               !empty($this->applicationSecret) && 
               !empty($this->token);
    }

    /**
     * Get session token (either from OAuth or config-based auth)
     */
    private function getSessionToken(): ?array
    {
        // Try OAuth first if user is authenticated
        if (auth()->check()) {
            $sessionData = $this->oauthService->getValidSessionToken(auth()->id());
            if ($sessionData) {
                return $sessionData;
            }
        }
        
        // Fallback to config-based authentication
        if (!empty($this->applicationId) && !empty($this->applicationSecret) && !empty($this->token)) {
            if (!$this->server) {
                if (!$this->authenticate()) {
                    return null;
                }
            }
            return [
                'token' => $this->server,
                'server' => $this->server,
            ];
        }
        
        return null;
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
            $response = Http::post("{$this->baseUrl}/api/Auth/AuthorizeByApplication", [
                'ApplicationId' => $this->applicationId,
                'ApplicationSecret' => $this->applicationSecret,
                'Token' => $this->token,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $this->server = $data['Server'];
                return true;
            }

            Log::error('Linnworks authentication failed', [
                'status' => $response->status(),
                'response' => $response->body(),
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
        Carbon $from = null,
        Carbon $to = null,
        int $pageNumber = 1,
        int $entriesPerPage = 100
    ): array {
        if (!$this->isConfigured()) {
            return [];
        }

        if (!$this->server) {
            if (!$this->authenticate()) {
                return [];
            }
        }

        $from = $from ?? Carbon::now()->subDays(30);
        $to = $to ?? Carbon::now();

        try {
            $response = Http::post("{$this->server}/api/Orders/GetOrders", [
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

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('Failed to fetch orders from Linnworks', [
                'status' => $response->status(),
                'response' => $response->body(),
            ]);

            return [];
        } catch (Exception $e) {
            Log::error('Error fetching orders from Linnworks: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get order details including items
     */
    public function getOrderDetails(string $orderId): ?array
    {
        if (!$this->isConfigured()) {
            return null;
        }

        if (!$this->server) {
            if (!$this->authenticate()) {
                return null;
            }
        }

        try {
            $response = Http::post("{$this->server}/api/Orders/GetOrdersByOrderId", [
                'pkOrderId' => $orderId
            ]);

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('Failed to fetch order details from Linnworks', [
                'orderId' => $orderId,
                'status' => $response->status(),
                'response' => $response->body(),
            ]);

            return null;
        } catch (Exception $e) {
            Log::error('Error fetching order details from Linnworks: ' . $e->getMessage());
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
        $entriesPerPage = 100;

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
     * Get processed orders (completed sales)
     */
    public function getProcessedOrders(
        Carbon $from = null,
        Carbon $to = null,
        int $pageNumber = 1,
        int $entriesPerPage = 100
    ): array {
        if (!$this->server) {
            if (!$this->authenticate()) {
                throw new Exception('Failed to authenticate with Linnworks API');
            }
        }

        $from = $from ?? Carbon::now()->subDays(30);
        $to = $to ?? Carbon::now();

        try {
            $response = Http::post("{$this->server}/api/Orders/GetOrdersProcessed", [
                'from' => $from->format('Y-m-d\TH:i:s.v\Z'),
                'to' => $to->format('Y-m-d\TH:i:s.v\Z'),
                'pageNumber' => $pageNumber,
                'entriesPerPage' => $entriesPerPage,
            ]);

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('Failed to fetch processed orders from Linnworks', [
                'status' => $response->status(),
                'response' => $response->body(),
            ]);

            return [];
        } catch (Exception $e) {
            Log::error('Error fetching processed orders from Linnworks: ' . $e->getMessage());
            return [];
        }
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
}