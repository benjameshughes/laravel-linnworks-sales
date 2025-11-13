<?php

namespace App\Livewire;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

#[Layout('components.layouts.app')]
class Sophie extends Component
{
    public string $sortBy = 'revenue';

    public string $sortDirection = 'desc';

    public array $expandedRows = [];

    // Filter properties
    public ?string $dateFrom = null;

    public ?string $dateTo = null;

    public array $selectedSkus = [];

    public array $selectedSubsources = [];

    public function mount(): void
    {
        // Initialize date range to last 30 days
        $this->dateTo = Carbon::now()->format('Y-m-d');
        $this->dateFrom = Carbon::now()->subDays(30)->format('Y-m-d');

        // Initialize with all SKUs and subsources selected (empty = all)
        $this->selectedSkus = [];
        $this->selectedSubsources = [];
    }

    #[Computed]
    public function dateRange(): array
    {
        return [
            'start' => $this->dateFrom
                ? Carbon::parse($this->dateFrom)->startOfDay()
                : Carbon::now()->subDays(730)->startOfDay(),
            'end' => $this->dateTo
                ? Carbon::parse($this->dateTo)->endOfDay()
                : Carbon::now()->endOfDay(),
        ];
    }

    #[Computed]
    public function variationGroups()
    {
        $dateRange = $this->dateRange;

        // Build WHERE clause for filters
        $whereClauses = ['oi.parent_sku IS NOT NULL'];
        $bindings = [];

        // Date filter (requires join with orders table)
        $whereClauses[] = 'o.received_date BETWEEN ? AND ?';
        $bindings[] = $dateRange['start'];
        $bindings[] = $dateRange['end'];

        // SKU filter
        if (! empty($this->selectedSkus)) {
            $placeholders = implode(',', array_fill(0, count($this->selectedSkus), '?'));
            $whereClauses[] = "oi.parent_sku IN ($placeholders)";
            $bindings = array_merge($bindings, $this->selectedSkus);
        }

        // Subsource filter
        if (! empty($this->selectedSubsources)) {
            $placeholders = implode(',', array_fill(0, count($this->selectedSubsources), '?'));
            $whereClauses[] = "COALESCE(NULLIF(o.subsource, ''), 'Unknown') IN ($placeholders)";
            $bindings = array_merge($bindings, $this->selectedSubsources);
        }

        $whereClause = implode(' AND ', $whereClauses);

        // Add sort bindings (8 times for CASE statements)
        $sortBindings = array_fill(0, 8, [$this->sortBy, $this->sortDirection]);
        $sortBindings = array_merge(...$sortBindings);

        $allBindings = array_merge($bindings, $sortBindings);

        // Raw MySQL query for blazing fast aggregation
        $results = DB::select("
            SELECT
                oi.parent_sku,
                COUNT(DISTINCT oi.order_id) as order_count,
                SUM(oi.quantity) as total_units,
                SUM(oi.quantity * oi.unit_price) as total_revenue
            FROM order_items oi
            INNER JOIN orders o ON o.id = oi.order_id
            WHERE {$whereClause}
            GROUP BY oi.parent_sku
            ORDER BY
                CASE
                    WHEN ? = 'sku' AND ? = 'asc' THEN oi.parent_sku
                END ASC,
                CASE
                    WHEN ? = 'sku' AND ? = 'desc' THEN oi.parent_sku
                END DESC,
                CASE
                    WHEN ? = 'orders' AND ? = 'asc' THEN order_count
                END ASC,
                CASE
                    WHEN ? = 'orders' AND ? = 'desc' THEN order_count
                END DESC,
                CASE
                    WHEN ? = 'units' AND ? = 'asc' THEN total_units
                END ASC,
                CASE
                    WHEN ? = 'units' AND ? = 'desc' THEN total_units
                END DESC,
                CASE
                    WHEN ? = 'revenue' AND ? = 'asc' THEN total_revenue
                END ASC,
                CASE
                    WHEN ? = 'revenue' AND ? = 'desc' THEN total_revenue
                    ELSE total_revenue
                END DESC
        ", $allBindings);

        // Convert to collection with consistent structure
        return collect($results)->map(function ($group) {
            return [
                'sku' => $group->parent_sku,
                'name' => $group->parent_sku,
                'order_count' => (int) $group->order_count,
                'total_units' => (int) $group->total_units,
                'total_revenue' => (float) $group->total_revenue,
            ];
        });
    }

    #[Computed]
    public function availableSkus(): Collection
    {
        $dateRange = $this->dateRange;

        $results = DB::select('
            SELECT DISTINCT oi.parent_sku as sku
            FROM order_items oi
            INNER JOIN orders o ON o.id = oi.order_id
            WHERE oi.parent_sku IS NOT NULL
              AND o.received_date BETWEEN ? AND ?
            ORDER BY oi.parent_sku ASC
        ', [$dateRange['start'], $dateRange['end']]);

        return collect($results)->pluck('sku');
    }

    #[Computed]
    public function availableSubsources(): Collection
    {
        $dateRange = $this->dateRange;

        $results = DB::select("
            SELECT DISTINCT COALESCE(NULLIF(o.subsource, ''), 'Unknown') as subsource
            FROM orders o
            INNER JOIN order_items oi ON o.id = oi.order_id
            WHERE oi.parent_sku IS NOT NULL
              AND o.received_date BETWEEN ? AND ?
            ORDER BY subsource ASC
        ", [$dateRange['start'], $dateRange['end']]);

        return collect($results)->pluck('subsource');
    }

    public function getSubsources(string $parentSku)
    {
        $dateRange = $this->dateRange;

        $whereClauses = ['oi.parent_sku = ?'];
        $bindings = [$parentSku];

        // Date filter
        $whereClauses[] = 'o.received_date BETWEEN ? AND ?';
        $bindings[] = $dateRange['start'];
        $bindings[] = $dateRange['end'];

        // Subsource filter
        if (! empty($this->selectedSubsources)) {
            $placeholders = implode(',', array_fill(0, count($this->selectedSubsources), '?'));
            $whereClauses[] = "COALESCE(NULLIF(o.subsource, ''), 'Unknown') IN ($placeholders)";
            $bindings = array_merge($bindings, $this->selectedSubsources);
        }

        $whereClause = implode(' AND ', $whereClauses);

        // Raw MySQL query for subsource breakdown (includes source)
        return DB::select("
            SELECT
                o.source,
                COALESCE(NULLIF(o.subsource, ''), 'Unknown') as subsource,
                COUNT(DISTINCT oi.order_id) as order_count,
                SUM(oi.quantity) as total_units,
                SUM(oi.quantity * oi.unit_price) as total_revenue
            FROM order_items oi
            INNER JOIN orders o ON o.id = oi.order_id
            WHERE {$whereClause}
            GROUP BY o.source, o.subsource
            ORDER BY total_revenue DESC
        ", $bindings);
    }

    protected function getAllSubsourcesWithSource(): Collection
    {
        $dateRange = $this->dateRange;

        $whereClauses = ['oi.parent_sku IS NOT NULL'];
        $bindings = [];

        // Date filter
        $whereClauses[] = 'o.received_date BETWEEN ? AND ?';
        $bindings[] = $dateRange['start'];
        $bindings[] = $dateRange['end'];

        // SKU filter
        if (! empty($this->selectedSkus)) {
            $placeholders = implode(',', array_fill(0, count($this->selectedSkus), '?'));
            $whereClauses[] = "oi.parent_sku IN ($placeholders)";
            $bindings = array_merge($bindings, $this->selectedSkus);
        }

        // Subsource filter
        if (! empty($this->selectedSubsources)) {
            $placeholders = implode(',', array_fill(0, count($this->selectedSubsources), '?'));
            $whereClauses[] = "COALESCE(NULLIF(o.subsource, ''), 'Unknown') IN ($placeholders)";
            $bindings = array_merge($bindings, $this->selectedSubsources);
        }

        $whereClause = implode(' AND ', $whereClauses);

        // Get all unique source-subsource combinations ordered by total revenue
        $results = DB::select("
            SELECT
                o.source,
                COALESCE(NULLIF(o.subsource, ''), 'Unknown') as subsource,
                SUM(oi.quantity * oi.unit_price) as total_revenue
            FROM order_items oi
            INNER JOIN orders o ON o.id = oi.order_id
            WHERE {$whereClause}
            GROUP BY o.source, o.subsource
            ORDER BY total_revenue DESC
        ", $bindings);

        return collect($results)->map(function ($row) {
            return [
                'source' => strtolower($row->source),
                'subsource' => $row->subsource,
                'total_revenue' => (float) $row->total_revenue,
            ];
        });
    }

    public function toggleSku(string $sku): void
    {
        if (in_array($sku, $this->selectedSkus)) {
            $this->selectedSkus = array_values(array_diff($this->selectedSkus, [$sku]));
        } else {
            $this->selectedSkus[] = $sku;
        }

        // Clear computed cache to trigger refresh
        unset($this->variationGroups);
    }

    public function toggleSubsource(string $subsource): void
    {
        if (in_array($subsource, $this->selectedSubsources)) {
            $this->selectedSubsources = array_values(array_diff($this->selectedSubsources, [$subsource]));
        } else {
            $this->selectedSubsources[] = $subsource;
        }

        // Clear computed cache to trigger refresh
        unset($this->variationGroups);
    }

    public function clearFilters(): void
    {
        $this->selectedSkus = [];
        $this->selectedSubsources = [];

        unset($this->variationGroups);
    }

    public function updatedDateFrom(): void
    {
        unset($this->availableSkus, $this->availableSubsources, $this->variationGroups);
    }

    public function updatedDateTo(): void
    {
        unset($this->availableSkus, $this->availableSubsources, $this->variationGroups);
    }

    public function toggleRow(string $sku): void
    {
        if (in_array($sku, $this->expandedRows)) {
            $this->expandedRows = array_values(array_diff($this->expandedRows, [$sku]));
        } else {
            $this->expandedRows[] = $sku;
        }
    }

    public function sortBy(string $column): void
    {
        if ($this->sortBy === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDirection = 'desc';
        }

        unset($this->variationGroups);
    }

    public function downloadCsv()
    {
        $data = $this->prepareCSVData();

        if (empty($data)) {
            // Optionally dispatch a notification/error event
            return null;
        }

        return response()->streamDownload(function () use ($data) {
            echo $this->generateXLSX($data);
        }, $this->generateXLSXFilename(), [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Content-Description' => 'File Transfer',
            'Expires' => '0',
            'Pragma' => 'public',
        ]);
    }

    protected function prepareCSVData(): array
    {
        $groups = $this->variationGroups;

        if ($groups->isEmpty()) {
            return [];
        }

        // Get all subsources ordered by revenue
        $allSubsources = $this->getAllSubsourcesWithSource();

        if ($allSubsources->isEmpty()) {
            return [];
        }

        $rows = [];

        foreach ($groups as $group) {
            // Get subsource breakdown for this parent SKU
            $subsources = $this->getSubsources($group['sku']);

            // Create a map of source-subsource to metrics
            $subsourceMap = [];
            foreach ($subsources as $subsource) {
                $key = strtolower($subsource->source).'-'.$subsource->subsource;
                $subsourceMap[$key] = [
                    'orders' => $subsource->order_count,
                    'units' => $subsource->total_units,
                    'revenue' => $subsource->total_revenue,
                ];
            }

            // Build row for this SKU - start with SKU and Name
            $row = [
                'sku' => $group['sku'],
                'name' => $group['sku'],
            ];

            // Calculate totals first
            $totalOrders = 0;
            $totalUnits = 0;
            $totalRevenue = 0;

            // Create a map for subsource data
            $subsourceData = [];

            foreach ($allSubsources as $subsource) {
                $key = $subsource['source'].'-'.$subsource['subsource'];

                if (isset($subsourceMap[$key])) {
                    $orders = $subsourceMap[$key]['orders'];
                    $units = $subsourceMap[$key]['units'];
                    $revenue = $subsourceMap[$key]['revenue'];

                    $subsourceData[] = [
                        'orders' => $orders,
                        'units' => $units,
                        'revenue' => number_format($revenue, 2, '.', ''),
                    ];

                    // Accumulate totals
                    $totalOrders += $orders;
                    $totalUnits += $units;
                    $totalRevenue += $revenue;
                } else {
                    // No data for this subsource
                    $subsourceData[] = [
                        'orders' => 0,
                        'units' => 0,
                        'revenue' => '0.00',
                    ];
                }
            }

            // Add TOTAL columns BEFORE subsource data
            $row[] = $totalOrders;
            $row[] = $totalUnits;
            $row[] = number_format($totalRevenue, 2, '.', '');

            // Add subsource data after totals
            foreach ($subsourceData as $data) {
                $row[] = $data['orders'];
                $row[] = $data['units'];
                $row[] = $data['revenue'];
            }

            $rows[] = $row;
        }

        return [
            'subsources' => $allSubsources,
            'rows' => $rows,
        ];
    }

    protected function generateXLSX(array $data): string
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();

        $rowNum = 1;

        // Row 1 - Date range
        $dateRange = Carbon::parse($this->dateFrom)->format('jS F Y').' to '.Carbon::parse($this->dateTo)->format('jS F Y');
        $sheet->setCellValue('A1', 'Date Range: '.$dateRange);
        $sheet->getStyle('A1')->getFont()->setBold(true);
        $rowNum++;

        // Row 2 - Subsource headers
        $colNum = 1;
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colNum).$rowNum, 'Subsource');
        $colNum++;

        // TOTAL header (spans 3 columns)
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colNum++).$rowNum, 'TOTAL');
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colNum++).$rowNum, '');
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colNum++).$rowNum, '');

        // Subsource headers with two-tone colors
        $subsourceColors = ['F3F4F6', 'FFFFFF'];
        $colorIndex = 0;

        foreach ($data['subsources'] as $subsource) {
            $label = strtolower($subsource['source']).' - '.$subsource['subsource'];
            $startCol = $colNum;

            $sheet->setCellValue(Coordinate::stringFromColumnIndex($colNum++).$rowNum, $label);
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($colNum++).$rowNum, '');
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($colNum++).$rowNum, '');

            // Apply background color to this subsource group (3 columns)
            for ($c = $startCol; $c < $colNum; $c++) {
                $sheet->getStyle(Coordinate::stringFromColumnIndex($c).$rowNum)
                    ->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()
                    ->setARGB($subsourceColors[$colorIndex]);
            }

            $colorIndex = ($colorIndex + 1) % 2;
        }

        $sheet->getStyle($rowNum.':'.$rowNum)->getFont()->setBold(true);
        $rowNum++;

        // Row 3 - Column headers
        $colNum = 1;
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colNum++).$rowNum, 'SKU');
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colNum++).$rowNum, 'Name');

        // TOTAL columns
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colNum++).$rowNum, 'Orders');
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colNum++).$rowNum, 'Units');
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colNum++).$rowNum, 'Revenue');

        // Subsource columns with two-tone colors
        $colorIndex = 0;
        foreach ($data['subsources'] as $subsource) {
            $startCol = $colNum;

            $sheet->setCellValue(Coordinate::stringFromColumnIndex($colNum++).$rowNum, 'Orders');
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($colNum++).$rowNum, 'Units');
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($colNum++).$rowNum, 'Revenue');

            // Apply same background color to match Row 2
            for ($c = $startCol; $c < $colNum; $c++) {
                $sheet->getStyle(Coordinate::stringFromColumnIndex($c).$rowNum)
                    ->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()
                    ->setARGB($subsourceColors[$colorIndex]);
            }

            $colorIndex = ($colorIndex + 1) % 2;
        }

        $sheet->getStyle($rowNum.':'.$rowNum)->getFont()->setBold(true);
        $rowNum++;

        // Data rows
        foreach ($data['rows'] as $dataRow) {
            $colNum = 1;

            foreach ($dataRow as $cellValue) {
                $sheet->setCellValue(Coordinate::stringFromColumnIndex($colNum).$rowNum, $cellValue);
                $colNum++;
            }

            // Apply alternating colors to subsource columns
            $colNum = 6; // Start after SKU, Name, TOTAL (5 columns)
            $colorIndex = 0;

            foreach ($data['subsources'] as $subsource) {
                for ($i = 0; $i < 3; $i++) {
                    $sheet->getStyle(Coordinate::stringFromColumnIndex($colNum).$rowNum)
                        ->getFill()
                        ->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()
                        ->setARGB($subsourceColors[$colorIndex]);
                    $colNum++;
                }
                $colorIndex = ($colorIndex + 1) % 2;
            }

            $rowNum++;
        }

        // Auto-size columns
        foreach (range(1, $colNum - 1) as $columnIndex) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($columnIndex))->setAutoSize(true);
        }

        // Write to stream
        $writer = new Xlsx($spreadsheet);
        ob_start();
        $writer->save('php://output');

        return ob_get_clean();
    }

    protected function generateXLSXFilename(): string
    {
        $fromDate = Carbon::parse($this->dateFrom)->format('Ymd');
        $toDate = Carbon::parse($this->dateTo)->format('Ymd');

        return "sophie-variation-groups-{$fromDate}-{$toDate}.xlsx";
    }

    public function render()
    {
        return view('livewire.sophie');
    }
}
