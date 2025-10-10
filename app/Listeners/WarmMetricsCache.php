<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\CachePeriodWarmed;
use App\Events\CacheWarmingCompleted;
use App\Events\CacheWarmingStarted;
use App\Events\OrdersSynced;
use App\Jobs\WarmPeriodCacheJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;

/**
 * Warm metrics cache when orders are synced
 *
 * Dispatches individual jobs for each period to avoid memory issues.
 * Jobs are dispatched to queue and processed sequentially by queue worker.
 */
final class WarmMetricsCache implements ShouldQueue
{
    /**
     * The name of the queue the job should be sent to.
     */
    public string $queue = 'low';

    /**
     * The number of seconds before the job should be processed.
     */
    public int $delay = 30; // Wait 30s after sync completes

    /**
     * Handle the event.
     *
     * Dispatches individual WarmPeriodCacheJob for each period/channel combination.
     * This prevents memory issues by processing one period at a time in the queue worker.
     */
    public function handle(OrdersSynced $event): void
    {
        Log::info('Warming metrics cache after orders sync', [
            'orders_processed' => $event->ordersProcessed,
            'sync_type' => $event->syncType,
        ]);

        $periods = config('dashboard.cacheable_periods', ['7', '30', '90']);
        $channels = ['all']; // Can add specific channels later

        // Broadcast that warming has started
        CacheWarmingStarted::dispatch(collect($periods)->map(fn($p) => "{$p}d")->toArray());

        // Dispatch individual jobs for each period/channel combination
        // Jobs are queued and processed sequentially, preventing memory buildup
        $jobs = collect($periods)->flatMap(function (string $period) use ($channels) {
            return collect($channels)->map(function (string $channel) use ($period) {
                return new WarmPeriodCacheJob($period, $channel);
            });
        });

        // Dispatch all jobs to the 'low' priority queue
        Bus::batch($jobs->all())
            ->onQueue('low')
            ->name('warm-metrics-cache')
            ->finally(function () use ($periods) {
                Log::info('Cache warming batch completed');
                CacheWarmingCompleted::dispatch(count($periods));
            })
            ->dispatch();

        Log::info('Cache warming jobs dispatched', [
            'job_count' => $jobs->count(),
        ]);
    }

    /**
     * Handle a job failure.
     */
    public function failed(OrdersSynced $event, \Throwable $exception): void
    {
        Log::error('WarmMetricsCache listener failed to dispatch jobs', [
            'orders_processed' => $event->ordersProcessed,
            'sync_type' => $event->syncType,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}
