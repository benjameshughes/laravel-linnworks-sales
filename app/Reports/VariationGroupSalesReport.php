<?php

namespace App\Reports;

use App\Reports\Enums\ReportCategory;
use App\Reports\Filters\DateRangeFilter;
use App\Reports\Filters\SkuFilter;
use App\Reports\Filters\SubsourceFilter;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class VariationGroupSalesReport extends AbstractReport
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

    public function export(array $filters, \App\Reports\Enums\ExportFormat $format = \App\Reports\Enums\ExportFormat::XLSX): string
    {
        $this->validateFilters($filters);

        return $this->generateDetailedXLSX($filters);
    }

    protected function generateDetailedXLSX(array $filters): string
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();

        $rowNum = 1;

        $dateRange = Carbon::parse($filters['date_range']['start'])->format('jS F Y').' to '.Carbon::parse($filters['date_range']['end'])->format('jS F Y');
        $sheet->setCellValue('A1', 'Date Range: '.$dateRange);
        $sheet->getStyle('A1')->getFont()->setBold(true);
        $rowNum++;

        $allSubsources = $this->getAllSubsourcesWithSource($filters);

        if ($allSubsources->isEmpty()) {
            return '';
        }

        // 2 is needed to align everything...o
        $colNum = 2;
        $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colNum).$rowNum, 'Subsource');
        $colNum++;

        $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colNum++).$rowNum, 'TOTAL');
        $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colNum++).$rowNum, '');
        $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colNum++).$rowNum, '');

        $subsourceColors = ['F3F4F6', 'FFFFFF'];
        $colorIndex = 0;

        foreach ($allSubsources as $subsource) {
            $label = strtolower($subsource['source']).' - '.$subsource['subsource'];
            $startCol = $colNum;

            $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colNum++).$rowNum, $label);
            $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colNum++).$rowNum, '');
            $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colNum++).$rowNum, '');

            for ($c = $startCol; $c < $colNum; $c++) {
                $sheet->getStyle(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c).$rowNum)
                    ->getFill()
                    ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                    ->getStartColor()
                    ->setARGB($subsourceColors[$colorIndex]);
            }

            $colorIndex = ($colorIndex + 1) % 2;
        }

        $sheet->getStyle($rowNum.':'.$rowNum)->getFont()->setBold(true);
        $rowNum++;

        $colNum = 1;
        $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colNum++).$rowNum, 'SKU');
        $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colNum++).$rowNum, 'Name');

        $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colNum++).$rowNum, 'Orders');
        $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colNum++).$rowNum, 'Units');
        $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colNum++).$rowNum, 'Revenue');

        $colorIndex = 0;
        foreach ($allSubsources as $subsource) {
            $startCol = $colNum;

            $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colNum++).$rowNum, 'Orders');
            $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colNum++).$rowNum, 'Units');
            $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colNum++).$rowNum, 'Revenue');

            for ($c = $startCol; $c < $colNum; $c++) {
                $sheet->getStyle(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c).$rowNum)
                    ->getFill()
                    ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                    ->getStartColor()
                    ->setARGB($subsourceColors[$colorIndex]);
            }

            $colorIndex = ($colorIndex + 1) % 2;
        }

        $sheet->getStyle($rowNum.':'.$rowNum)->getFont()->setBold(true);
        $rowNum++;

        $groups = $this->buildQuery($filters)->get();

        foreach ($groups as $group) {
            $subsources = $this->getSubsources($group->parent_sku, $filters);

            $subsourceMap = [];
            foreach ($subsources as $subsource) {
                $key = strtolower($subsource->source).'-'.$subsource->subsource;
                $subsourceMap[$key] = [
                    'orders' => $subsource->order_count,
                    'units' => $subsource->total_units,
                    'revenue' => $subsource->total_revenue,
                ];
            }

            $colNum = 1;
            $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colNum++).$rowNum, $group->parent_sku);
            $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colNum++).$rowNum, $group->parent_sku);

            $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colNum++).$rowNum, $group->order_count);
            $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colNum++).$rowNum, $group->total_units);
            $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colNum++).$rowNum, number_format($group->total_revenue, 2, '.', ''));

            $colorIndex = 0;
            foreach ($allSubsources as $subsource) {
                $key = $subsource['source'].'-'.$subsource['subsource'];
                $startCol = $colNum;

                if (isset($subsourceMap[$key])) {
                    $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colNum++).$rowNum, $subsourceMap[$key]['orders']);
                    $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colNum++).$rowNum, $subsourceMap[$key]['units']);
                    $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colNum++).$rowNum, number_format($subsourceMap[$key]['revenue'], 2, '.', ''));
                } else {
                    $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colNum++).$rowNum, 0);
                    $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colNum++).$rowNum, 0);
                    $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colNum++).$rowNum, '0.00');
                }

                for ($c = $startCol; $c < $colNum; $c++) {
                    $sheet->getStyle(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c).$rowNum)
                        ->getFill()
                        ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                        ->getStartColor()
                        ->setARGB($subsourceColors[$colorIndex]);
                }

                $colorIndex = ($colorIndex + 1) % 2;
            }

            $rowNum++;
        }

        foreach (range(1, $colNum - 1) as $columnIndex) {
            $sheet->getColumnDimension(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($columnIndex))->setAutoSize(true);
        }

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        ob_start();
        $writer->save('php://output');

        return ob_get_clean();
    }

    protected function getSubsources(string $parentSku, array $filters): array
    {
        $dateStart = Carbon::parse($filters['date_range']['start'])->startOfDay();
        $dateEnd = Carbon::parse($filters['date_range']['end'])->endOfDay();

        $query = DB::table('order_items as oi')
            ->join('orders as o', 'o.id', '=', 'oi.order_id')
            ->where('oi.parent_sku', '=', $parentSku)
            ->whereBetween('o.received_at', [$dateStart, $dateEnd])
            ->where('o.status', '!=', 'cancelled');

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

        return $query->select([
            'o.source',
            DB::raw("COALESCE(NULLIF(o.subsource, ''), 'Unknown') as subsource"),
            DB::raw('COUNT(DISTINCT oi.order_id) as order_count'),
            DB::raw('SUM(oi.quantity) as total_units'),
            DB::raw('SUM(oi.quantity * oi.price_per_unit) as total_revenue'),
        ])
            ->groupBy('o.source', 'o.subsource')
            ->orderByDesc('total_revenue')
            ->get()
            ->toArray();
    }

    protected function getAllSubsourcesWithSource(array $filters): \Illuminate\Support\Collection
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

        $results = $query->select([
            'o.source',
            DB::raw("COALESCE(NULLIF(o.subsource, ''), 'Unknown') as subsource"),
            DB::raw('SUM(oi.quantity * oi.price_per_unit) as total_revenue'),
        ])
            ->groupBy('o.source', 'o.subsource')
            ->orderByDesc('total_revenue')
            ->get();

        return $results->map(function ($row) {
            return [
                'source' => strtolower($row->source),
                'subsource' => $row->subsource,
                'total_revenue' => (float) $row->total_revenue,
            ];
        });
    }
}
