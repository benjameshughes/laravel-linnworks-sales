<?php

namespace App\Reports\Filters;

class TextFilter extends AbstractFilter
{
    public function __construct(
        private readonly string $name,
        private readonly string $label,
        private readonly bool $required = false,
        private readonly ?string $placeholder = null,
        private readonly ?string $icon = null
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
        return 'text';
    }

    public function required(): bool
    {
        return $this->required;
    }

    public function default(): ?string
    {
        return null;
    }

    public function options(): array
    {
        return [];
    }

    public function validate(mixed $value): bool
    {
        return is_string($value) || is_null($value);
    }

    public function placeholder(): ?string
    {
        return $this->placeholder;
    }

    public function icon(): ?string
    {
        return $this->icon;
    }
}
