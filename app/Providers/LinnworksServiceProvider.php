<?php

namespace App\Providers;

use RuntimeException;
use App\Models\LinnworksConnection;
use Illuminate\Support\ServiceProvider;
use BenHughes\Linnworks\LinnworksClient as PackageClient;

final class LinnworksServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->registerPackageClient();
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
            PackageClient::class,
        ];
    }
}
