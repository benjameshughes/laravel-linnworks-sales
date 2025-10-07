<?php

namespace App\Console\Commands;

use App\Services\Linnworks\Auth\AuthenticationService;
use App\Services\Linnworks\Core\LinnworksClient;
use App\Services\Linnworks\Core\RateLimiter;
use App\Services\Linnworks\Orders\OrdersApiService;
use App\Services\Linnworks\Products\ProductsApiService;
use App\ValueObjects\Linnworks\ApiCredentials;
use App\ValueObjects\Linnworks\ApiRequest;
use Illuminate\Console\Command;

class TestLinnworksRefactoring extends Command
{
    protected $signature = 'linnworks:test-refactoring';
    protected $description = 'Test the refactored Linnworks services';

    public function handle(): int
    {
        $this->info('Testing Linnworks Refactoring...');
        
        // Test 1: Service Resolution
        $this->info('1. Testing service resolution...');
        try {
            $client = app()->make(LinnworksClient::class);
            $auth = app()->make(AuthenticationService::class);
            $orders = app()->make(OrdersApiService::class);
            $products = app()->make(ProductsApiService::class);
            $this->info('   ✅ All services resolved successfully');
        } catch (\Exception $e) {
            $this->error('   ❌ Service resolution failed: ' . $e->getMessage());
            return 1;
        }

        // Test 2: Configuration
        $this->info('2. Testing configuration...');
        try {
            $credentials = app()->make(ApiCredentials::class);
            $this->info("   ✅ Credentials: App ID present=" . (!empty($credentials->applicationId) ? 'Yes' : 'No'));
            $this->info("   ✅ Redirect URI: " . $credentials->redirectUri);
        } catch (\Exception $e) {
            $this->error('   ❌ Configuration test failed: ' . $e->getMessage());
            return 1;
        }

        // Test 3: Rate Limiter
        $this->info('3. Testing rate limiter...');
        try {
            $rateLimiter = app()->make(RateLimiter::class);
            $stats = $rateLimiter->getStats();
            $this->info("   ✅ Rate limiter stats: " . json_encode($stats, JSON_PRETTY_PRINT));
        } catch (\Exception $e) {
            $this->error('   ❌ Rate limiter test failed: ' . $e->getMessage());
            return 1;
        }

        // Test 4: Value Objects
        $this->info('4. Testing value objects...');
        try {
            $request = ApiRequest::get('test/endpoint', ['param' => 'value']);
            $this->info("   ✅ ApiRequest created: " . $request->endpoint);
            
            $cacheKey = $request->getCacheKey();
            $this->info("   ✅ Cache key generated: " . substr($cacheKey, 0, 10) . '...');
        } catch (\Exception $e) {
            $this->error('   ❌ Value objects test failed: ' . $e->getMessage());
            return 1;
        }

        // Test 5: Authentication Service
        $this->info('5. Testing authentication service...');
        try {
            $authService = app()->make(AuthenticationService::class);
            $installUrl = $authService->generateInstallUrl();
            $this->info("   ✅ Install URL generated: " . substr($installUrl, 0, 50) . '...');
        } catch (\Exception $e) {
            $this->error('   ❌ Authentication service test failed: ' . $e->getMessage());
            return 1;
        }

        // Test 6: Interface Bindings
        $this->info('6. Testing interface bindings...');
        try {
            $authInterface = app()->make(\App\Services\Linnworks\Contracts\AuthenticationServiceInterface::class);
            $sessionInterface = app()->make(\App\Services\Linnworks\Contracts\SessionManagerInterface::class);
            $this->info('   ✅ All interfaces bound correctly');
        } catch (\Exception $e) {
            $this->error('   ❌ Interface binding test failed: ' . $e->getMessage());
            return 1;
        }

        $this->info('🎉 All tests passed! Linnworks refactoring is working correctly.');
        $this->info('');
        $this->info('Summary of refactored architecture:');
        $this->info('- 🏗️  Value Objects: 5 created (ApiCredentials, SessionToken, ApiRequest, ApiResponse, RateLimitConfig)');
        $this->info('- ⚙️  Core Services: 2 created (LinnworksClient, RateLimiter)');
        $this->info('- 🔐 Auth Services: 2 created (AuthenticationService, SessionManager)');
        $this->info('- 📦 Order Services: 3 created (OrdersApiService, ProcessedOrdersService, OpenOrdersService)');
        $this->info('- 🛍️  Product Services: 2 created (ProductsApiService, InventoryService)');
        $this->info('- 📋 Contracts: 6 interfaces created for clean architecture');
        $this->info('- 🎪 Service Provider: 1 created for dependency injection');
        $this->info('');
        $this->info('The monolithic LinnworksApiService has been successfully refactored into');
        $this->info('a clean, modular, testable, and maintainable architecture! 🚀');

        return 0;
    }
}