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
            'product_rank' => ['label' => 'Rank', 'type' => 'integer'],
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

        $query = DB::table('order_items as oi')
            ->join('orders as o', 'o.id', '=', 'oi.order_id')
            ->whereBetween('o.received_at', [$dateStart, $dateEnd])
            ->where('o.status', '!=', 'cancelled')
            ->select([
                DB::raw('ROW_NUMBER() OVER (ORDER BY SUM(oi.quantity * oi.unit_price) DESC) as product_rank'),
                'oi.sku',
                DB::raw('MAX(oi.title) as title'),
                DB::raw('MAX(oi.category) as category'),
                DB::raw('SUM(oi.quantity) as units_sold'),
                DB::raw('SUM(oi.quantity * oi.unit_price) as total_revenue'),
                DB::raw('COUNT(DISTINCT o.id) as orders'),
                DB::raw('ROUND((SUM(oi.quantity * oi.unit_price) / (
                    SELECT SUM(oi2.quantity * oi2.unit_price)
                    FROM order_items as oi2
                    JOIN orders as o2 ON o2.id = oi2.order_id
                    WHERE o2.received_at BETWEEN ? AND ?
                      AND o2.status != ?
                )) * 100, 2) as revenue_percent'),
            ])
            ->addBinding([$dateStart, $dateEnd, 'cancelled'], 'select')
            ->groupBy('oi.sku')
            ->orderByRaw('total_revenue DESC')
            ->limit(100);

        return $query;
    }
}
