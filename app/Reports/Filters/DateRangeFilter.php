<?php

namespace App\Reports\Filters;

use Carbon\Carbon;

class DateRangeFilter implements FilterContract
{
    public function __construct(
        private readonly bool $required = true,
        private readonly ?int $defaultDays = 30
    ) {}

    public function name(): string
    {
        return 'date_range';
    }

    public function label(): string
    {
        return 'Date Range';
    }

    public function type(): string
    {
        return 'date_range';
    }

    public function required(): bool
    {
        return $this->required;
    }

    public function default(): array
    {
        return [
            'start' => Carbon::now()->subDays($this->defaultDays ?? 30)->format('Y-m-d'),
            'end' => Carbon::now()->format('Y-m-d'),
        ];
    }

    public function options(): array
    {
        return [];
    }

    public function validate(mixed $value): bool
    {
        if (! is_array($value)) {
            return false;
        }

        if (! isset($value['start']) || ! isset($value['end'])) {
            return false;
        }

        try {
            Carbon::parse($value['start']);
            Carbon::parse($value['end']);

            return true;
        } catch (\Exception) {
            return false;
        }
    }
}
