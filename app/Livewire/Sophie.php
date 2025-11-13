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

        // Raw MySQL query for subsource breakdown
        return DB::select("
            SELECT
                COALESCE(NULLIF(o.subsource, ''), 'Unknown') as subsource,
                COUNT(DISTINCT oi.order_id) as order_count,
                SUM(oi.quantity) as total_units,
                SUM(oi.quantity * oi.unit_price) as total_revenue
            FROM order_items oi
            INNER JOIN orders o ON o.id = oi.order_id
            WHERE {$whereClause}
            GROUP BY o.subsource
            ORDER BY total_revenue DESC
        ", $bindings);
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

        $data = [];

        foreach ($groups as $group) {
            // Add summary row for parent SKU
            $data[] = [
                'type' => 'Summary',
                'parent_sku' => $group['sku'],
                'item_channel' => $group['sku'],
                'orders' => $group['order_count'],
                'units_sold' => $group['total_units'],
                'revenue' => number_format($group['total_revenue'], 2, '.', ''),
                'percent_of_parent' => '100%',
            ];

            // Add subsource rows
            $subsources = $this->getSubsources($group['sku']);
            $parentRevenue = $group['total_revenue'];

            foreach ($subsources as $subsource) {
                $percentage = $parentRevenue > 0
                    ? round(($subsource->total_revenue / $parentRevenue) * 100)
                    : 0;

                $data[] = [
                    'type' => 'Subsource',
                    'parent_sku' => $group['sku'],
                    'item_channel' => $subsource->subsource,
                    'orders' => $subsource->order_count,
                    'units_sold' => $subsource->total_units,
                    'revenue' => number_format($subsource->total_revenue, 2, '.', ''),
                    'percent_of_parent' => $percentage.'%',
                ];
            }
        }

        return $data;
    }

    protected function generateCSV(array $data): string
    {
        $output = fopen('php://temp', 'r+');

        // Metadata row
        $dateRange = Carbon::parse($this->dateFrom)->format('Y-m-d').' to '.Carbon::parse($this->dateTo)->format('Y-m-d');
        $exportTime = Carbon::now()->format('Y-m-d H:i:s');
        fputcsv($output, [
            'Sophie Variation Group Sales Export',
            '',
            'Date Range: '.$dateRange,
            'Exported: '.$exportTime,
        ]);

        // Active filters row (if any)
        $filtersText = $this->getActiveFiltersText();
        if ($filtersText) {
            fputcsv($output, ['Filters Applied:', $filtersText]);
        }

        // Empty separator row
        fputcsv($output, []);

        // Column headers
        fputcsv($output, [
            'Type',
            'Parent SKU',
            'Item/Channel',
            'Orders',
            'Units Sold',
            'Revenue (£)',
            '% of Parent Revenue',
        ]);

        // Data rows
        foreach ($data as $row) {
            fputcsv($output, [
                $row['type'],
                $row['parent_sku'],
                $row['item_channel'],
                $row['orders'],
                $row['units_sold'],
                $row['revenue'],
                $row['percent_of_parent'],
            ]);
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

    protected function getActiveFiltersText(): string
    {
        $filters = [];

        if (! empty($this->selectedSkus)) {
            $skuCount = count($this->selectedSkus);
            $skuList = implode(' ', array_slice($this->selectedSkus, 0, 3));
            $filters[] = "{$skuCount} SKU(s): {$skuList}".(count($this->selectedSkus) > 3 ? '...' : '');
        }

        if (! empty($this->selectedSubsources)) {
            $subCount = count($this->selectedSubsources);
            $subList = implode(' ', array_slice($this->selectedSubsources, 0, 3));
            $filters[] = "{$subCount} Subsource(s): {$subList}".(count($this->selectedSubsources) > 3 ? '...' : '');
        }

        return implode(' | ', $filters);
    }

    public function render()
    {
        return view('livewire.sophie');
    }
}
