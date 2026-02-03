<?php

use App\Http\Controllers\LinnworksCallbackController;
use App\Livewire\Dashboard\ChannelComparison;
use App\Livewire\Dashboard\SalesDashboard;
use App\Livewire\OrderDetail;
use App\Livewire\Orders\OrdersIndex;
use App\Livewire\ProductDetail;
use App\Livewire\Products\ProductEdit;
use App\Livewire\Products\ProductImportExport;
use App\Livewire\Products\ProductsIndex;
use App\Livewire\Reports\ReportComparison;
use App\Livewire\Reports\ReportsIndex;
use App\Livewire\Settings\Appearance;
use App\Livewire\Settings\ImportProgress;
use App\Livewire\Settings\LinnworksSettings;
use App\Livewire\Settings\Password;
use App\Livewire\Settings\Profile;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', SalesDashboard::class)->name('dashboard');
    Route::get('products', ProductsIndex::class)->name('products.analytics');
    Route::get('products/{sku}', ProductDetail::class)->name('products.detail');
    Route::get('products/{sku}/edit', ProductEdit::class)->name('products.edit');
    Route::get('products-import', ProductImportExport::class)->name('products.import-export');
    Route::get('orders', OrdersIndex::class)->name('orders.analytics');
    Route::get('orders/{number}', OrderDetail::class)->name('orders.detail');
    Route::get('channels', ChannelComparison::class)->name('channels.comparison');
    Route::get('reports', ReportsIndex::class)->name('reports');
    Route::get('reports/compare', ReportComparison::class)->name('reports.compare');
    Route::get('linnworks/install-url', [LinnworksCallbackController::class, 'getInstallationUrl'])->name('linnworks.install.url');
    Route::redirect('settings', 'settings/profile');

    Route::get('settings/profile', Profile::class)->name('settings.profile');
    Route::get('settings/password', Password::class)->name('settings.password');
    Route::get('settings/appearance', Appearance::class)->name('settings.appearance');
    Route::get('settings/linnworks', LinnworksSettings::class)->name('settings.linnworks');
    Route::get('settings/import', ImportProgress::class)->name('settings.import');
});

Route::middleware(['auth', 'can:manage-security'])->group(function () {
    Route::get('settings/security', \App\Livewire\Settings\SecuritySettings::class)->name('settings.security');
});

Route::middleware(['auth', 'can:manage-cache'])->group(function () {
    Route::get('settings/cache', \App\Livewire\Settings\CacheManagement::class)->name('settings.cache');
});

require __DIR__.'/auth.php';
