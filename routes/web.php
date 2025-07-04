<?php

use App\Http\Controllers\LinnworksCallbackController;
use App\Livewire\Dashboard\SalesDashboard;
use App\Livewire\Dashboard\ProductAnalytics;
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
    Route::get('linnworks/install-url', [LinnworksCallbackController::class, 'getInstallationUrl'])->name('linnworks.install.url');
});

// Public callback endpoint (no auth middleware)
Route::post('linnworks/callback', [LinnworksCallbackController::class, 'handleCallback'])->name('linnworks.callback');

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Route::get('settings/profile', Profile::class)->name('settings.profile');
    Route::get('settings/password', Password::class)->name('settings.password');
    Route::get('settings/appearance', Appearance::class)->name('settings.appearance');
    Route::get('settings/linnworks', LinnworksSettings::class)->name('settings.linnworks');
});

require __DIR__.'/auth.php';
