<?php

declare(strict_types=1);

namespace App\Traits;

use Illuminate\Support\Collection;

trait PreparesChartData
{
    protected function prepareLineChartData(
        Collection $data,
        string $labelKey,
        array $datasets,
        ?array $colors = null
    ): array {
        $defaultColors = [
            '#3B82F6', // blue
            '#10B981', // emerald
            '#8B5CF6', // purple
            '#F59E0B', // amber
            '#EF4444', // red
            '#6366F1', // indigo
            '#EC4899', // pink
            '#14B8A6', // teal
        ];

        $colors = $colors ?? $defaultColors;

        $labels = $data->pluck($labelKey)->toArray();
        $chartDatasets = [];

        foreach ($datasets as $index => $dataset) {
            $color = $colors[$index % count($colors)];

            $chartDatasets[] = [
                'label' => $dataset['label'],
                'data' => $data->pluck($dataset['dataKey'])->toArray(),
                'borderColor' => $color,
                'backgroundColor' => $color,
                'tension' => $dataset['tension'] ?? 0.4,
                'fill' => $dataset['fill'] ?? false,
                'pointRadius' => $dataset['pointRadius'] ?? 3,
                'pointHoverRadius' => $dataset['pointHoverRadius'] ?? 5,
                'borderWidth' => $dataset['borderWidth'] ?? 2,
            ];
        }

        return [
            'labels' => $labels,
            'datasets' => $chartDatasets,
        ];
    }

    protected function prepareBarChartData(
        Collection $data,
        string $labelKey,
        array $datasets,
        ?array $colors = null
    ): array {
        $defaultColors = [
            '#3B82F6', // blue
            '#10B981', // emerald
            '#8B5CF6', // purple
            '#F59E0B', // amber
            '#EF4444', // red
        ];

        $colors = $colors ?? $defaultColors;

        $labels = $data->pluck($labelKey)->toArray();
        $chartDatasets = [];

        foreach ($datasets as $index => $dataset) {
            $color = $colors[$index % count($colors)];

            $chartDatasets[] = [
                'label' => $dataset['label'],
                'data' => $data->pluck($dataset['dataKey'])->toArray(),
                'backgroundColor' => $color,
                'borderColor' => $color,
                'borderWidth' => 0,
                'borderRadius' => $dataset['borderRadius'] ?? 4,
            ];
        }

        return [
            'labels' => $labels,
            'datasets' => $chartDatasets,
        ];
    }

    protected function prepareDoughnutChartData(
        Collection $data,
        string $labelKey,
        string $dataKey,
        ?array $colors = null
    ): array {
        $defaultColors = [
            '#3B82F6', // blue
            '#10B981', // emerald
            '#8B5CF6', // purple
            '#F59E0B', // amber
            '#EF4444', // red
            '#6366F1', // indigo
            '#EC4899', // pink
            '#14B8A6', // teal
        ];

        $colors = $colors ?? $defaultColors;
        $useColors = array_slice($colors, 0, $data->count());

        return [
            'labels' => $data->pluck($labelKey)->toArray(),
            'datasets' => [
                [
                    'data' => $data->pluck($dataKey)->toArray(),
                    'backgroundColor' => $useColors,
                    'borderWidth' => 0,
                    'spacing' => 2,
                ],
            ],
        ];
    }

    protected function formatCurrency(float $value, int $decimals = 0): string
    {
        return '£'.number_format($value, $decimals);
    }

    protected function formatNumber(float $value, int $decimals = 0): string
    {
        return number_format($value, $decimals);
    }
}
