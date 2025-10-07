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
            'elements' => [
                'line' => [
                    'tension' => 0.4,
                    'borderWidth' => 2,
                ],
                'point' => [
                    'radius' => 3,
                    'hoverRadius' => 5,
                ],
            ],
        ]);
    }
}