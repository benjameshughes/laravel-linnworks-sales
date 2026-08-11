<?php

namespace App\Reports;

use Carbon\Carbon;
use App\Reports\Filters\SkuFilter;
use Illuminate\Support\Facades\DB;
use App\Reports\Enums\ReportCategory;
use App\Reports\Filters\ChannelFilter;
use Illuminate\Database\Query\Builder;
use App\Reports\Filters\DateRangeFilter;
use App\Reports\Filters\SubsourceFilter;

final class ProductProfitabilityReport extends AbstractReport
{
    public function name(): string
    {
        return 'Product Profitability';
    }

    public function description(): string
    {
        return 'True profitability by product and channel, including cost of goods, channel fees, and shipping.';
    }

    public function icon(): string
    {
        return 'banknotes';
    }

    public function category(): ReportCategory
    {
        return ReportCategory::Financial;
    }

    public function filters(): array
    {
        return [
            new DateRangeFilter(required: true, defaultDays: 30),
            new ChannelFilter(multiple: true, required: false),
            new SubsourceFilter(multiple: true, required: false),
            new SkuFilter(multiple: true, required: false),
        ];
    }

    public function columns(): array
    {
        return [
            'sku' => ['label' => 'SKU', 'type' => 'string'],
            'title' => ['label' => 'Product', 'type' => 'string'],
            'source' => ['label' => 'Channel', 'type' => 'channel'],
            'subsource' => ['label' => 'Account', 'type' => 'account'],
            'orders' => ['label' => 'Orders', 'type' => 'integer'],
            'units_sold' => ['label' => 'Units', 'type' => 'integer'],
            'total_revenue' => ['label' => 'Revenue', 'type' => 'currency'],
            'cogs' => ['label' => 'COGS', 'type' => 'currency'],
            'channel_fees' => ['label' => 'Channel Fees', 'type' => 'currency'],
            'shipping_cost' => ['label' => 'Shipping', 'type' => 'currency'],
            'total_cost' => ['label' => 'Total Cost', 'type' => 'currency'],
            'true_profit' => ['label' => 'True Profit', 'type' => 'currency'],
            'true_margin' => ['label' => 'True Margin %', 'type' => 'percentage'],
            'avg_unit_price' => ['label' => 'Avg Price', 'type' => 'currency'],
            'profit_per_unit' => ['label' => 'Profit/Unit', 'type' => 'currency'],
        ];
    }

    protected function buildQuery(array $filters): Builder
    {
        $dateStart = Carbon::parse($filters['date_range']['start'])->startOfDay();
        $dateEnd = Carbon::parse($filters['date_range']['end'])->endOfDay();
        $feeCase = $this->buildFeeCase();

        $query = DB::table('order_items as oi')
            ->join('orders as o', 'o.id', '=', 'oi.order_id')
            ->leftJoin('products as p', 'oi.sku', '=', 'p.sku')
            ->whereBetween('o.received_at', [$dateStart, $dateEnd])
            ->where('o.status', '!=', 'cancelled');

        if (! empty($filters['channels'])) {
            $query->whereIn('o.source', $filters['channels']);
        }

        if (! empty($filters['subsources'])) {
            $query->where(function ($q) use ($filters) {
                foreach ($filters['subsources'] as $subsource) {
                    if ($subsource === 'Unknown') {
                        $q->orWhereNull('o.subsource')
                            ->orWhere('o.subsource', '=', '');
                    } else {
                        $q->orWhere('o.subsource', '=', $subsource);
                    }
                }
            });
        }

        if (! empty($filters['skus'])) {
            $query->whereIn('oi.sku', $filters['skus']);
        }

        $query->select([
            'oi.sku',
            DB::raw('MAX(COALESCE(p.title, oi.item_title)) as title'),
            'o.source',
            DB::raw("COALESCE(NULLIF(o.subsource, ''), 'N/A') as subsource"),
            DB::raw('COUNT(DISTINCT o.id) as orders'),
            DB::raw('SUM(oi.quantity) as units_sold'),
            DB::raw('SUM(CASE WHEN oi.line_total > 0 THEN oi.line_total ELSE oi.quantity * oi.price_per_unit END) as total_revenue'),
            DB::raw('SUM(COALESCE(NULLIF(oi.unit_cost, 0), p.purchase_price, 0) * oi.quantity) as cogs'),
            DB::raw("SUM((CASE WHEN oi.line_total > 0 THEN oi.line_total ELSE oi.quantity * oi.price_per_unit END) * {$feeCase}) as channel_fees"),
            DB::raw('SUM(COALESCE(NULLIF(oi.shipping_cost, 0), p.shipping_cost, 0)) as shipping_cost'),
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
            ) as true_profit"),
            DB::raw("ROUND(CASE WHEN SUM(CASE WHEN oi.line_total > 0 THEN oi.line_total ELSE oi.quantity * oi.price_per_unit END) > 0 THEN (
                (SUM(CASE WHEN oi.line_total > 0 THEN oi.line_total ELSE oi.quantity * oi.price_per_unit END)
                - SUM((COALESCE(NULLIF(oi.unit_cost, 0), p.purchase_price, 0) * oi.quantity)
                    + COALESCE(NULLIF(oi.shipping_cost, 0), p.shipping_cost, 0)
                    + ((CASE WHEN oi.line_total > 0 THEN oi.line_total ELSE oi.quantity * oi.price_per_unit END) * {$feeCase})))
                / SUM(CASE WHEN oi.line_total > 0 THEN oi.line_total ELSE oi.quantity * oi.price_per_unit END) * 100
            ) ELSE 0 END, 2) as true_margin"),
            DB::raw('ROUND(SUM(CASE WHEN oi.line_total > 0 THEN oi.line_total ELSE oi.quantity * oi.price_per_unit END) / NULLIF(SUM(oi.quantity), 0), 2) as avg_unit_price'),
            DB::raw("ROUND((SUM(
                (CASE WHEN oi.line_total > 0 THEN oi.line_total ELSE oi.quantity * oi.price_per_unit END)
                - (COALESCE(NULLIF(oi.unit_cost, 0), p.purchase_price, 0) * oi.quantity)
                - COALESCE(NULLIF(oi.shipping_cost, 0), p.shipping_cost, 0)
                - ((CASE WHEN oi.line_total > 0 THEN oi.line_total ELSE oi.quantity * oi.price_per_unit END) * {$feeCase})
            )) / NULLIF(SUM(oi.quantity), 0), 2) as profit_per_unit"),
        ])
            ->groupBy('oi.sku', 'o.source', 'o.subsource')
            ->orderByRaw('true_profit DESC');

        return $query;
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
