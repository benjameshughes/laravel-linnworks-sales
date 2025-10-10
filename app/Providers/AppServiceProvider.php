<?php

namespace App\Providers;

use App\Services\Dashboard\DashboardDataService;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

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
        $this->configurePasswordValidation();
    }

    /**
     * Configure default password validation rules
     */
    private function configurePasswordValidation(): void
    {
        Password::defaults(fn () => Password::min(12)
            ->letters()           // Must contain letters
            ->mixedCase()         // Upper AND lowercase required
            ->numbers()           // At least one number
            ->symbols()           // At least one special character
            ->uncompromised()     // Check against pwned passwords database
        );
    }
}
