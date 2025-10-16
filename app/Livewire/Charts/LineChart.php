<?php

declare(strict_types=1);

namespace App\Livewire\Charts;

final class LineChart extends BaseChart
{
    public function getChartType(): string
    {
        return 'line';
    }

    protected function getDefaultOptions(): array
    {
        return array_merge(parent::getDefaultOptions(), [
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'grace' => '10%',
                    'grid' => [
                        'display' => true,
                        'color' => 'rgba(0, 0, 0, 0.05)',
                    ],
                    'ticks' => [
                        'padding' => 10,
                    ],
                ],
                'x' => [
                    'grid' => [
                        'display' => false,
                    ],
                    'offset' => true,
                    'ticks' => [
                        'padding' => 10,
                        'autoSkip' => true,
                        'maxRotation' => 0,
                    ],
                ],
            ],
            'elements' => [
                'line' => [
                    'tension' => 0.4,
                    'borderWidth' => 2,
                ],
                'point' => [
                    'radius' => 4,
                    'hoverRadius' => 6,
                    'hitRadius' => 10,
                ],
            ],
        ]);
    }
}
