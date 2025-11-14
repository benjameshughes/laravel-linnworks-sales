<?php

declare(strict_types=1);

namespace App\Livewire\Charts;

final class PieChart extends BaseChart
{
    public function getChartType(): string
    {
        return 'pie';
    }

    protected function getDefaultOptions(): array
    {
        return array_merge(parent::getDefaultOptions(), [
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
                        'label' => '__PIE_LABEL_CALLBACK__',
                    ],
                ],
            ],
        ]);
    }
}
