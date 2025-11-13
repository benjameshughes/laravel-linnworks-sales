<?php

namespace App\Reports\Filters;

class NumberRangeFilter extends AbstractFilter
{
    public function __construct(
        private readonly string $name,
        private readonly string $label,
        private readonly bool $required = false,
        private readonly ?float $min = null,
        private readonly ?float $max = null
    ) {}

    public function name(): string
    {
        return $this->name;
    }

    public function label(): string
    {
        return $this->label;
    }

    public function type(): string
    {
        return 'number_range';
    }

    public function required(): bool
    {
        return $this->required;
    }

    public function default(): array
    {
        return [
            'min' => $this->min,
            'max' => $this->max,
        ];
    }

    public function options(): array
    {
        return [
            'min' => $this->min,
            'max' => $this->max,
        ];
    }

    public function validate(mixed $value): bool
    {
        if (! is_array($value)) {
            return false;
        }

        if (! isset($value['min']) && ! isset($value['max'])) {
            return false;
        }

        if (isset($value['min']) && ! is_numeric($value['min']) && ! is_null($value['min'])) {
            return false;
        }

        if (isset($value['max']) && ! is_numeric($value['max']) && ! is_null($value['max'])) {
            return false;
        }

        return true;
    }

    public function icon(): ?string
    {
        return 'calculator';
    }
}
