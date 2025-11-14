<?php

declare(strict_types=1);

namespace App\Livewire\Charts;

final class DoughnutChart extends BaseChart
{
    public function getChartType(): string
    {
        return 'doughnut';
    }

    protected function getDefaultOptions(): array
    {
        return array_merge(parent::getDefaultOptions(), [
            'cutout' => '60%',
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'bottom',
                    'labels' => [
                        'padding' => 15,
                        'usePointStyle' => true,
                        'font' => [
                            'size' => 12,
                        ],
                    ],
                ],
                'tooltip' => [
                    'enabled' => true,
                    'callbacks' => [
                        'label' => '__DOUGHNUT_LABEL_CALLBACK__',
                    ],
                ],
            ],
        ]);
    }
}
