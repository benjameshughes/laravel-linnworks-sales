<?php

namespace App\Providers;

use RuntimeException;
use App\Models\LinnworksConnection;
use Illuminate\Support\ServiceProvider;
use App\Services\Linnworks\Core\RateLimiter;
use App\Services\Linnworks\Auth\SessionManager;
use App\ValueObjects\Linnworks\RateLimitConfig;
use App\Services\Linnworks\Core\LinnworksClient;
use App\Services\Linnworks\Auth\AuthenticationService;
use BenHughes\Linnworks\LinnworksClient as PackageClient;
use App\Services\Linnworks\Contracts\ProductSyncServiceInterface;

final class LinnworksServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->registerPackageClient();

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

        // Bind interfaces
        $this->app->bind(ProductSyncServiceInterface::class, ProductSyncService::class);
    }

    /**
     * Point the package client at our stored credentials.
     *
     * The package reads them from config by default. This app keeps them
     * encrypted per connection in the database instead, so the binding is
     * replaced rather than the config populated.
     */
    private function registerPackageClient(): void
    {
        $this->app->singleton(PackageClient::class, function (): PackageClient {
            $connection = LinnworksConnection::query()->active()->orderByDesc('updated_at')->first();

            throw_unless(
                $connection,
                RuntimeException::class,
                'No active Linnworks connection configured.',
            );

            return new PackageClient(
                clientId: (string) $connection->application_id,
                clientSecret: (string) $connection->application_secret,
                appToken: (string) $connection->access_token,
                baseUrl: config('linnworks.base_url'),
                authUrl: config('linnworks.auth_url'),
            );
        });
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
            ProductSyncServiceInterface::class,
        ];
    }
}
