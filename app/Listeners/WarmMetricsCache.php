<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\CachePeriodWarmed;
use App\Events\CacheWarmingCompleted;
use App\Events\CacheWarmingStarted;
use App\Events\OrdersSynced;
use App\Services\Dashboard\DashboardDataService;
use App\Services\Metrics\SalesMetrics;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Concurrency;
use Illuminate\Support\Facades\Log;

/**
 * Warm metrics cache when orders are synced
 *
 * Uses Concurrency::defer() to warm all period caches in parallel
 * after the HTTP response is sent, ensuring zero impact on sync performance.
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
     */
    public function handle(OrdersSynced $event): void
    {
        Log::info('Warming metrics cache after orders sync', [
            'orders_processed' => $event->ordersProcessed,
            'sync_type' => $event->syncType,
        ]);

        $periods = ['7', '30', '90'];
        $channels = ['all']; // Can add specific channels later

        // Broadcast that warming has started
        CacheWarmingStarted::dispatch(collect($periods)->map(fn($p) => "{$p}d")->toArray());

        // Use Concurrency::run() to warm all caches in parallel
        // This waits for all tasks to complete
        Concurrency::run(
            collect($periods)->flatMap(function (string $period) use ($channels) {
                return collect($channels)->map(function (string $channel) use ($period) {
                    return function () use ($period, $channel) {
                        $this->warmCacheForPeriod($period, $channel);
                    };
                });
            })->toArray()
        );

        Log::info('Cache warming tasks completed');

        // Broadcast completion AFTER all tasks are done
        CacheWarmingCompleted::dispatch(count($periods));
    }

    /**
     * Warm cache for a specific period and channel
     */
    private function warmCacheForPeriod(string $period, string $channel): void
    {
        try {
            $service = app(DashboardDataService::class);
            $orders = $service->getOrders($period, $channel);
            $metrics = new SalesMetrics($orders);

            // Build comprehensive metrics data
            $cacheData = [
                'revenue' => $metrics->totalRevenue(),
                'orders' => $metrics->totalOrders(),
                'items' => $metrics->totalItemsSold(),
                'avg_order_value' => $metrics->averageOrderValue(),
                'processed_orders' => $metrics->totalProcessedOrders(),
                'open_orders' => $metrics->totalOpenOrders(),
                'top_channels' => $metrics->topChannels(6),
                'top_products' => $metrics->topProducts(5),
                'chart_line' => $metrics->getLineChartData($period),
                'chart_orders' => $metrics->getOrderCountChartData($period),
                'chart_doughnut' => $metrics->getDoughnutChartData(),
                'recent_orders' => $metrics->recentOrders(15),
                'warmed_at' => now()->toISOString(),
            ];

            // Cache for 1 hour (will be refreshed when new orders sync)
            $cacheKey = "metrics_{$period}d_{$channel}";
            Cache::put($cacheKey, $cacheData, 3600);

            Log::debug("Cache warmed successfully", [
                'cache_key' => $cacheKey,
                'orders_count' => $orders->count(),
            ]);

            // Broadcast that this period was warmed
            CachePeriodWarmed::dispatch(
                "{$period}d",
                $cacheData['orders'],
                $cacheData['revenue'],
                $cacheData['items']
            );
        } catch (\Throwable $e) {
            Log::error('Failed to warm cache for period', [
                'period' => $period,
                'channel' => $channel,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(OrdersSynced $event, \Throwable $exception): void
    {
        Log::error('WarmMetricsCache listener failed', [
            'orders_processed' => $event->ordersProcessed,
            'sync_type' => $event->syncType,
            'error' => $exception->getMessage(),
        ]);
    }
}
