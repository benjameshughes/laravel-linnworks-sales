<?php

namespace App\Providers;

use App\Services\Dashboard\DashboardDataService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register DashboardDataService as singleton
        // All dashboard islands share same instance = 1 query instead of 8
        $this->app->singleton(DashboardDataService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
