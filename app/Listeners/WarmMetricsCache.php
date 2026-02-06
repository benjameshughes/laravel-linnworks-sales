<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\CacheWarmingCompleted;
use App\Events\CacheWarmingStarted;
use App\Events\OrdersSynced;
use App\Jobs\WarmPeriodCacheJob;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Warm metrics cache when orders are synced
 *
 * Simple flow:
 * 1. OrdersSynced fires → this listener runs
 * 2. Broadcasts CacheWarmingStarted → UI shows "Crunching numbers..."
 * 3. Dispatches batch of WarmPeriodCacheJob jobs
 * 4. When batch completes → broadcasts CacheWarmingCompleted → UI resets
 */
final class WarmMetricsCache
{
    public function handle(OrdersSynced $event): void
    {
        $periods = \App\Enums\Period::cacheable();

        $channelSources = DB::table('orders')
            ->select('source')
            ->where('source', '!=', 'DIRECT')
            ->distinct()
            ->pluck('source')
            ->filter()
            ->sort()
            ->values();

        // Populate the available channels cache for the dashboard filter dropdown
        Cache::forever('analytics:available_channels', $channelSources);

        $channels = $channelSources->prepend('all')->toArray();

        $statuses = ['all', 'open', 'processed', 'open_paid'];

        // Broadcast start - UI shows "Crunching numbers..."
        CacheWarmingStarted::dispatch(
            collect($periods)->map(fn ($p) => "{$p->value}d")->toArray()
        );

        // Build jobs for each period/channel/status combination
        $jobs = collect($periods)->flatMap(function (\App\Enums\Period $period) use ($channels, $statuses) {
            return collect($channels)->flatMap(function (string $channel) use ($period, $statuses) {
                return collect($statuses)->map(function (string $status) use ($period, $channel) {
                    return new WarmPeriodCacheJob($period->value, $channel, $status);
                });
            });
        });

        Log::info('Dispatching cache warming batch', ['jobs' => $jobs->count()]);

        // Dispatch batch - finally() fires when all complete
        Bus::batch($jobs->all())
            ->onQueue('low')
            ->name('warm-metrics-cache')
            ->finally(function () use ($periods) {
                Log::info('Cache warming complete, broadcasting CacheWarmingCompleted');
                CacheWarmingCompleted::dispatch(count($periods));
            })
            ->dispatch();
    }
}
