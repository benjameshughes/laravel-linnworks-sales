<?php

namespace App\Reports\Filters;

interface FilterContract
{
    public function name(): string;

    public function label(): string;

    public function type(): string;

    public function required(): bool;

    public function default(): mixed;

    public function options(): array;

    public function validate(mixed $value): bool;
}
