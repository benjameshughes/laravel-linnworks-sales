<?php

namespace App\Providers;

use App\Services\Linnworks\Auth\AuthenticationService;
use App\Services\Linnworks\Auth\SessionManager;
use App\Services\Linnworks\Contracts\AuthenticationServiceInterface;
use App\Services\Linnworks\Contracts\LinnworksServiceInterface;
use App\Services\Linnworks\Contracts\RateLimitedServiceInterface;
use App\Services\Linnworks\Contracts\SessionManagerInterface;
use App\Services\Linnworks\Core\LinnworksClient;
use App\Services\Linnworks\Core\RateLimiter;
use App\Services\Linnworks\Orders\LocationsService;
use App\Services\Linnworks\Orders\OpenOrdersService;
use App\Services\Linnworks\Orders\OrdersApiService;
use App\Services\Linnworks\Orders\ProcessedOrdersService;
use App\Services\Linnworks\Orders\ViewsService;
use App\Services\Linnworks\Parsers\ProcessedOrdersResponseParser;
use App\Services\Linnworks\Products\InventoryService;
use App\Services\Linnworks\Products\ProductsApiService;
use App\ValueObjects\Linnworks\RateLimitConfig;
use Illuminate\Support\ServiceProvider;

class LinnworksServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Register value objects
        $this->app->singleton(RateLimitConfig::class, function () {
            return RateLimitConfig::standard();
        });

        // Register core services
        $this->app->singleton(RateLimiter::class, function ($app) {
            return new RateLimiter($app->make(RateLimitConfig::class));
        });

        $this->app->singleton(LinnworksClient::class, function ($app) {
            return new LinnworksClient(
                rateLimiter: $app->make(RateLimiter::class),
                timeout: config('linnworks.timeout', 30),
                enableCaching: config('linnworks.enable_caching', true),
            );
        });

        // Register authentication services
        $this->app->singleton(AuthenticationService::class, function ($app) {
            return new AuthenticationService(
                client: $app->make(LinnworksClient::class),
            );
        });

        $this->app->singleton(SessionManager::class, function ($app) {
            return new SessionManager(
                authService: $app->make(AuthenticationService::class),
            );
        });

        // Register API services
        $this->app->singleton(OrdersApiService::class, function ($app) {
            return new OrdersApiService(
                client: $app->make(LinnworksClient::class),
                sessionManager: $app->make(SessionManager::class),
            );
        });

        $this->app->singleton(ProcessedOrdersResponseParser::class, function () {
            return new ProcessedOrdersResponseParser;
        });

        $this->app->singleton(ProcessedOrdersService::class, function ($app) {
            return new ProcessedOrdersService(
                client: $app->make(LinnworksClient::class),
                sessionManager: $app->make(SessionManager::class),
                parser: $app->make(ProcessedOrdersResponseParser::class),
            );
        });

        $this->app->singleton(LocationsService::class, function ($app) {
            return new LocationsService(
                client: $app->make(LinnworksClient::class),
                sessionManager: $app->make(SessionManager::class),
            );
        });

        $this->app->singleton(ViewsService::class, function ($app) {
            return new ViewsService(
                client: $app->make(LinnworksClient::class),
                sessionManager: $app->make(SessionManager::class),
            );
        });

        $this->app->singleton(OpenOrdersService::class, function ($app) {
            return new OpenOrdersService(
                client: $app->make(LinnworksClient::class),
                sessionManager: $app->make(SessionManager::class),
                locations: $app->make(LocationsService::class),
                views: $app->make(ViewsService::class),
            );
        });

        $this->app->singleton(ProductsApiService::class, function ($app) {
            return new ProductsApiService(
                client: $app->make(LinnworksClient::class),
                sessionManager: $app->make(SessionManager::class),
            );
        });

        $this->app->singleton(InventoryService::class, function ($app) {
            return new InventoryService(
                client: $app->make(LinnworksClient::class),
                sessionManager: $app->make(SessionManager::class),
            );
        });

        // Bind interfaces
        $this->app->bind(AuthenticationServiceInterface::class, AuthenticationService::class);
        $this->app->bind(SessionManagerInterface::class, SessionManager::class);
        $this->app->bind(LinnworksServiceInterface::class, LinnworksClient::class);
        $this->app->bind(RateLimitedServiceInterface::class, LinnworksClient::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Publish configuration if needed
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../../config/linnworks.php' => config_path('linnworks.php'),
            ], 'linnworks-config');
        }
    }

    /**
     * Get the services provided by the provider.
     */
    public function provides(): array
    {
        return [
            RateLimitConfig::class,
            RateLimiter::class,
            LinnworksClient::class,
            AuthenticationService::class,
            SessionManager::class,
            OrdersApiService::class,
            ProcessedOrdersService::class,
            OpenOrdersService::class,
            LocationsService::class,
            ViewsService::class,
            ProductsApiService::class,
            InventoryService::class,
            AuthenticationServiceInterface::class,
            SessionManagerInterface::class,
            LinnworksServiceInterface::class,
            RateLimitedServiceInterface::class,
        ];
    }
}
