<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Product;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use App\Queries\ProductQueries;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Queries\ProfitabilityQueries;
use App\Services\ProductBadgeService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Contracts\Queue\ShouldBeUnique;

final class WarmProductMetricsCacheJob implements ShouldBeUnique, ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const BADGE_PREWARM_LIMIT = 50;

    public int $tries = 3;

    public int $timeout = 600;

    public int $maxExceptions = 3;

    public int $uniqueFor = 300;

    public function __construct(
        public readonly string $period
    ) {}

    public function uniqueId(): string
    {
        return 'warm-product-metrics-'.$this->period;
    }

    public function handle(
        ProductQueries $queries,
        ProfitabilityQueries $profitQueries,
        ProductBadgeService $badgeService
    ): void {
        if ($this->batch()?->cancelled()) {
            return;
        }

        Log::debug('Warming product cache for period', ['period' => $this->period]);

        $startTime = microtime(true);
        DB::connection()->disableQueryLog();

        $periodInt = (int) $this->period;

        $products = $queries->activeProducts(limit: 5000);
        $skus = $products->pluck('sku')->toArray();
        $salesData = $queries->bulkSalesData($skus, $periodInt);
        $profitData = $profitQueries->bulkProfitabilityData($skus, $periodInt);

        $topProducts = $products->map(function (Product $product) use ($salesData, $profitData) {
            $sales = $salesData->get($product->sku, [
                'total_sold' => 0,
                'total_revenue' => 0,
                'avg_selling_price' => 0,
                'order_count' => 0,
            ]);

            $profit = $profitData->get($product->sku);
            $totalProfit = (float) ($profit->profit ?? 0);
            $revenue = (float) ($profit->revenue ?? $sales['total_revenue']);
            $profitMargin = $revenue > 0 ? ($totalProfit / $revenue) * 100 : 0;

            return [
                'product' => $product,
                'total_sold' => $sales['total_sold'],
                'total_revenue' => $sales['total_revenue'],
                'total_profit' => $totalProfit,
                'profit_margin_percent' => $profitMargin,
                'avg_selling_price' => $sales['avg_selling_price'],
                'purchase_price' => $product->purchase_price,
                'order_count' => $sales['order_count'],
                'cogs' => (float) ($profit->cogs ?? 0),
                'channel_fees' => (float) ($profit->channel_fees ?? 0),
                'shipping_cost' => (float) ($profit->shipping_cost ?? 0),
            ];
        })
            ->sortByDesc('total_revenue')
            ->take(100)
            ->values();

        $totalProducts = $queries->countWithSales($periodInt);
        $categories = $queries->categorySalesData($periodInt)
            ->filter(fn (array $cat) => $cat['total_revenue'] > 0)
            ->take(10)
            ->values();

        $stockAlerts = $queries->lowStock(10)->map(fn (Product $product) => [
            'product' => $product,
            'stock_level' => $product->stock_available,
            'stock_minimum' => $product->stock_minimum,
            'percentage' => $product->stock_minimum > 0
                ? ($product->stock_available / $product->stock_minimum) * 100
                : 0,
        ]);

        $metrics = [
            'total_products' => $totalProducts,
            'total_units_sold' => $topProducts->sum('total_sold'),
            'total_revenue' => $topProducts->sum('total_revenue'),
            'avg_profit_margin' => $topProducts->where('total_revenue', '>', 0)->avg('profit_margin_percent') ?? 0,
            'top_performing_sku' => $topProducts->first()['product']->sku ?? null,
            'categories_count' => $categories->count(),
            'low_stock_count' => $stockAlerts->count(),
        ];

        $cacheKey = "product_metrics_{$this->period}d";
        Cache::forever($cacheKey, [
            'metrics' => $metrics,
            'top_products' => $topProducts->all(),
            'categories' => $categories->all(),
            'stock_alerts' => $stockAlerts->all(),
            'warmed_at' => now()->toISOString(),
        ]);

        $badgeService->getBulkProductBadges(
            $topProducts->take(self::BADGE_PREWARM_LIMIT)->pluck('product'),
            $periodInt
        );

        Log::debug('Product cache warmed successfully', [
            'cache_key' => $cacheKey,
            'products_count' => $topProducts->count(),
            'categories_count' => $categories->count(),
            'duration_seconds' => round(microtime(true) - $startTime, 2),
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('WarmProductMetricsCacheJob failed permanently', [
            'period' => $this->period,
            'error' => $exception->getMessage(),
        ]);
    }
}
