<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Product;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use App\Queries\ProductQueries;
use App\Events\CachePeriodWarmed;
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
        $start = now()->subDays($periodInt);
        $end = now();
        $feeCase = $profitQueries->feeCase();

        $topProducts = DB::table('order_items as oi')
            ->join('orders as o', 'oi.order_id', '=', 'o.id')
            ->join('products as p', 'oi.sku', '=', 'p.sku')
            ->where('p.is_active', true)
            ->whereBetween('o.received_at', [$start, $end])
            ->select([
                'p.id',
                'p.sku',
                'p.title',
                'p.category_name',
                'p.purchase_price',
                'p.stock_available',
                'p.stock_minimum',
                DB::raw('SUM(oi.quantity) as total_sold'),
                DB::raw('SUM(CASE WHEN oi.line_total > 0 THEN oi.line_total ELSE oi.quantity * oi.price_per_unit END) as total_revenue'),
                DB::raw('AVG(NULLIF(oi.price_per_unit, 0)) as avg_selling_price'),
                DB::raw('COUNT(DISTINCT oi.order_id) as order_count'),
                DB::raw('SUM(COALESCE(NULLIF(oi.unit_cost, 0), p.purchase_price, 0) * oi.quantity) as cogs'),
                DB::raw('SUM(COALESCE(NULLIF(oi.shipping_cost, 0), p.shipping_cost, 0)) as shipping_cost'),
                DB::raw("SUM((CASE WHEN oi.line_total > 0 THEN oi.line_total ELSE oi.quantity * oi.price_per_unit END) * {$feeCase}) as channel_fees"),
                DB::raw("SUM(
                    (CASE WHEN oi.line_total > 0 THEN oi.line_total ELSE oi.quantity * oi.price_per_unit END)
                    - (COALESCE(NULLIF(oi.unit_cost, 0), p.purchase_price, 0) * oi.quantity)
                    - COALESCE(NULLIF(oi.shipping_cost, 0), p.shipping_cost, 0)
                    - ((CASE WHEN oi.line_total > 0 THEN oi.line_total ELSE oi.quantity * oi.price_per_unit END) * {$feeCase})
                ) as total_profit"),
            ])
            ->groupBy('p.id', 'p.sku', 'p.title', 'p.category_name', 'p.purchase_price', 'p.stock_available', 'p.stock_minimum')
            ->orderByDesc('total_revenue')
            ->limit(100)
            ->get()
            ->map(function ($row) {
                $revenue = (float) $row->total_revenue;
                $profit = (float) $row->total_profit;

                return [
                    'sku' => $row->sku,
                    'title' => $row->title,
                    'category_name' => $row->category_name,
                    'purchase_price' => (float) $row->purchase_price,
                    'stock_available' => (int) $row->stock_available,
                    'stock_minimum' => (int) $row->stock_minimum,
                    'total_sold' => (int) $row->total_sold,
                    'total_revenue' => $revenue,
                    'total_profit' => $profit,
                    'profit_margin_percent' => $revenue > 0 ? ($profit / $revenue) * 100 : 0,
                    'avg_selling_price' => (float) $row->avg_selling_price,
                    'order_count' => (int) $row->order_count,
                    'cogs' => (float) $row->cogs,
                    'channel_fees' => (float) $row->channel_fees,
                    'shipping_cost' => (float) $row->shipping_cost,
                ];
            });

        $totalProducts = $queries->countWithSales($periodInt);
        $categories = $queries->categorySalesData($periodInt)
            ->filter(fn (array $cat) => $cat['total_revenue'] > 0)
            ->take(10)
            ->values();

        $stockAlerts = DB::table('products')
            ->where('is_active', true)
            ->whereColumn('stock_available', '<=', 'stock_minimum')
            ->select(['sku', 'title', 'stock_available', 'stock_minimum'])
            ->orderBy('stock_available')
            ->limit(10)
            ->get()
            ->map(fn ($row) => [
                'sku' => $row->sku,
                'title' => $row->title,
                'stock_level' => (int) $row->stock_available,
                'stock_minimum' => (int) $row->stock_minimum,
                'percentage' => $row->stock_minimum > 0
                    ? ((int) $row->stock_available / (int) $row->stock_minimum) * 100
                    : 0,
            ]);

        $metrics = [
            'total_products' => $totalProducts,
            'total_units_sold' => $topProducts->sum('total_sold'),
            'total_revenue' => $topProducts->sum('total_revenue'),
            'avg_profit_margin' => $topProducts->where('total_revenue', '>', 0)->avg('profit_margin_percent') ?? 0,
            'top_performing_sku' => $topProducts->first()['sku'] ?? null,
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

        $skus = $topProducts->take(self::BADGE_PREWARM_LIMIT)->pluck('sku')->toArray();
        $badgeProducts = Product::whereIn('sku', $skus)
            ->select(['id', 'sku', 'title', 'purchase_price', 'created_at'])
            ->get();
        $badgeService->getBulkProductBadges($badgeProducts, $periodInt);

        event(new CachePeriodWarmed(
            period: "{$this->period}d",
            orders: $topProducts->count(),
            revenue: $topProducts->sum('total_revenue'),
            items: $topProducts->sum('total_sold'),
        ));

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
