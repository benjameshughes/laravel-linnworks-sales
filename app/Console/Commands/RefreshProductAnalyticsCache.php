<?php

namespace App\Console\Commands;

use App\Services\ProductAnalyticsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class RefreshProductAnalyticsCache extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'analytics:refresh-cache {--force : Force refresh even if cache is still valid}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Refresh product analytics cache to ensure fast dashboard loading';

    /**
     * Execute the console command.
     */
    public function handle(ProductAnalyticsService $analyticsService): int
    {
        $this->info('Starting product analytics cache refresh...');
        $startTime = microtime(true);

        try {
            // Common periods to pre-cache
            $periods = [1, 7, 30, 90];
            $categories = $this->getTopCategories();

            // If force flag is set, clear existing cache first
            if ($this->option('force')) {
                $this->info('Force flag detected, clearing existing cache...');
                $analyticsService->invalidateCache();
            }

            $totalCached = 0;

            // Pre-cache metrics for common periods
            foreach ($periods as $period) {
                $this->info("Caching metrics for {$period} day period...");

                // Cache general metrics
                $analyticsService->getMetrics($period);
                $totalCached++;

                // Cache top products
                $analyticsService->getTopSellingProducts($period, null, null, 50);
                $totalCached++;

                // Cache top categories
                $analyticsService->getTopCategories($period);
                $totalCached++;

                // Cache metrics for top categories
                foreach ($categories as $category) {
                    $analyticsService->getMetrics($period, null, $category);
                    $analyticsService->getTopSellingProducts($period, null, $category, 20);
                    $totalCached += 2;
                }
            }

            // Always refresh stock alerts
            $this->info('Refreshing stock alerts...');
            Cache::forget('stock_alerts');
            $analyticsService->getStockAlerts();
            $totalCached++;

            // Pre-cache top product details and charts
            $this->info('Caching top product details...');
            $topProducts = $analyticsService->getTopSellingProducts(30, null, null, 10);

            foreach ($topProducts as $productData) {
                $sku = $productData['product']->sku;

                // Cache product details
                $analyticsService->getProductDetails($sku);
                $totalCached++;

                // Cache charts for common periods
                foreach ([7, 30, 90] as $period) {
                    $analyticsService->getProductSalesChart($sku, $period);
                    $totalCached++;
                }
            }

            $duration = round(microtime(true) - $startTime, 2);
            $this->info("✓ Successfully cached {$totalCached} analytics datasets in {$duration} seconds");

            // Log the successful refresh
            Log::info('Product analytics cache refreshed', [
                'datasets_cached' => $totalCached,
                'duration_seconds' => $duration,
                'forced' => $this->option('force'),
            ]);

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('Failed to refresh analytics cache: '.$e->getMessage());
            Log::error('Product analytics cache refresh failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return Command::FAILURE;
        }
    }

    /**
     * Get top categories to pre-cache
     */
    private function getTopCategories(): array
    {
        // Get categories from products table
        $categories = \App\Models\Product::query()
            ->where('is_active', true)
            ->whereNotNull('category_name')
            ->distinct()
            ->limit(5)
            ->pluck('category_name')
            ->toArray();

        return $categories;
    }
}
