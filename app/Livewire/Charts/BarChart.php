<?php

declare(strict_types=1);

namespace App\Livewire\Charts;

final class BarChart extends BaseChart
{
    public function getChartType(): string
    {
        return 'bar';
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
                'bar' => [
                    'borderRadius' => 4,
                    'borderWidth' => 0,
                ],
            ],
        ]);
    }
}
