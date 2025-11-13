<?php

namespace App\Reports;

use App\Reports\Enums\ExportFormat;
use App\Reports\Enums\ReportCategory;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Base class for all reports in the system.
 *
 * # Available Filter Types
 *
 * Reports can use the following filter types by returning filter objects from the filters() method:
 *
 * ## DateRangeFilter
 * - Type: 'date_range'
 * - Renders: Two date inputs (start and end)
 * - Usage: new DateRangeFilter(required: true, defaultDays: 30)
 *
 * ## SkuFilter
 * - Type: 'multi_select' (or 'select' if multiple: false)
 * - Renders: Pill selector with dynamic SKU options from database
 * - Usage: new SkuFilter(multiple: true, required: false)
 *
 * ## SubsourceFilter
 * - Type: 'multi_select' (or 'select' if multiple: false)
 * - Renders: Pill selector with dynamic subsource options from database
 * - Usage: new SubsourceFilter(multiple: true, required: false)
 *
 * ## ChannelFilter
 * - Type: 'multi_select' (or 'select' if multiple: false)
 * - Renders: Pill selector with dynamic channel options from database
 * - Usage: new ChannelFilter(multiple: true, required: false)
 *
 * ## StatusFilter
 * - Type: 'multi_select'
 * - Renders: Pill selector with order status options (processed, open, cancelled)
 * - Usage: new StatusFilter(required: false)
 *
 * ## TextFilter
 * - Type: 'text'
 * - Renders: Single text input
 * - Usage: new TextFilter(name: 'customer_name', label: 'Customer Name', required: false, placeholder: 'Enter name...')
 *
 * ## NumberRangeFilter
 * - Type: 'number_range'
 * - Renders: Two number inputs (min and max)
 * - Usage: new NumberRangeFilter(name: 'price_range', label: 'Price Range', required: false, min: 0, max: 1000)
 *
 * # Creating Custom Filters
 *
 * To create a custom filter:
 * 1. Extend AbstractFilter class
 * 2. Implement all required methods from FilterContract
 * 3. Override optional helper methods (placeholder(), helpText(), icon()) if needed
 * 4. Add the filter to your report's filters() method
 *
 * Example:
 *
 * ```php
 * class CustomFilter extends AbstractFilter
 * {
 *     public function name(): string { return 'custom'; }
 *     public function label(): string { return 'Custom Filter'; }
 *     public function type(): string { return 'text'; }
 *     public function required(): bool { return false; }
 *     public function default(): mixed { return null; }
 *     public function options(): array { return []; }
 *     public function validate(mixed $value): bool { return is_string($value); }
 *     public function placeholder(): ?string { return 'Enter custom value...'; }
 * }
 * ```
 *
 * Then in your report:
 *
 * ```php
 * public function filters(): array
 * {
 *     return [
 *         new DateRangeFilter(required: true, defaultDays: 30),
 *         new CustomFilter(),
 *     ];
 * }
 * ```
 */
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

        // Clone the query and remove ORDER BY for counting (ORDER BY breaks with calculated columns)
        $query = clone $this->buildQuery($filters);
        $query->orders = null;

        return $query->count();
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
