<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Enums\Period;
use App\Events\OrdersSynced;
use App\Jobs\WarmProductMetricsCacheJob;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;

/**
 * Warm product metrics cache when orders are synced
 *
 * Runs in parallel with WarmMetricsCache but handles product analytics separately.
 * Uses a separate batch so product warming can fail independently of dashboard warming.
 */
final class WarmProductMetricsCache
{
    public function handle(OrdersSynced $event): void
    {
        $periods = Period::cacheable();

        // Build jobs for each cacheable period
        $jobs = collect($periods)->map(
            fn (Period $period) => new WarmProductMetricsCacheJob($period->value)
        );

        Log::info('Dispatching product cache warming batch', ['jobs' => $jobs->count()]);

        // Dispatch batch on low priority queue
        Bus::batch($jobs->all())
            ->onQueue('low')
            ->name('warm-product-cache')
            ->finally(function () use ($periods) {
                Log::info('Product cache warming complete', ['periods' => count($periods)]);
            })
            ->dispatch();
    }
}
