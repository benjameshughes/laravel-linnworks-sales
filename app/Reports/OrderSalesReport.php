<?php

namespace App\Reports;

use App\Reports\Enums\ReportCategory;
use App\Reports\Filters\ChannelFilter;
use App\Reports\Filters\DateRangeFilter;
use App\Reports\Filters\SkuFilter;
use App\Reports\Filters\StatusFilter;
use App\Reports\Filters\SubsourceFilter;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class OrderSalesReport extends AbstractReport
{
    public function name(): string
    {
        return 'Order Sales';
    }

    public function description(): string
    {
        return 'Flat file of all order items showing prices per product, per order, per channel. Perfect for pricing analysis and verification.';
    }

    public function icon(): string
    {
        return 'document-text';
    }

    public function category(): ReportCategory
    {
        return ReportCategory::Sales;
    }

    public function filters(): array
    {
        return [
            new DateRangeFilter(required: true, defaultDays: 30),
            new ChannelFilter(multiple: true, required: false),
            new SubsourceFilter(multiple: true, required: false),
            new SkuFilter(multiple: true, required: false),
            new StatusFilter(required: false),
        ];
    }

    public function columns(): array
    {
        return [
            'order_number' => ['label' => 'Order #', 'type' => 'string'],
            'order_date' => ['label' => 'Date', 'type' => 'datetime'],
            'source' => ['label' => 'Channel', 'type' => 'string'],
            'subsource' => ['label' => 'Subsource', 'type' => 'string'],
            'status' => ['label' => 'Status', 'type' => 'string'],
            'sku' => ['label' => 'SKU', 'type' => 'string'],
            'title' => ['label' => 'Product', 'type' => 'string'],
            'quantity' => ['label' => 'Qty', 'type' => 'integer'],
            'unit_price' => ['label' => 'Unit Price', 'type' => 'currency'],
            'discount' => ['label' => 'Discount', 'type' => 'currency'],
            'tax' => ['label' => 'Tax', 'type' => 'currency'],
            'line_total' => ['label' => 'Line Total', 'type' => 'currency'],
        ];
    }

    protected function buildQuery(array $filters): Builder
    {
        $dateStart = Carbon::parse($filters['date_range']['start'])->startOfDay();
        $dateEnd = Carbon::parse($filters['date_range']['end'])->endOfDay();

        $query = DB::table('order_items as oi')
            ->join('orders as o', 'o.id', '=', 'oi.order_id')
            ->whereBetween('o.received_at', [$dateStart, $dateEnd]);

        if (! empty($filters['statuses'])) {
            $query->whereIn('o.status', $filters['statuses']);
        } else {
            $query->where('o.status', '!=', 'cancelled');
        }

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
            'o.number as order_number',
            'o.received_at as order_date',
            'o.source',
            DB::raw("COALESCE(NULLIF(o.subsource, ''), 'N/A') as subsource"),
            'o.status',
            'oi.sku',
            'oi.item_title as title',
            'oi.quantity',
            'oi.price_per_unit as unit_price',
            DB::raw('COALESCE(oi.discount, 0) as discount'),
            DB::raw('COALESCE(oi.tax, 0) as tax'),
            'oi.line_total',
        ])
            ->orderByDesc('o.received_at')
            ->orderBy('o.number')
            ->orderBy('oi.sku');

        return $query;
    }
}
