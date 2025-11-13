<?php

namespace App\Reports;

use App\Reports\Enums\ReportCategory;
use App\Reports\Filters\DateRangeFilter;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class TopProductsReport extends AbstractReport
{
    public function name(): string
    {
        return 'Top Products';
    }

    public function description(): string
    {
        return 'Best selling products ranked by revenue. Identify your top performers and revenue drivers.';
    }

    public function icon(): string
    {
        return 'star';
    }

    public function category(): ReportCategory
    {
        return ReportCategory::Products;
    }

    public function filters(): array
    {
        return [
            new DateRangeFilter(required: true, defaultDays: 30),
        ];
    }

    public function columns(): array
    {
        return [
            'rank' => ['label' => 'Rank', 'type' => 'integer'],
            'sku' => ['label' => 'SKU', 'type' => 'string'],
            'title' => ['label' => 'Product', 'type' => 'string'],
            'category' => ['label' => 'Category', 'type' => 'string'],
            'units_sold' => ['label' => 'Units Sold', 'type' => 'integer'],
            'total_revenue' => ['label' => 'Revenue', 'type' => 'currency'],
            'orders' => ['label' => 'Orders', 'type' => 'integer'],
            'revenue_percent' => ['label' => 'Revenue %', 'type' => 'percentage'],
        ];
    }

    protected function buildQuery(array $filters): Builder
    {
        $dateStart = Carbon::parse($filters['date_range']['start'])->startOfDay();
        $dateEnd = Carbon::parse($filters['date_range']['end'])->endOfDay();

        $totalRevenue = DB::table('order_items as oi')
            ->join('orders as o', 'o.id', '=', 'oi.order_id')
            ->whereBetween('o.received_date', [$dateStart, $dateEnd])
            ->where('o.status', '!=', 'cancelled')
            ->sum(DB::raw('oi.quantity * oi.unit_price'));

        $query = DB::table('order_items as oi')
            ->join('orders as o', 'o.id', '=', 'oi.order_id')
            ->whereBetween('o.received_date', [$dateStart, $dateEnd])
            ->where('o.status', '!=', 'cancelled')
            ->select([
                DB::raw('ROW_NUMBER() OVER (ORDER BY SUM(oi.quantity * oi.unit_price) DESC) as rank'),
                'oi.sku',
                DB::raw('MAX(oi.title) as title'),
                DB::raw('MAX(oi.category) as category'),
                DB::raw('SUM(oi.quantity) as units_sold'),
                DB::raw('SUM(oi.quantity * oi.unit_price) as total_revenue'),
                DB::raw('COUNT(DISTINCT o.id) as orders'),
                DB::raw($totalRevenue > 0
                    ? "ROUND((SUM(oi.quantity * oi.unit_price) / {$totalRevenue}) * 100, 2) as revenue_percent"
                    : '0 as revenue_percent'),
            ])
            ->groupBy('oi.sku')
            ->orderByDesc('total_revenue')
            ->limit(100);

        return $query;
    }
}
