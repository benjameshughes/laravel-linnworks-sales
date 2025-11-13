<?php

namespace App\Reports;

use App\Reports\Enums\ExportFormat;
use App\Reports\Enums\ReportCategory;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

abstract class AbstractReport
{
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

        return $this->buildQuery($filters)->count();
    }

    public function all(array $filters): Collection
    {
        $this->validateFilters($filters);

        return $this->buildQuery($filters)->get();
    }

    public function export(array $filters, ExportFormat $format = ExportFormat::XLSX): string
    {
        $this->validateFilters($filters);
        $exporter = new Exports\ReportExport($this, $this->buildQuery($filters), $filters, $format);

        return $exporter->generate();
    }

    protected function validateFilters(array $filters): void
    {
        foreach ($this->filters() as $filter) {
            $filterName = $filter->name();

            if ($filter->required() && ! isset($filters[$filterName])) {
                throw new \InvalidArgumentException("Filter '{$filterName}' is required");
            }

            if (isset($filters[$filterName]) && ! $filter->validate($filters[$filterName])) {
                throw new \InvalidArgumentException("Filter '{$filterName}' has invalid value");
            }
        }
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
