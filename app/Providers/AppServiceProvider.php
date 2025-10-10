<?php

namespace App\Providers;

use App\Models\User;
use App\Services\Dashboard\DashboardDataService;
use Illuminate\Support\Facades\Gate;
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
        $this->configureAuthorizationGates();
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

    /**
     * Configure authorization gates
     */
    private function configureAuthorizationGates(): void
    {
        // Security management gate
        Gate::define('manage-security', function (User $user) {
            return $user->is_admin;
        });

        // User management gate
        Gate::define('manage-users', function (User $user) {
            return $user->is_admin;
        });

        // Settings management gate (for non-security settings)
        Gate::define('manage-settings', function (User $user) {
            return $user->is_admin;
        });
    }
}
