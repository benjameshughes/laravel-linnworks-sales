<?php

use App\Http\Controllers\Api\Search\AutocompleteController;
use App\Http\Controllers\Api\Search\QuickSearchController;
use App\Http\Controllers\Api\Search\ProductLookupController;
use App\Http\Controllers\Api\Analytics\TrendingController;
use App\Http\Controllers\Api\Analytics\SearchStatsController;
use App\Http\Controllers\Api\Admin\SearchMaintenanceController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Search API endpoints - organized by domain
Route::prefix('search')->name('api.search.')->group(function () {
    Route::get('/autocomplete', AutocompleteController::class)->name('autocomplete');
    Route::get('/quick', QuickSearchController::class)->name('quick');
    Route::get('/product/{sku}', ProductLookupController::class)->name('product');
});

// Analytics API endpoints  
Route::prefix('analytics')->name('api.analytics.')->group(function () {
    Route::get('/trending', TrendingController::class)->name('trending');
    Route::get('/search-stats', SearchStatsController::class)->name('search-stats');
});

// Admin API endpoints (would typically have middleware)
Route::prefix('admin')->name('api.admin.')->group(function () {
    Route::post('/search/reindex', [SearchMaintenanceController::class, 'reindex'])->name('search.reindex');
    Route::delete('/search/cache', [SearchMaintenanceController::class, 'clearCache'])->name('search.clear-cache');
});