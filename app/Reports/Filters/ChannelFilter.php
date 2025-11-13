<?php

namespace App\Reports\Filters;

class ChannelFilter extends AbstractFilter
{
    public function __construct(
        private readonly bool $multiple = true,
        private readonly bool $required = false
    ) {}

    public function name(): string
    {
        return 'channels';
    }

    public function label(): string
    {
        return 'Channels';
    }

    public function type(): string
    {
        return $this->multiple ? 'multi_select' : 'select';
    }

    public function required(): bool
    {
        return $this->required;
    }

    public function default(): mixed
    {
        return $this->multiple ? [] : null;
    }

    public function options(): array
    {
        return [];
    }

    public function validate(mixed $value): bool
    {
        if ($this->multiple) {
            return is_array($value);
        }

        return is_string($value) || is_null($value);
    }

    public function hasDynamicOptions(): bool
    {
        return true;
    }

    public function icon(): ?string
    {
        return 'shopping-bag';
    }
}
