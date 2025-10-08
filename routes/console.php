<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule open orders sync every 15 minutes
Schedule::command('sync:open-orders')
    ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/linnworks-sync.log'));

// Schedule a more thorough sync every hour
Schedule::command('sync:open-orders --force')
    ->hourly()
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/linnworks-sync.log'));

// Daily cleanup of old sync logs (keep last 30 days)
Schedule::call(function () {
    \App\Models\SyncLog::where('created_at', '<', now()->subDays(30))->delete();
})->daily()->at('03:00');

// Refresh product analytics cache every 5 minutes
Schedule::command('analytics:refresh-cache')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/analytics-cache.log'));

// Force refresh analytics cache every hour (clears stale data)
Schedule::command('analytics:refresh-cache --force')
    ->hourly()
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/analytics-cache.log'));

// Refresh metrics cache every 15 minutes with concurrent jobs
Schedule::command('metrics:refresh-cache --concurrent')
    ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/metrics-cache.log'));

// Force refresh metrics cache every hour (ensures fresh data)
Schedule::command('metrics:refresh-cache --concurrent')
    ->hourly()
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/metrics-cache.log'));

// Check and update processed orders status every 30 minutes (oldest first)
Schedule::command('sync:check-processed')
    ->everyThirtyMinutes()
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/check-processed.log'));
