<?php

namespace App\Reports;

use App\Reports\Enums\ReportCategory;
use App\Reports\Filters\DateRangeFilter;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class OrderStatusReport extends AbstractReport
{
    public function name(): string
    {
        return 'Order Status Breakdown';
    }

    public function description(): string
    {
        return 'Order distribution by status. Track processing, cancelled, and completed orders with revenue impact.';
    }

    public function icon(): string
    {
        return 'clipboard-document-list';
    }

    public function category(): ReportCategory
    {
        return ReportCategory::Sales;
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
            'status' => ['label' => 'Status', 'type' => 'string'],
            'order_count' => ['label' => 'Orders', 'type' => 'integer'],
            'total_revenue' => ['label' => 'Revenue', 'type' => 'currency'],
            'avg_order_value' => ['label' => 'Avg Order Value', 'type' => 'currency'],
            'total_items' => ['label' => 'Items', 'type' => 'integer'],
            'percent_of_orders' => ['label' => '% of Orders', 'type' => 'percentage'],
        ];
    }

    protected function buildQuery(array $filters): Builder
    {
        $dateStart = Carbon::parse($filters['date_range']['start'])->startOfDay();
        $dateEnd = Carbon::parse($filters['date_range']['end'])->endOfDay();

        $totalOrders = DB::table('orders')
            ->whereBetween('received_date', [$dateStart, $dateEnd])
            ->count();

        $query = DB::table('orders as o')
            ->whereBetween('o.received_date', [$dateStart, $dateEnd])
            ->select([
                'o.status',
                DB::raw('COUNT(*) as order_count'),
                DB::raw('SUM(o.total_charge) as total_revenue'),
                DB::raw('AVG(o.total_charge) as avg_order_value'),
                DB::raw('SUM(o.num_items) as total_items'),
                DB::raw($totalOrders > 0
                    ? "ROUND((COUNT(*) / {$totalOrders}) * 100, 2) as percent_of_orders"
                    : '0 as percent_of_orders'),
            ])
            ->groupBy('o.status')
            ->orderByDesc('order_count');

        return $query;
    }
}
