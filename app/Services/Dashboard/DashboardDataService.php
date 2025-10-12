<?php

declare(strict_types=1);

namespace App\Services\Dashboard;

use App\Models\Order;
use App\Services\Metrics\SalesMetrics;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

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
     * Get pre-warmed metrics from cache
     *
     * IMPORTANT: This method ONLY reads from cache, never calculates.
     * If cache is empty, it returns null. The frontend should show a
     * "Cache is warming..." message and never call SalesMetrics directly.
     *
     * Cache is warmed by:
     * - Background jobs (WarmPeriodCacheJob)
     * - Scheduled commands
     * - Manual cache warming button
     */
    public function getCachedMetrics(string $period, string $channel = 'all'): ?array
    {
        $periodEnum = \App\Enums\Period::tryFrom($period);

        // Only support caching for cacheable periods with 'all' channel
        if ($periodEnum === null || !$periodEnum->isCacheable() || $channel !== 'all') {
            return null;
        }

        $cacheKey = $periodEnum->cacheKey($channel);

        // Simply return cached data or null - NEVER calculate
        return $this->cachedMetrics ??= Cache::get($cacheKey);
    }

    /**
     * Check if we can use pre-warmed cache for current filters
     *
     * Cache is only available for:
     * - Configured cacheable periods (from config/dashboard.php)
     * - "all" channel filter
     * - "all" status (shows everything for cache)
     * - No custom date range
     */
    public function canUseCachedMetrics(
        string $period,
        string $channel = 'all',
        string $status = 'all',
        ?string $customFrom = null,
        ?string $customTo = null
    ): bool {
        $periodEnum = \App\Enums\Period::tryFrom($period);

        return $periodEnum?->isCacheable() === true
            && $channel === 'all'
            && $status === 'all'
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
        string $status = 'all',
        ?string $customFrom = null,
        ?string $customTo = null
    ): Collection {
        $filters = $this->makeFilterKey($period, $channel, $status, $customFrom, $customTo);

        // If filters changed, clear cache and reload
        if ($this->currentFilters !== $filters) {
            $this->orders = null;
            $this->previousPeriodOrders = null;
            $this->cachedMetrics = null;
            $this->currentFilters = $filters;
        }

        // Return cached orders or load from DB
        return $this->orders ??= $this->loadOrders($period, $channel, $status, $customFrom, $customTo);
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
        string $status,
        ?string $customFrom,
        ?string $customTo
    ): Collection {
        // Uncomment to see skeleton loaders (adds 1 second delay)
        // sleep(1);

        $dateRange = $this->getDateRange($period, $customFrom, $customTo);

        // Memory optimization: Use DB::table() instead of Eloquent
        // Loads raw stdClass objects instead of hydrated models (~50-70% memory reduction)
        // Database-agnostic query, works with SQLite, MySQL, PostgreSQL
        return DB::table('orders')
            ->select([
                'id',
                'order_number',
                'linnworks_order_id',
                'received_date',
                'channel_name',
                'sub_source',
                'total_charge',
                'total_paid',
                'is_paid',
                'is_open',
                'is_processed',
                'items', // JSON column
            ])
            ->whereBetween('received_date', [
                $dateRange->get('start'),
                $dateRange->get('end')
            ])
            ->where('channel_name', '!=', 'DIRECT')
            ->when($channel !== 'all', fn($query) =>
                $query->where('channel_name', $channel)
            )
            ->when($status !== 'all', function ($query) use ($status) {
                if ($status === 'open_paid') {
                    $query->where('is_paid', (int) true);
                } elseif ($status === 'open') {
                    $query->where('is_open', (int) true)->where('is_paid', (int) true);
                } elseif ($status === 'processed') {
                    $query->where('is_processed', (int) true)->where('is_paid', (int) true);
                }
            })
            ->orderByDesc('received_date')
            ->get()
            ->map(function ($order) {
                // Decode JSON columns from DB::table() (they come back as strings)
                if (is_string($order->items)) {
                    $order->items = json_decode($order->items, true) ?? [];
                }

                // Convert date strings to Carbon instances for blade compatibility
                if (is_string($order->received_date)) {
                    $order->received_date = Carbon::parse($order->received_date);
                }

                return $order;
            });
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

        return DB::table('orders')
            ->select([
                'id',
                'order_number',
                'linnworks_order_id',
                'received_date',
                'channel_name',
                'sub_source',
                'total_charge',
                'total_paid',
                'is_paid',
                'is_open',
                'is_processed',
                'items',
            ])
            ->whereBetween('received_date', [$start, $end])
            ->where('channel_name', '!=', 'DIRECT')
            ->when($channel !== 'all', fn($query) =>
                $query->where('channel_name', $channel)
            )
            ->get()
            ->map(function ($order) {
                // Decode JSON columns from DB::table()
                if (is_string($order->items)) {
                    $order->items = json_decode($order->items, true) ?? [];
                }

                // Convert date strings to Carbon instances for blade compatibility
                if (is_string($order->received_date)) {
                    $order->received_date = Carbon::parse($order->received_date);
                }

                return $order;
            });
    }

    private function makeFilterKey(
        string $period,
        string $channel,
        string $status,
        ?string $customFrom,
        ?string $customTo
    ): string {
        return md5($period . $channel . $status . $customFrom . $customTo);
    }
}
