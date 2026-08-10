<?php

namespace App\Reports;

use Illuminate\Support\Str;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use App\Reports\Enums\ExportFormat;
use App\Reports\Enums\ReportCategory;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;

/**
 * Base class for all reports.
 *
 * Drop a subclass into app/Reports and ReportRegistry finds it by scanning the
 * directory - no registration needed. Available filters live in Reports\Filters;
 * custom ones extend AbstractFilter.
 */
abstract class AbstractReport
{
    /**
     * Ceiling on unpaginated fetches. Reports run against a table with 160k+
     * rows, so an unbounded get() is an out-of-memory waiting to happen.
     */
    private const MAX_ROWS = 50_000;

    abstract public function name(): string;

    abstract public function description(): string;

    abstract public function icon(): string;

    abstract public function category(): ReportCategory;

    abstract public function filters(): array;

    abstract public function columns(): array;

    abstract protected function buildQuery(array $filters): EloquentBuilder|QueryBuilder;

    public function slug(): string
    {
        return Str::kebab(class_basename($this));
    }

    public function preview(array $filters, int $limit = 100): Collection
    {
        $this->validateFilters($filters);

        return $this->buildQuery($filters)->limit($limit)->get();
    }

    public function count(array $filters): int
    {
        $this->validateFilters($filters);

        // Wrap in subquery so COUNT(*) counts total rows, not per-group
        $query = clone $this->buildQuery($filters);
        $query->orders = null;

        return (int) DB::query()->fromSub($query, 'sub')->count();
    }

    public function all(array $filters): Collection
    {
        $this->validateFilters($filters);

        return $this->buildQuery($filters)->limit(self::MAX_ROWS)->get();
    }

    public function export(array $filters, ExportFormat $format = ExportFormat::XLSX): string
    {
        $this->validateFilters($filters);
        $exporter = new Exports\ReportExport($this, $this->buildQuery($filters), $filters, $format);

        return $exporter->generate();
    }

    /**
     * Export to a temp file and return the path. Memory-efficient for large reports.
     */
    public function exportToFile(array $filters, ExportFormat $format = ExportFormat::XLSX): string
    {
        $this->validateFilters($filters);
        $exporter = new Exports\ReportExport($this, $this->buildQuery($filters), $filters, $format);

        return $exporter->generateToFile();
    }

    protected function validateFilters(array $filters): void
    {
        collect($this->filters())->each(function (Filters\FilterContract $filter) use ($filters): void {
            $name = $filter->name();
            $supplied = $filters[$name] ?? null;

            throw_if(
                $filter->required() && ! isset($filters[$name]),
                \InvalidArgumentException::class,
                "Filter '{$name}' is required",
            );

            throw_if(
                isset($filters[$name]) && ! $filter->validate($supplied),
                \InvalidArgumentException::class,
                "Filter '{$name}' has invalid value",
            );
        });
    }

    public function getDefaultFilters(): array
    {
        $defaults = [];

        foreach ($this->filters() as $filter) {
            $defaults[$filter->name()] = $filter->default();
        }

        return $defaults;
    }
}
