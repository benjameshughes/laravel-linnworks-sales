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
        return 'Detailed product performance analysis showing units sold, revenue, current price, and average order value by SKU.';
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
            'current_price' => ['label' => 'Current Price', 'type' => 'currency'],
            'avg_order_value' => ['label' => 'Avg Order Value', 'type' => 'currency'],
        ];
    }

    protected function buildQuery(array $filters): Builder
    {
        $dateStart = Carbon::parse($filters['date_range']['start'])->startOfDay();
        $dateEnd = Carbon::parse($filters['date_range']['end'])->endOfDay();

        $query = DB::table('order_items as oi')
            ->join('orders as o', 'o.id', '=', 'oi.order_id')
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
            DB::raw('SUM(oi.quantity * oi.price_per_unit) as total_revenue'),
            DB::raw('(
                SELECT oi2.price_per_unit
                FROM order_items oi2
                JOIN orders o2 ON o2.id = oi2.order_id
                WHERE oi2.sku = oi.sku
                  AND o2.received_at BETWEEN ? AND ?
                  AND o2.status != ?
                ORDER BY o2.received_at DESC
                LIMIT 1
            ) as current_price'),
            DB::raw('SUM(oi.quantity * oi.price_per_unit) / COUNT(DISTINCT o.id) as avg_order_value'),
        ])
            ->addBinding([$dateStart, $dateEnd, 'cancelled'], 'select')
            ->groupBy('oi.sku')
            ->orderByRaw('total_revenue DESC');

        return $query;
    }
}
