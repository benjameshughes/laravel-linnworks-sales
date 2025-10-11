<?php

namespace App\Providers;

use App\Models\User;
use App\Services\Dashboard\DashboardDataService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
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
        $this->configureRateLimiting();
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

        // Cache management gate
        Gate::define('manage-cache', function (User $user) {
            return $user->is_admin;
        });
    }

    /**
     * Configure rate limiting for authentication routes
     */
    private function configureRateLimiting(): void
    {
        // Login: 5 attempts per minute, keyed by email + IP
        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)->by($request->input('email').$request->ip());
        });

        // Register: 3 attempts per hour, keyed by IP only
        RateLimiter::for('register', function (Request $request) {
            return Limit::perHour(3)->by($request->ip());
        });

        // API: 60 requests per minute, keyed by user ID or IP
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });
    }
}
