<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Events\CachePeriodWarmed;
use App\Events\CachePeriodWarmingStarted;
use App\Factories\Metrics\Sales\SalesFactory;
use App\Repositories\Metrics\Sales\SalesRepository;
use App\Services\Metrics\ChunkedMetricsCalculator;
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

            // Cache forever (refreshed every 15 minutes after order sync)
            $periodEnum = \App\Enums\Period::tryFrom($this->period);
            $cacheKey = $periodEnum?->cacheKey($this->channel, $this->status) ?? "metrics_{$this->period}d_{$this->channel}_{$this->status}";
            Cache::forever($cacheKey, $cacheData);

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
     * - Small periods (≤180d): Use service + factory approach (fast, clean architecture)
     * - Large periods (365d, 730d): Use database aggregation (memory-efficient)
     *
     * Architecture:
     * - Core business metrics come from SalesMetrics service
     * - Chart.js formatting comes from factory (presentation logic)
     * - Status counts come from factory (status-filtered aggregation)
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

        // For smaller periods, use service for core metrics
        Log::debug('Using service-based calculation for small period', [
            'period' => $this->period,
            'channel' => $this->channel,
            'status' => $this->status,
        ]);

        $service = app(\App\Services\Metrics\Sales\SalesMetrics::class);

        // Get core business metrics from service (RAW data only - Alpine formats for Chart.js)
        $summary = $service->getMetricsSummary($this->period, $this->channel);
        $topChannels = $service->getTopChannels($this->period, $this->channel, 6);
        $topProducts = $service->getTopProducts($this->period, $this->channel, 5);
        $recentOrders = $service->getRecentOrders(15);
        $bestDay = $service->getBestPerformingDay($this->period, $this->channel);

        // Get raw daily breakdown data (NO Chart.js formatting - Alpine will handle)
        $dailyBreakdown = $service->getDailyRevenueData(
            period: $this->period
        );

        // For status counts, use factory
        $repository = app(SalesRepository::class);
        $orders = $repository->getAllOrders(
            period: $this->period,
            source: $this->channel,
            status: $this->status
        );
        $factory = new SalesFactory($orders);

        // Build comprehensive metrics data
        return [
            // Core metrics from service
            'revenue' => $summary['total_revenue'],
            'orders' => $summary['total_orders'],
            'items' => $summary['total_items'],
            'avg_order_value' => $summary['average_order_value'],
            'top_channels' => $topChannels,
            'top_products' => $topProducts,
            'recent_orders' => $recentOrders,
            'best_day' => $bestDay,

            // Status counts from factory
            'processed_orders' => $factory->totalProcessedOrders(),
            'open_orders' => $factory->totalOpenOrders(),

            // Raw daily breakdown data (Alpine.js will format for Chart.js)
            'daily_breakdown' => $dailyBreakdown->toArray(),

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
        $threshold = config('dashboard.chunked_calculation_threshold');

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
