<?php

namespace App\Reports\Filters;

class StatusFilter extends AbstractFilter
{
    public function __construct(
        private readonly array $allowedStatuses = ['processed', 'open', 'cancelled'],
        private readonly bool $required = false
    ) {}

    public function name(): string
    {
        return 'statuses';
    }

    public function label(): string
    {
        return 'Order Status';
    }

    public function type(): string
    {
        return 'multi_select';
    }

    public function required(): bool
    {
        return $this->required;
    }

    public function default(): array
    {
        return [];
    }

    public function options(): array
    {
        return $this->allowedStatuses;
    }

    public function validate(mixed $value): bool
    {
        if (! is_array($value)) {
            return false;
        }

        foreach ($value as $status) {
            if (! in_array($status, $this->allowedStatuses)) {
                return false;
            }
        }

        return true;
    }

    public function icon(): ?string
    {
        return 'check-circle';
    }
}
