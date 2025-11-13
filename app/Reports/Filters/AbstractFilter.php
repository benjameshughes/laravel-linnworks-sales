<?php

namespace App\Reports\Filters;

abstract class AbstractFilter implements FilterContract
{
    /**
     * Get placeholder text for the filter input.
     */
    public function placeholder(): ?string
    {
        return null;
    }

    /**
     * Get help text to display below the filter.
     */
    public function helpText(): ?string
    {
        return null;
    }

    /**
     * Get icon name for the filter (Heroicons).
     */
    public function icon(): ?string
    {
        return null;
    }

    /**
     * Get whether this filter should load dynamic options.
     * Used for filters like SKUs, subsources, channels that need database queries.
     */
    public function hasDynamicOptions(): bool
    {
        return false;
    }
}
