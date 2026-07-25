<?php

declare(strict_types=1);

namespace App\Services\Metrics;

use Carbon\CarbonImmutable;
use App\Enums\ComparisonMode;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Database\Query\Builder;
use App\DataTransferObjects\ChannelPerformance;

/**
 * Period-over-period channel comparison built entirely from database aggregation.
 *
 * Compares an anchor month against its baseline - the previous month (MoM) or
 * the same month a year earlier (YoY). Every figure is grouped at the database
 * level so the memory cost stays flat regardless of order volume.
 *
 * DIRECT is excluded to stay consistent with ChunkedMetricsCalculator, so
 * totals here reconcile with the main dashboard.
 */
final readonly class ChannelComparisonQuery
{
    private const EXCLUDED_SOURCE = 'DIRECT';

    private const MONTHS_CACHE_KEY = 'analytics:comparable_months';

    private const MONTHS_CACHE_MINUTES = 60;

    public function __construct(
        private ComparisonMode $mode,
        private CarbonImmutable $anchor,
        private bool $includeSubsources = false,
        private string $status = 'all',
        private string $source = 'all',
    ) {}

    /**
     * @return array{
     *     channels: Collection<int, ChannelPerformance>,
     *     trend: array{labels: array<int, string>, current: array<int, float>, baseline: array<int, float>},
     *     current_label: string,
     *     baseline_label: string
     * }
     */
    public function compare(): array
    {
        $current = $this->aggregate($this->currentStart(), $this->currentEnd());
        $baseline = $this->aggregate($this->baselineStart(), $this->baselineEnd());

        return [
            'channels' => $this->mergePeriods($current, $baseline),
            'trend' => $this->trend(),
            'current_label' => $this->currentStart()->format('F Y'),
            'baseline_label' => $this->baselineStart()->format('F Y'),
        ];
    }

    public function currentStart(): CarbonImmutable
    {
        return $this->anchor->startOfMonth();
    }

    public function currentEnd(): CarbonImmutable
    {
        return $this->anchor->endOfMonth();
    }

    public function baselineStart(): CarbonImmutable
    {
        return $this->mode->baseline($this->anchor)->startOfMonth();
    }

    public function baselineEnd(): CarbonImmutable
    {
        return $this->mode->baseline($this->anchor)->endOfMonth();
    }

    /**
     * Months that actually contain orders, newest first, as Y-m => "F Y".
     *
     * @return Collection<string, string>
     */
    public static function availableMonths(): Collection
    {
        return Cache::remember(
            self::MONTHS_CACHE_KEY,
            now()->addMinutes(self::MONTHS_CACHE_MINUTES),
            fn (): Collection => DB::table('orders')
                ->where('source', '!=', self::EXCLUDED_SOURCE)
                ->whereNotNull('received_at')
                ->selectRaw('SUBSTR(received_at, 1, 7) as month')
                ->groupBy('month')
                ->orderByDesc('month')
                ->pluck('month')
                ->mapWithKeys(fn (string $month): array => [
                    $month => CarbonImmutable::createFromFormat('Y-m-d', $month.'-01')->format('F Y'),
                ])
        );
    }

    /**
     * The most recent month holding data - the only sensible default anchor
     * when the sync has fallen behind today's date.
     */
    public static function latestMonth(): CarbonImmutable
    {
        $month = self::availableMonths()->keys()->first();

        return $month
            ? CarbonImmutable::createFromFormat('Y-m-d', $month.'-01')->startOfMonth()
            : CarbonImmutable::now()->startOfMonth();
    }

    /**
     * Distinct sources available for filtering.
     *
     * @return Collection<int, string>
     */
    public static function availableSources(): Collection
    {
        return Cache::remember(
            'analytics:comparison_sources',
            now()->addMinutes(self::MONTHS_CACHE_MINUTES),
            fn (): Collection => DB::table('orders')
                ->where('source', '!=', self::EXCLUDED_SOURCE)
                ->whereNotNull('source')
                ->distinct()
                ->orderBy('source')
                ->pluck('source')
        );
    }

    /**
     * Revenue and order aggregates for one period, keyed by channel.
     *
     * @return array<string, array{source: string, subsource: string, orders: int, revenue: float, items: int}>
     */
    private function aggregate(CarbonImmutable $start, CarbonImmutable $end): array
    {
        $items = $this->itemsQuery($start, $end)
            ->selectRaw("orders.source, COALESCE(orders.subsource, '') as subsource, SUM(order_items.quantity) as items")
            ->groupBy('orders.source', 'orders.subsource')
            ->get()
            ->groupBy(fn (object $row): string => $this->key($row->source, (string) $row->subsource))
            ->map(fn (Collection $rows): int => (int) $rows->sum('items'));

        return $this->ordersQuery($start, $end)
            ->selectRaw("source, COALESCE(subsource, '') as subsource, COUNT(*) as orders, SUM(total_charge) as revenue")
            ->groupBy('source', 'subsource')
            ->get()
            ->groupBy(fn (object $row): string => $this->key($row->source, (string) $row->subsource))
            ->map(fn (Collection $rows, string $key): array => [
                'source' => $rows->first()->source,
                'subsource' => $this->includeSubsources ? (string) $rows->first()->subsource : '',
                'orders' => (int) $rows->sum('orders'),
                'revenue' => (float) $rows->sum('revenue'),
                'items' => $items->get($key, 0),
            ])
            ->all();
    }

    /**
     * Fold both periods into one DTO per channel, including channels that
     * exist in only one of the two periods.
     *
     * @param  array<string, array{source: string, subsource: string, orders: int, revenue: float, items: int}>  $current
     * @param  array<string, array{source: string, subsource: string, orders: int, revenue: float, items: int}>  $baseline
     * @return Collection<int, ChannelPerformance>
     */
    private function mergePeriods(array $current, array $baseline): Collection
    {
        $totalCurrentRevenue = array_sum(array_column($current, 'revenue'));

        return collect(array_unique([...array_keys($current), ...array_keys($baseline)]))
            ->map(function (string $key) use ($current, $baseline, $totalCurrentRevenue): ChannelPerformance {
                $now = $current[$key] ?? null;
                $then = $baseline[$key] ?? null;
                $identity = $now ?? $then;

                $subsource = $identity['subsource'] !== '' ? $identity['subsource'] : null;
                $currentRevenue = (float) ($now['revenue'] ?? 0.0);

                return new ChannelPerformance(
                    name: $this->displayName($identity['source'], $subsource),
                    source: $identity['source'],
                    subsource: $subsource,
                    currentRevenue: $currentRevenue,
                    baselineRevenue: (float) ($then['revenue'] ?? 0.0),
                    currentOrders: (int) ($now['orders'] ?? 0),
                    baselineOrders: (int) ($then['orders'] ?? 0),
                    currentItems: (int) ($now['items'] ?? 0),
                    baselineItems: (int) ($then['items'] ?? 0),
                    revenueShare: $totalCurrentRevenue > 0 ? ($currentRevenue / $totalCurrentRevenue) * 100 : 0.0,
                );
            })
            ->sortByDesc(fn (ChannelPerformance $channel): float => $channel->currentRevenue)
            ->values();
    }

    /**
     * Daily revenue for both periods, overlaid by day of month so the two
     * lines sit on a shared axis.
     *
     * @return array{labels: array<int, string>, current: array<int, float>, baseline: array<int, float>}
     */
    private function trend(): array
    {
        $current = $this->dailyRevenue($this->currentStart(), $this->currentEnd());
        $baseline = $this->dailyRevenue($this->baselineStart(), $this->baselineEnd());

        $currentDays = $this->currentEnd()->day;
        $baselineDays = $this->baselineEnd()->day;

        $axis = collect(range(1, max($currentDays, $baselineDays)));

        return [
            'labels' => $axis->map(fn (int $day): string => (string) $day)->all(),
            'current' => $axis->map(
                fn (int $day): float => $day <= $currentDays ? ($current[$day] ?? 0.0) : 0.0
            )->all(),
            'baseline' => $axis->map(
                fn (int $day): float => $day <= $baselineDays ? ($baseline[$day] ?? 0.0) : 0.0
            )->all(),
        ];
    }

    /**
     * @return array<int, float>
     */
    private function dailyRevenue(CarbonImmutable $start, CarbonImmutable $end): array
    {
        return $this->ordersQuery($start, $end)
            ->selectRaw('SUBSTR(received_at, 9, 2) as day, SUM(total_charge) as revenue')
            ->groupByRaw('SUBSTR(received_at, 9, 2)')
            ->pluck('revenue', 'day')
            ->mapWithKeys(fn ($revenue, $day): array => [(int) $day => (float) $revenue])
            ->all();
    }

    private function ordersQuery(CarbonImmutable $start, CarbonImmutable $end): Builder
    {
        $query = DB::table('orders')
            ->whereBetween('received_at', [$start, $end])
            ->where('source', '!=', self::EXCLUDED_SOURCE)
            ->when($this->source !== 'all', fn (Builder $q): Builder => $q->where('source', $this->source));

        return $this->applyStatusFilter($query, 'orders');
    }

    private function itemsQuery(CarbonImmutable $start, CarbonImmutable $end): Builder
    {
        $query = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereBetween('orders.received_at', [$start, $end])
            ->where('orders.source', '!=', self::EXCLUDED_SOURCE)
            ->when($this->source !== 'all', fn (Builder $q): Builder => $q->where('orders.source', $this->source));

        return $this->applyStatusFilter($query, 'orders');
    }

    /**
     * Mirrors ChunkedMetricsCalculator's status semantics so both pages agree.
     */
    private function applyStatusFilter(Builder $query, string $table): Builder
    {
        return $query->when($this->status !== 'all', function (Builder $q) use ($table): void {
            match ($this->status) {
                'open_paid' => $q->where("{$table}.is_paid", true),
                'open' => $q->where("{$table}.status", 0)->where("{$table}.is_paid", true),
                'processed' => $q->where("{$table}.status", 1)->where("{$table}.is_paid", true),
                default => null,
            };
        });
    }

    private function key(string $source, string $subsource): string
    {
        return $this->includeSubsources ? "{$source}|{$subsource}" : $source;
    }

    private function displayName(string $source, ?string $subsource): string
    {
        if (! $this->includeSubsources || $subsource === null) {
            return $source;
        }

        return "{$subsource} ({$source})";
    }
}
