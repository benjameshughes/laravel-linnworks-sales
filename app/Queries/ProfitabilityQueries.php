<?php

declare(strict_types=1);

namespace App\Queries;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class ProfitabilityQueries
{
    public function productProfitability(string $sku, Carbon $start, Carbon $end): ?object
    {
        return $this->baseQuery($start, $end)
            ->where('oi.sku', $sku)
            ->select([
                'oi.sku',
                DB::raw('COALESCE(p.title, oi.item_title, "Unknown Product") as title'),
                DB::raw('SUM(oi.quantity) as total_quantity'),
                DB::raw('COUNT(DISTINCT oi.order_id) as order_count'),
                ...$this->profitSelectColumns(),
            ])
            ->groupBy('oi.sku', 'p.title', 'oi.item_title')
            ->first();
    }

    public function bulkProfitabilityData(array $skus, ?int $period = null): Collection
    {
        if (empty($skus)) {
            return collect();
        }

        $query = DB::table('order_items as oi')
            ->join('orders as o', 'oi.order_id', '=', 'o.id')
            ->leftJoin('products as p', 'oi.sku', '=', 'p.sku')
            ->whereIn('oi.sku', $skus);

        if ($period !== null) {
            $query->whereBetween('o.received_at', [now()->subDays($period), now()]);
        }

        $results = $query->select([
            'oi.sku',
            DB::raw('SUM(oi.quantity) as total_quantity'),
            DB::raw('COUNT(DISTINCT oi.order_id) as order_count'),
            ...$this->profitSelectColumns(),
        ])
            ->groupBy('oi.sku')
            ->get()
            ->keyBy('sku');

        return collect($skus)->mapWithKeys(fn (string $sku) => [
            $sku => $results->get($sku, (object) [
                'total_quantity' => 0,
                'order_count' => 0,
                'revenue' => 0,
                'cogs' => 0,
                'shipping_cost' => 0,
                'channel_fees' => 0,
                'total_cost' => 0,
                'profit' => 0,
            ]),
        ]);
    }

    public function channelProfitImpact(Carbon $start, Carbon $end): Collection
    {
        return $this->baseQuery($start, $end)
            ->select([
                'o.source',
                DB::raw('COUNT(DISTINCT o.id) as order_count'),
                DB::raw('SUM(oi.quantity) as total_quantity'),
                ...$this->profitSelectColumns(),
            ])
            ->groupBy('o.source')
            ->orderByDesc('revenue')
            ->get();
    }

    public function unprofitableProducts(Carbon $start, Carbon $end, int $limit = 50): Collection
    {
        return $this->baseQuery($start, $end)
            ->select([
                'oi.sku',
                DB::raw('COALESCE(p.title, oi.item_title, "Unknown Product") as title'),
                DB::raw('SUM(oi.quantity) as total_quantity'),
                DB::raw('COUNT(DISTINCT oi.order_id) as order_count'),
                ...$this->profitSelectColumns(),
            ])
            ->groupBy('oi.sku', 'p.title', 'oi.item_title')
            ->havingRaw('profit < 0')
            ->orderBy('profit')
            ->limit($limit)
            ->get();
    }

    private function baseQuery(Carbon $start, Carbon $end): \Illuminate\Database\Query\Builder
    {
        return DB::table('order_items as oi')
            ->join('orders as o', 'oi.order_id', '=', 'o.id')
            ->leftJoin('products as p', 'oi.sku', '=', 'p.sku')
            ->whereBetween('o.received_at', [$start, $end]);
    }

    private function profitSelectColumns(): array
    {
        $feeCase = $this->buildFeeCase();

        return [
            DB::raw('SUM(CASE WHEN oi.line_total > 0 THEN oi.line_total ELSE oi.quantity * oi.price_per_unit END) as revenue'),
            DB::raw('SUM(COALESCE(NULLIF(oi.unit_cost, 0), p.purchase_price, 0) * oi.quantity) as cogs'),
            DB::raw('SUM(COALESCE(NULLIF(oi.shipping_cost, 0), p.shipping_cost, 0)) as shipping_cost'),
            DB::raw("SUM((CASE WHEN oi.line_total > 0 THEN oi.line_total ELSE oi.quantity * oi.price_per_unit END) * {$feeCase}) as channel_fees"),
            DB::raw("SUM(
                (COALESCE(NULLIF(oi.unit_cost, 0), p.purchase_price, 0) * oi.quantity)
                + COALESCE(NULLIF(oi.shipping_cost, 0), p.shipping_cost, 0)
                + ((CASE WHEN oi.line_total > 0 THEN oi.line_total ELSE oi.quantity * oi.price_per_unit END) * {$feeCase})
            ) as total_cost"),
            DB::raw("SUM(
                (CASE WHEN oi.line_total > 0 THEN oi.line_total ELSE oi.quantity * oi.price_per_unit END)
                - (COALESCE(NULLIF(oi.unit_cost, 0), p.purchase_price, 0) * oi.quantity)
                - COALESCE(NULLIF(oi.shipping_cost, 0), p.shipping_cost, 0)
                - ((CASE WHEN oi.line_total > 0 THEN oi.line_total ELSE oi.quantity * oi.price_per_unit END) * {$feeCase})
            ) as profit"),
        ];
    }

    private function buildFeeCase(): string
    {
        $fees = config('channel-fees', []);
        $cases = collect($fees)
            ->filter(fn (float $fee) => $fee > 0)
            ->map(fn (float $fee, string $source) => 'WHEN o.source = '.DB::getPdo()->quote($source).' THEN '.($fee / 100))
            ->values()
            ->implode(' ');

        if (empty($cases)) {
            return '0';
        }

        return "(CASE {$cases} ELSE 0 END)";
    }
}
