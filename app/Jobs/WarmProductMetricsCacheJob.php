<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Product;
use App\Services\ProductAnalyticsService;
use App\Services\ProductBadgeService;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Job to warm product metrics cache for a specific period
 *
 * Calculates and caches:
 * - Top selling products
 * - Category analysis
 * - Product summary metrics
 * - Stock alerts
 * - Pre-warms badges for top 50 products
 */
final class WarmProductMetricsCacheJob implements ShouldBeUnique, ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 600;

    public int $maxExceptions = 3;

    /**
     * Unique lock duration in seconds (5 minutes)
     */
    public int $uniqueFor = 300;

    public function __construct(
        public readonly string $period
    ) {}

    /**
     * Unique ID to prevent duplicate jobs for same period
     */
    public function uniqueId(): string
    {
        return 'warm-product-metrics-'.$this->period;
    }

    public function handle(
        ProductAnalyticsService $analyticsService,
        ProductBadgeService $badgeService
    ): void {
        if ($this->batch()?->cancelled()) {
            return;
        }

        Log::debug('Warming product cache for period', ['period' => $this->period]);

        $startTime = microtime(true);
        DB::connection()->disableQueryLog();

        $periodInt = (int) $this->period;

        $metrics = $analyticsService->getMetrics($periodInt);
        $topProducts = $analyticsService->getTopSellingProducts($periodInt, null, null, 100);
        $categories = $analyticsService->getTopCategories($periodInt);
        $stockAlerts = $analyticsService->getStockAlerts();

        $cacheKey = "product_metrics_{$this->period}d";
        Cache::forever($cacheKey, [
            'metrics' => $metrics,
            'top_products' => $topProducts->toArray(),
            'categories' => $categories->toArray(),
            'stock_alerts' => $stockAlerts->toArray(),
            'warmed_at' => now()->toISOString(),
        ]);

        $this->prewarmBadges($topProducts->take(50), $badgeService, $periodInt);

        Log::debug('Product cache warmed successfully', [
            'cache_key' => $cacheKey,
            'products_count' => $topProducts->count(),
            'categories_count' => $categories->count(),
            'duration_seconds' => round(microtime(true) - $startTime, 2),
        ]);
    }

    private function prewarmBadges(
        \Illuminate\Support\Collection $topProducts,
        ProductBadgeService $badgeService,
        int $period
    ): void {
        foreach ($topProducts as $item) {
            $product = $item['product'] ?? null;

            if ($product instanceof Product) {
                $badgeService->getProductBadges($product, $period);
            }
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('WarmProductMetricsCacheJob failed permanently', [
            'period' => $this->period,
            'error' => $exception->getMessage(),
        ]);
    }
}
