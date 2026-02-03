<?php

namespace App\Reports;

use App\Reports\Enums\ReportCategory;
use App\Reports\Filters\ChannelFilter;
use App\Reports\Filters\DateRangeFilter;
use App\Reports\Filters\SkuFilter;
use App\Reports\Filters\SubsourceFilter;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class ProductProfitabilityReport extends AbstractReport
{
    public function name(): string
    {
        return 'Product Profitability';
    }

    public function description(): string
    {
        return 'Comprehensive profitability analysis by product and channel. Shows revenue, cost, tax, profit, and margin breakdown for each SKU per channel.';
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
            'source' => ['label' => 'Channel', 'type' => 'string'],
            'subsource' => ['label' => 'Subsource', 'type' => 'string'],
            'orders' => ['label' => 'Orders', 'type' => 'integer'],
            'units_sold' => ['label' => 'Units', 'type' => 'integer'],
            'total_revenue' => ['label' => 'Revenue', 'type' => 'currency'],
            'total_cost' => ['label' => 'Cost', 'type' => 'currency'],
            'total_tax' => ['label' => 'Tax', 'type' => 'currency'],
            'avg_tax_rate' => ['label' => 'Avg Tax %', 'type' => 'percentage'],
            'total_profit' => ['label' => 'Profit', 'type' => 'currency'],
            'margin_percent' => ['label' => 'Margin %', 'type' => 'percentage'],
            'avg_unit_price' => ['label' => 'Avg Price', 'type' => 'currency'],
            'avg_unit_cost' => ['label' => 'Avg Cost', 'type' => 'currency'],
            'profit_per_unit' => ['label' => 'Profit/Unit', 'type' => 'currency'],
        ];
    }

    protected function buildQuery(array $filters): Builder
    {
        $dateStart = Carbon::parse($filters['date_range']['start'])->startOfDay();
        $dateEnd = Carbon::parse($filters['date_range']['end'])->endOfDay();

        $query = DB::table('order_items as oi')
            ->join('orders as o', 'o.id', '=', 'oi.order_id')
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
            DB::raw('MAX(oi.item_title) as title'),
            'o.source',
            DB::raw("COALESCE(NULLIF(o.subsource, ''), 'N/A') as subsource"),
            DB::raw('COUNT(DISTINCT o.id) as orders'),
            DB::raw('SUM(oi.quantity) as units_sold'),
            DB::raw('SUM(oi.line_total) as total_revenue'),
            DB::raw('SUM(COALESCE(oi.unit_cost, 0) * oi.quantity) as total_cost'),
            DB::raw('SUM(COALESCE(oi.tax, 0)) as total_tax'),
            DB::raw('ROUND(AVG(COALESCE(oi.tax_rate, 0)), 2) as avg_tax_rate'),
            DB::raw('SUM(oi.line_total) - SUM(COALESCE(oi.unit_cost, 0) * oi.quantity) as total_profit'),
            DB::raw('ROUND(CASE WHEN SUM(oi.line_total) > 0 THEN ((SUM(oi.line_total) - SUM(COALESCE(oi.unit_cost, 0) * oi.quantity)) / SUM(oi.line_total)) * 100 ELSE 0 END, 2) as margin_percent'),
            DB::raw('ROUND(SUM(oi.line_total) / SUM(oi.quantity), 2) as avg_unit_price'),
            DB::raw('ROUND(AVG(COALESCE(oi.unit_cost, 0)), 2) as avg_unit_cost'),
            DB::raw('ROUND((SUM(oi.line_total) - SUM(COALESCE(oi.unit_cost, 0) * oi.quantity)) / SUM(oi.quantity), 2) as profit_per_unit'),
        ])
            ->groupBy('oi.sku', 'o.source', 'o.subsource')
            ->orderByRaw('total_profit DESC');

        return $query;
    }
}
