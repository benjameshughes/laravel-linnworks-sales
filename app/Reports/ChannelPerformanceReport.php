<?php

namespace App\Reports;

use App\Reports\Enums\ReportCategory;
use App\Reports\Filters\ChannelFilter;
use App\Reports\Filters\DateRangeFilter;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class ChannelPerformanceReport extends AbstractReport
{
    public function name(): string
    {
        return 'Channel Performance';
    }

    public function description(): string
    {
        return 'Analyze sales performance across different channels. Compare revenue, order count, and average order value by sales channel.';
    }

    public function icon(): string
    {
        return 'globe-alt';
    }

    public function category(): ReportCategory
    {
        return ReportCategory::Channels;
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
            'source' => ['label' => 'Channel', 'type' => 'string'],
            'subsource' => ['label' => 'Subsource', 'type' => 'string'],
            'orders' => ['label' => 'Orders', 'type' => 'integer'],
            'total_revenue' => ['label' => 'Revenue', 'type' => 'currency'],
            'avg_order_value' => ['label' => 'Avg Order Value', 'type' => 'currency'],
            'total_items' => ['label' => 'Items Sold', 'type' => 'integer'],
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
            'o.source',
            DB::raw("COALESCE(NULLIF(o.subsource, ''), 'Unknown') as subsource"),
            DB::raw('COUNT(*) as orders'),
            DB::raw('SUM(CASE WHEN o.status != "cancelled" THEN o.total_charge ELSE 0 END) as total_revenue'),
            DB::raw('AVG(CASE WHEN o.status != "cancelled" THEN o.total_charge ELSE 0 END) as avg_order_value'),
            DB::raw('SUM(CASE WHEN o.status != "cancelled" THEN o.num_items ELSE 0 END) as total_items'),
            DB::raw('SUM(CASE WHEN o.status = "cancelled" THEN 1 ELSE 0 END) as cancelled_orders'),
        ])
            ->groupBy('o.source', 'o.subsource')
            ->orderByRaw('total_revenue DESC');

        return $query;
    }
}
