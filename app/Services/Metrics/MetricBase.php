<?php

declare(strict_types=1);

namespace App\Services\Metrics;

use Illuminate\Support\Collection;

/**
 * Base metric class for getting and analysing data sets
 */
abstract class MetricBase
{
    protected Collection $data;

    public function __construct(Collection $data)
    {
        $this->data = $data;
    }

    public function getData(): Collection
    {
        return $this->data;
    }
}
