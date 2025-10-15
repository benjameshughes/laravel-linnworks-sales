<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Events\CachePeriodWarmed;
use App\Events\CachePeriodWarmingStarted;
use App\Services\Dashboard\DashboardDataService;
use App\Services\Metrics\ChunkedMetricsCalculator;
use App\Services\Metrics\SalesMetrics;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Job to warm cache for a specific period and channel
 *
 * Memory optimization:
 * - Processes one period at a time
 * - Clears service instance after use
 * - Uses explicit garbage collection hint
 * - No concurrent execution (via queue serialization)
 */
final class WarmPeriodCacheJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds the job can run before timing out.
     * Set to 0 for no timeout - let it run as long as needed
     */
    public int $timeout = 0;

    /**
     * The maximum number of unhandled exceptions to allow before failing.
     */
    public int $maxExceptions = 3;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public readonly string $period,
        public readonly string $channel = 'all',
        public readonly string $status = 'all'
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Skip if batch was cancelled
        if ($this->batch()?->cancelled()) {
            return;
        }

        // Broadcast that this period is starting to warm
        CachePeriodWarmingStarted::dispatch("{$this->period}d");

        Log::debug('Warming cache for period', [
            'period' => $this->period,
            'channel' => $this->channel,
            'status' => $this->status,
        ]);

        $startTime = microtime(true);
        $peakMemoryBefore = memory_get_peak_usage(true);

        // Performance optimization: Disable query log to reduce memory overhead
        DB::connection()->disableQueryLog();

        try {
            $cacheData = $this->calculateMetrics();

            // Cache for 1 hour (will be refreshed when new orders sync)
            $periodEnum = \App\Enums\Period::tryFrom($this->period);
            $cacheKey = $periodEnum?->cacheKey($this->channel, $this->status) ?? "metrics_{$this->period}d_{$this->channel}_{$this->status}";
            Cache::put($cacheKey, $cacheData, 3600);

            $duration = round(microtime(true) - $startTime, 2);
            $peakMemoryAfter = memory_get_peak_usage(true);
            $memoryUsed = $peakMemoryAfter - $peakMemoryBefore;

            Log::info('Cache warmed successfully', [
                'cache_key' => $cacheKey,
                'orders_count' => $cacheData['orders'],
                'duration_seconds' => $duration,
                'memory_used_mb' => round($memoryUsed / 1024 / 1024, 2),
                'peak_memory_mb' => round($peakMemoryAfter / 1024 / 1024, 2),
            ]);

            // Broadcast that this period was warmed
            CachePeriodWarmed::dispatch(
                "{$this->period}d",
                $cacheData['orders'],
                $cacheData['revenue'],
                $cacheData['items']
            );

            // Explicitly free memory after broadcasting
            unset($cacheData);
        } catch (\Throwable $e) {
            Log::error('Failed to warm cache for period', [
                'period' => $this->period,
                'channel' => $this->channel,
                'status' => $this->status,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Re-throw to trigger job retry
            throw $e;
        } finally {
            // Force garbage collection to free memory immediately
            // This is safe here as we're done with all collections
            if (function_exists('gc_collect_cycles')) {
                gc_collect_cycles();
            }
        }
    }

    /**
     * Calculate all metrics for this period/channel
     *
     * Memory optimization strategy:
     * - Small periods (≤180d): Use in-memory collection-based approach (fast, proven)
     * - Large periods (365d, 730d): Use database aggregation (memory-efficient)
     */
    private function calculateMetrics(): array
    {
        // For large periods, use chunked calculator to avoid OOM
        if ($this->shouldUseChunkedCalculation()) {
            Log::debug('Using chunked calculation for large period', [
                'period' => $this->period,
                'channel' => $this->channel,
                'status' => $this->status,
            ]);

            $calculator = new ChunkedMetricsCalculator($this->period, $this->channel, $this->status);

            return $calculator->calculate();
        }

        // For smaller periods, use existing optimized in-memory approach
        Log::debug('Using in-memory calculation for small period', [
            'period' => $this->period,
            'channel' => $this->channel,
            'status' => $this->status,
        ]);

        $service = app(DashboardDataService::class);
        $orders = $service->getOrders($this->period, $this->channel, $this->status);
        $metrics = new SalesMetrics($orders);

        // Calculate date range
        $startDate = now()->subDays((int) $this->period)->startOfDay()->format('Y-m-d');
        $endDate = now()->endOfDay()->format('Y-m-d');

        // Build comprehensive metrics data
        return [
            'revenue' => $metrics->totalRevenue(),
            'orders' => $metrics->totalOrders(),
            'items' => $metrics->totalItemsSold(),
            'avg_order_value' => $metrics->averageOrderValue(),
            'processed_orders' => $metrics->totalProcessedOrders(),
            'open_orders' => $metrics->totalOpenOrders(),
            'top_channels' => $metrics->topChannels(6),
            'top_products' => $metrics->topProducts(5),
            'chart_line' => $metrics->getLineChartData($this->period),
            'chart_orders' => $metrics->getOrderCountChartData($this->period),
            'chart_doughnut' => $metrics->getDoughnutChartData(),
            'chart_items' => $metrics->getItemsSoldChartData($this->period, $startDate, $endDate),
            'chart_orders_revenue' => $metrics->getOrdersVsRevenueChartData($this->period, $startDate, $endDate),
            'recent_orders' => $metrics->recentOrders(15),
            'best_day' => $metrics->bestPerformingDay($startDate, $endDate),
            'warmed_at' => now()->toISOString(),
        ];
    }

    /**
     * Determine if we should use chunked calculation
     *
     * Compares period days against configured threshold.
     * Periods >= threshold use database aggregation for memory efficiency.
     */
    private function shouldUseChunkedCalculation(): bool
    {
        $periodDays = (int) $this->period;
        $threshold = config('dashboard.chunked_calculation_threshold', 365);

        return $periodDays >= $threshold;
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('WarmPeriodCacheJob failed permanently after retries', [
            'period' => $this->period,
            'channel' => $this->channel,
            'status' => $this->status,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}
