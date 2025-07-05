<?php

use App\Http\Controllers\LinnworksCallbackController;
use App\Livewire\Dashboard\SalesDashboard;
use App\Livewire\Dashboard\ProductAnalytics;
use App\Livewire\Dashboard\ChannelComparison;
use App\Livewire\Settings\Appearance;
use App\Livewire\Settings\LinnworksSettings;
use App\Livewire\Settings\Password;
use App\Livewire\Settings\Profile;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', SalesDashboard::class)->name('dashboard');
    Route::get('products', ProductAnalytics::class)->name('products.analytics');
    Route::get('channels', ChannelComparison::class)->name('channels.comparison');
    Route::get('linnworks/install-url', [LinnworksCallbackController::class, 'getInstallationUrl'])->name('linnworks.install.url');
    
    // Test route to see raw Linnworks data
    Route::get('test-linnworks', function () {
        $apiService = app(\App\Services\LinnworksApiService::class);
        $orders = $apiService->getRecentOpenOrders(auth()->id(), 7);
        return response()->json([
            'order_count' => count($orders),
            'orders' => $orders
        ]);
    })->name('test.linnworks');
});

// Public callback endpoints (no auth middleware)
Route::post('linnworks/callback', [LinnworksCallbackController::class, 'handleCallback'])->name('linnworks.callback');
Route::get('linnworks/callback', [LinnworksCallbackController::class, 'testCallback'])->name('linnworks.callback.test');

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Route::get('settings/profile', Profile::class)->name('settings.profile');
    Route::get('settings/password', Password::class)->name('settings.password');
    Route::get('settings/appearance', Appearance::class)->name('settings.appearance');
    Route::get('settings/linnworks', LinnworksSettings::class)->name('settings.linnworks');
});

require __DIR__.'/auth.php';
