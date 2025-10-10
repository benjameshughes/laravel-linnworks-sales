<?php

declare(strict_types=1);

namespace App\Services\Dashboard;

use App\Models\Order;
use App\Services\Metrics\SalesMetrics;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Request-scoped singleton for dashboard data
 *
 * Loads orders ONCE per request and shares across all dashboard islands.
 * Massive memory savings: 1 query instead of 8.
 */
class DashboardDataService
{
    private ?Collection $orders = null;
    private ?Collection $previousPeriodOrders = null;
    private ?string $currentFilters = null;
    private ?array $cachedMetrics = null;

    /**
     * Get pre-warmed metrics from cache with flexible fallback
     *
     * Uses Cache::flexible() with:
     * - 55 minutes fresh period (data considered fresh)
     * - 5 minutes stale period (serve stale while recalculating)
     *
     * This ensures instant responses (~0.3ms) when cache is warm.
     * Falls back to live calculation only when cache is completely empty.
     */
    public function getCachedMetrics(string $period, string $channel = 'all'): ?array
    {
        // Only support caching for standard periods without search/custom dates
        if (!in_array($period, ['7', '30', '90']) || $channel !== 'all') {
            return null;
        }

        $cacheKey = "metrics_{$period}d_{$channel}";

        return $this->cachedMetrics ??= Cache::flexible(
            key: $cacheKey,
            ttl: [3300, 300], // [fresh: 55min, stale: 5min]
            callback: function () use ($period, $channel) {
                // Fallback: calculate metrics if cache completely empty
                $orders = $this->loadOrders($period, $channel, '', null, null);
                $metrics = new SalesMetrics($orders);

                return [
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
            }
        );
    }

    /**
     * Check if we can use pre-warmed cache for current filters
     *
     * Cache is only available for:
     * - Standard periods: 7, 30, 90 days
     * - "all" channel filter
     * - No search term
     * - No custom date range
     */
    public function canUseCachedMetrics(
        string $period,
        string $channel = 'all',
        string $searchTerm = '',
        ?string $customFrom = null,
        ?string $customTo = null
    ): bool {
        return in_array($period, ['7', '30', '90'])
            && $channel === 'all'
            && $searchTerm === ''
            && $customFrom === null
            && $customTo === null;
    }

    /**
     * Get orders for current filter state
     * Loads from DB only once, then returns cached collection
     */
    public function getOrders(
        string $period,
        string $channel = 'all',
        string $searchTerm = '',
        ?string $customFrom = null,
        ?string $customTo = null
    ): Collection {
        $filters = $this->makeFilterKey($period, $channel, $searchTerm, $customFrom, $customTo);

        // If filters changed, clear cache and reload
        if ($this->currentFilters !== $filters) {
            $this->orders = null;
            $this->previousPeriodOrders = null;
            $this->cachedMetrics = null;
            $this->currentFilters = $filters;
        }

        // Return cached orders or load from DB
        return $this->orders ??= $this->loadOrders($period, $channel, $searchTerm, $customFrom, $customTo);
    }

    /**
     * Get previous period orders for comparison
     * Also cached per filter state
     */
    public function getPreviousPeriodOrders(
        string $period,
        string $channel = 'all',
        ?string $customFrom = null,
        ?string $customTo = null
    ): Collection {
        // Make sure main orders are loaded first (sets currentFilters)
        $this->getOrders($period, $channel, '', $customFrom, $customTo);

        return $this->previousPeriodOrders ??= $this->loadPreviousPeriodOrders($period, $channel, $customFrom, $customTo);
    }

    /**
     * Calculate date range from period string
     */
    public function getDateRange(string $period, ?string $customFrom = null, ?string $customTo = null): Collection
    {
        if ($period === 'custom') {
            return collect([
                'start' => Carbon::parse($customFrom)->startOfDay(),
                'end' => Carbon::parse($customTo)->endOfDay(),
            ]);
        }

        if ($period === 'yesterday') {
            return collect([
                'start' => Carbon::yesterday()->startOfDay(),
                'end' => Carbon::yesterday()->endOfDay(),
            ]);
        }

        $days = (int) $period;
        $now = Carbon::now();

        return collect([
            'start' => $now->copy()->subDays($days)->startOfDay(),
            'end' => $now->endOfDay(),
        ]);
    }

    private function loadOrders(
        string $period,
        string $channel,
        string $searchTerm,
        ?string $customFrom,
        ?string $customTo
    ): Collection {
        // Uncomment to see skeleton loaders (adds 1 second delay)
        // sleep(1);

        $dateRange = $this->getDateRange($period, $customFrom, $customTo);

        return Order::whereBetween('received_date', [
                $dateRange->get('start'),
                $dateRange->get('end')
            ])
            ->where('channel_name', '!=', 'DIRECT')
            ->when($channel !== 'all', fn($query) =>
                $query->where('channel_name', $channel)
            )
            ->when($searchTerm, fn($query) =>
                $query->where(function ($q) use ($searchTerm) {
                    $q->where('order_number', 'like', "%{$searchTerm}%")
                      ->orWhere('channel_name', 'like', "%{$searchTerm}%");
                })
            )
            ->orderByDesc('received_date')
            ->get();
    }

    private function loadPreviousPeriodOrders(
        string $period,
        string $channel,
        ?string $customFrom,
        ?string $customTo
    ): Collection {
        if ($period === 'custom') {
            $days = Carbon::parse($customFrom)->diffInDays(Carbon::parse($customTo)) + 1;
            $start = Carbon::parse($customFrom)->subDays($days)->startOfDay();
            $end = Carbon::parse($customFrom)->subDay()->endOfDay();
        } elseif ($period === 'yesterday') {
            $start = Carbon::now()->subDays(2)->startOfDay();
            $end = Carbon::now()->subDays(2)->endOfDay();
        } else {
            $days = (int) $period;
            $start = Carbon::now()->subDays($days * 2)->startOfDay();
            $end = Carbon::now()->subDays($days)->endOfDay();
        }

        return Order::whereBetween('received_date', [$start, $end])
            ->where('channel_name', '!=', 'DIRECT')
            ->when($channel !== 'all', fn($query) =>
                $query->where('channel_name', $channel)
            )
            ->get();
    }

    private function makeFilterKey(
        string $period,
        string $channel,
        string $searchTerm,
        ?string $customFrom,
        ?string $customTo
    ): string {
        return md5($period . $channel . $searchTerm . $customFrom . $customTo);
    }
}
