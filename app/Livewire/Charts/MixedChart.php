<?php

declare(strict_types=1);

namespace App\Livewire\Charts;

final class MixedChart extends BaseChart
{
    public function getChartType(): string
    {
        return 'bar'; // Default type, but datasets can override
    }

    protected function getDefaultOptions(): array
    {
        return array_merge(parent::getDefaultOptions(), [
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'grid' => [
                        'display' => true,
                        'color' => 'rgba(0, 0, 0, 0.05)',
                    ],
                ],
                'x' => [
                    'grid' => [
                        'display' => false,
                    ],
                ],
            ],
        ]);
    }
}
