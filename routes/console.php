<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule unified orders sync every 15 minutes
Schedule::command('sync:orders')
    ->everyFifteenMinutes()
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

// Check and update processed orders status every 30 minutes (oldest first)
Schedule::command('sync:check-processed')
    ->everyThirtyMinutes()
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/check-processed.log'));

// Monitor queue health every 5 minutes
Schedule::call(function () {
    $highQueueCount = \Illuminate\Support\Facades\DB::table('jobs')->where('queue', 'high')->count();
    $mediumQueueCount = \Illuminate\Support\Facades\DB::table('jobs')->where('queue', 'medium')->count();
    $lowQueueCount = \Illuminate\Support\Facades\DB::table('jobs')->where('queue', 'low')->count();

    $totalPending = $highQueueCount + $mediumQueueCount + $lowQueueCount;

    if ($totalPending > 500) {
        \Illuminate\Support\Facades\Log::warning('Queue backlog detected', [
            'total_pending' => $totalPending,
            'high' => $highQueueCount,
            'medium' => $mediumQueueCount,
            'low' => $lowQueueCount,
        ]);
    }

    if ($highQueueCount > 100) {
        \Illuminate\Support\Facades\Log::warning('High priority queue backlog', [
            'count' => $highQueueCount,
            'recommendation' => 'Consider adding more high queue workers',
        ]);
    }
})->everyFiveMinutes()->name('monitor-queue-health');
