<?php

namespace App\Reports;

use App\Reports\Enums\ReportCategory;
use App\Reports\Filters\DateRangeFilter;
use App\Reports\Filters\SkuFilter;
use App\Reports\Filters\StatusFilter;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class ProductPerformanceReport extends AbstractReport
{
    public function name(): string
    {
        return 'Product Performance';
    }

    public function description(): string
    {
        return 'Detailed product performance analysis showing units sold, revenue, cost, profit, and margin by SKU.';
    }

    public function icon(): string
    {
        return 'cube';
    }

    public function category(): ReportCategory
    {
        return ReportCategory::Products;
    }

    public function filters(): array
    {
        return [
            new DateRangeFilter(required: true, defaultDays: 30),
            new SkuFilter(multiple: true, required: false),
            new StatusFilter(required: false),
        ];
    }

    public function columns(): array
    {
        return [
            'sku' => ['label' => 'SKU', 'type' => 'string'],
            'title' => ['label' => 'Product Title', 'type' => 'string'],
            'category' => ['label' => 'Category', 'type' => 'string'],
            'orders' => ['label' => 'Orders', 'type' => 'integer'],
            'units_sold' => ['label' => 'Units Sold', 'type' => 'integer'],
            'total_revenue' => ['label' => 'Revenue', 'type' => 'currency'],
            'total_cost' => ['label' => 'Cost', 'type' => 'currency'],
            'total_tax' => ['label' => 'Tax', 'type' => 'currency'],
            'total_profit' => ['label' => 'Profit', 'type' => 'currency'],
            'margin_percent' => ['label' => 'Margin %', 'type' => 'percentage'],
            'current_price' => ['label' => 'Current Price', 'type' => 'currency'],
        ];
    }

    protected function buildQuery(array $filters): Builder
    {
        $dateStart = Carbon::parse($filters['date_range']['start'])->startOfDay();
        $dateEnd = Carbon::parse($filters['date_range']['end'])->endOfDay();

        // Derive latest price per SKU using ROW_NUMBER() — single pass, not per-row
        $rankedPrices = DB::table('order_items as oi2')
            ->join('orders as o2', 'o2.id', '=', 'oi2.order_id')
            ->whereBetween('o2.received_at', [$dateStart, $dateEnd])
            ->where('o2.status', '!=', 'cancelled')
            ->select('oi2.sku', 'oi2.price_per_unit')
            ->selectRaw('ROW_NUMBER() OVER (PARTITION BY oi2.sku ORDER BY o2.received_at DESC) as rn');

        $latestPrices = DB::query()
            ->fromSub($rankedPrices, 'ranked')
            ->where('ranked.rn', 1)
            ->select('ranked.sku', 'ranked.price_per_unit as current_price');

        $query = DB::table('order_items as oi')
            ->join('orders as o', 'o.id', '=', 'oi.order_id')
            ->leftJoinSub($latestPrices, 'latest_price', 'latest_price.sku', '=', 'oi.sku')
            ->whereBetween('o.received_at', [$dateStart, $dateEnd]);

        // Apply status filter if provided
        if (! empty($filters['statuses'])) {
            $query->whereIn('o.status', $filters['statuses']);
        } else {
            // Default: exclude cancelled orders if no status filter specified
            $query->where('o.status', '!=', 'cancelled');
        }

        if (! empty($filters['skus'])) {
            $query->whereIn('oi.sku', $filters['skus']);
        }

        $query->select([
            'oi.sku',
            DB::raw('MAX(oi.item_title) as title'),
            DB::raw('MAX(oi.category_name) as category'),
            DB::raw('COUNT(DISTINCT o.id) as orders'),
            DB::raw('SUM(oi.quantity) as units_sold'),
            DB::raw('SUM(oi.line_total) as total_revenue'),
            DB::raw('SUM(COALESCE(oi.unit_cost, 0) * oi.quantity) as total_cost'),
            DB::raw('SUM(COALESCE(oi.tax, 0)) as total_tax'),
            DB::raw('SUM(oi.line_total) - SUM(COALESCE(oi.unit_cost, 0) * oi.quantity) as total_profit'),
            DB::raw('ROUND(CASE WHEN SUM(oi.line_total) > 0 THEN ((SUM(oi.line_total) - SUM(COALESCE(oi.unit_cost, 0) * oi.quantity)) / SUM(oi.line_total)) * 100 ELSE 0 END, 2) as margin_percent'),
            DB::raw('MAX(latest_price.current_price) as current_price'),
        ])
            ->groupBy('oi.sku')
            ->orderByRaw('total_profit DESC');

        return $query;
    }
}
