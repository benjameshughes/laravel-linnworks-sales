<?php

namespace App\Reports;

use App\Reports\Enums\ReportCategory;
use App\Reports\Filters\ChannelFilter;
use App\Reports\Filters\DateRangeFilter;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class DailyRevenueReport extends AbstractReport
{
    public function name(): string
    {
        return 'Daily Revenue Trend';
    }

    public function description(): string
    {
        return 'Revenue trends over time by day. Track daily sales performance and identify patterns.';
    }

    public function icon(): string
    {
        return 'chart-line';
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
        ];
    }

    public function columns(): array
    {
        return [
            'date' => ['label' => 'Date', 'type' => 'date'],
            'orders' => ['label' => 'Orders', 'type' => 'integer'],
            'revenue' => ['label' => 'Revenue', 'type' => 'currency'],
            'avg_order_value' => ['label' => 'Avg Order Value', 'type' => 'currency'],
            'items_sold' => ['label' => 'Items Sold', 'type' => 'integer'],
            'cancelled_orders' => ['label' => 'Cancelled', 'type' => 'integer'],
        ];
    }

    protected function buildQuery(array $filters): Builder
    {
        $dateStart = Carbon::parse($filters['date_range']['start'])->startOfDay();
        $dateEnd = Carbon::parse($filters['date_range']['end'])->endOfDay();

        $query = DB::table('orders as o')
            ->whereBetween('o.received_at', [$dateStart, $dateEnd]);

        if (! empty($filters['channels'])) {
            $query->whereIn('o.source', $filters['channels']);
        }

        $query->select([
            DB::raw('DATE(o.received_at) as date'),
            DB::raw('COUNT(*) as orders'),
            DB::raw('SUM(CASE WHEN o.status != "cancelled" THEN o.total_charge ELSE 0 END) as revenue'),
            DB::raw('AVG(CASE WHEN o.status != "cancelled" THEN o.total_charge ELSE 0 END) as avg_order_value'),
            DB::raw('SUM(CASE WHEN o.status != "cancelled" THEN o.num_items ELSE 0 END) as items_sold'),
            DB::raw('SUM(CASE WHEN o.status = "cancelled" THEN 1 ELSE 0 END) as cancelled_orders'),
        ])
            ->groupBy(DB::raw('DATE(o.received_at)'))
            ->orderBy('date');

        return $query;
    }
}
