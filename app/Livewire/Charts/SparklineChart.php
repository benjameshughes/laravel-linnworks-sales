<?php

declare(strict_types=1);

namespace App\Livewire\Charts;

final class SparklineChart extends BaseChart
{
    public function getChartType(): string
    {
        return 'line';
    }

    protected function getDefaultOptions(): array
    {
        return [
            'responsive' => true,
            'maintainAspectRatio' => false,
            'plugins' => [
                'legend' => [
                    'display' => false,
                ],
                'tooltip' => [
                    'enabled' => true,
                    'mode' => 'index',
                    'intersect' => false,
                ],
            ],
            'scales' => [
                'x' => [
                    'display' => false,
                ],
                'y' => [
                    'display' => false,
                ],
            ],
            'elements' => [
                'point' => [
                    'radius' => 0,
                    'hoverRadius' => 3,
                ],
                'line' => [
                    'borderWidth' => 2,
                    'tension' => 0.4,
                ],
            ],
        ];
    }

    public function mount(
        array $data = [],
        ?array $options = null,
        ?string $height = null,
        ?string $width = null
    ): void {
        // Set default height for sparklines
        $height = $height ?? '40px';

        // Create a copy of the data to avoid mutating the reactive property
        $processedData = $data;

        // Ensure single dataset for sparklines
        if (isset($processedData['datasets']) && count($processedData['datasets']) > 0) {
            $processedData['datasets'] = [$processedData['datasets'][0]];
        }

        parent::mount($processedData, $options ?? [], $height, $width);
    }
}
