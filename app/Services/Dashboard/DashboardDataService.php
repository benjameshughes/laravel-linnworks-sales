<?php

declare(strict_types=1);

namespace App\Services\Dashboard;

use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Support\Collection;

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
