<?php

namespace App\Reports;

use Carbon\Carbon;
use App\Reports\Filters\SkuFilter;
use Illuminate\Support\Facades\DB;
use App\Reports\Enums\ExportFormat;
use App\Reports\Enums\ReportCategory;
use Illuminate\Database\Query\Builder;
use App\Reports\Filters\DateRangeFilter;
use App\Reports\Filters\SubsourceFilter;
use App\Reports\Exports\VariationGroupExport;

final class VariationGroupSalesReport extends AbstractReport
{
    public function name(): string
    {
        return 'Variation Group Sales';
    }

    public function description(): string
    {
        return 'Sales analysis by variation groups (parent SKU). Shows orders, units sold, and revenue broken down by source and subsource.';
    }

    public function icon(): string
    {
        return 'chart-bar';
    }

    public function category(): ReportCategory
    {
        return ReportCategory::Sales;
    }

    public function filters(): array
    {
        return [
            new DateRangeFilter(required: true, defaultDays: 30),
            new SkuFilter(multiple: true, required: false),
            new SubsourceFilter(multiple: true, required: false),
        ];
    }

    public function columns(): array
    {
        return [
            'parent_sku' => ['label' => 'SKU', 'type' => 'string'],
            'order_count' => ['label' => 'Orders', 'type' => 'integer'],
            'total_units' => ['label' => 'Units Sold', 'type' => 'integer'],
            'total_revenue' => ['label' => 'Revenue', 'type' => 'currency'],
        ];
    }

    protected function buildQuery(array $filters): Builder
    {
        $dateStart = Carbon::parse($filters['date_range']['start'])->startOfDay();
        $dateEnd = Carbon::parse($filters['date_range']['end'])->endOfDay();

        $query = DB::table('order_items as oi')
            ->join('orders as o', 'o.id', '=', 'oi.order_id')
            ->whereNotNull('oi.parent_sku')
            ->whereBetween('o.received_at', [$dateStart, $dateEnd])
            ->where('o.status', '!=', 'cancelled');

        if (! empty($filters['skus'])) {
            $query->whereIn('oi.parent_sku', $filters['skus']);
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

        $query->select([
            'oi.parent_sku',
            DB::raw('COUNT(DISTINCT oi.order_id) as order_count'),
            DB::raw('SUM(oi.quantity) as total_units'),
            DB::raw('SUM(oi.quantity * oi.price_per_unit) as total_revenue'),
        ])
            ->groupBy('oi.parent_sku')
            ->orderByRaw('total_revenue DESC');

        return $query;
    }

    /**
     * This report ships a bespoke sheet layout - one column group per
     * subsource - so it bypasses the generic ReportExport.
     */
    public function export(array $filters, ExportFormat $format = ExportFormat::XLSX): string
    {
        $this->validateFilters($filters);

        return new VariationGroupExport()->generate($filters, $this->buildQuery($filters)->get());
    }

    public function exportToFile(array $filters, ExportFormat $format = ExportFormat::XLSX): string
    {
        $path = tempnam(sys_get_temp_dir(), 'report_');
        file_put_contents($path, $this->export($filters, $format));

        return $path;
    }
}
