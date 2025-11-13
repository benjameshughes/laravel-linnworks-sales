<?php

namespace App\Livewire;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

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
            echo $this->generateCSV($data);
        }, $this->generateCSVFilename(), [
            'Content-Type' => 'text/csv',
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

            // Build row for this SKU
            $row = [
                'sku' => $group['sku'],
                'name' => $group['sku'],
            ];

            // Add metrics for each subsource in order
            foreach ($allSubsources as $subsource) {
                $key = $subsource['source'].'-'.$subsource['subsource'];

                if (isset($subsourceMap[$key])) {
                    $row[] = $subsourceMap[$key]['orders'];
                    $row[] = $subsourceMap[$key]['units'];
                    $row[] = number_format($subsourceMap[$key]['revenue'], 2, '.', '');
                } else {
                    // No data for this subsource
                    $row[] = 0;
                    $row[] = 0;
                    $row[] = '0.00';
                }
            }

            $rows[] = $row;
        }

        return [
            'subsources' => $allSubsources,
            'rows' => $rows,
        ];
    }

    protected function generateCSV(array $data): string
    {
        $output = fopen('php://temp', 'r+');

        // Row 1: Date range in human-readable format
        $dateFrom = Carbon::parse($this->dateFrom)->format('jS F Y');
        $dateTo = Carbon::parse($this->dateTo)->format('jS F Y');
        fputcsv($output, ["Date Range: {$dateFrom} to {$dateTo}"]);

        // Row 2 - Subsource headers (with column spanning)
        $subsourceRow = ['Subsource'];
        foreach ($data['subsources'] as $subsource) {
            $subsourceRow[] = strtolower($subsource['source']).' - '.$subsource['subsource'];
            $subsourceRow[] = ''; // Empty cell for Orders column
            $subsourceRow[] = ''; // Empty cell for Units column
            // Revenue column will get the next subsource header
        }
        fputcsv($output, $subsourceRow);

        // Row 3: Column headers (SKU, Name, then Orders/Units/Revenue for each subsource)
        $columnHeaders = ['SKU', 'Name'];
        foreach ($data['subsources'] as $subsource) {
            $columnHeaders[] = 'Orders';
            $columnHeaders[] = 'Units';
            $columnHeaders[] = 'Revenue';
        }
        fputcsv($output, $columnHeaders);

        // Rows 4+: Data rows
        foreach ($data['rows'] as $row) {
            fputcsv($output, $row);
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv;
    }

    protected function generateCSVFilename(): string
    {
        $fromDate = Carbon::parse($this->dateFrom)->format('Ymd');
        $toDate = Carbon::parse($this->dateTo)->format('Ymd');

        return "sophie-variation-groups-{$fromDate}-{$toDate}.csv";
    }

    public function render()
    {
        return view('livewire.sophie');
    }
}
