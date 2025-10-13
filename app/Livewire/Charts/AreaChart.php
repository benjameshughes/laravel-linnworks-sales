<?php

declare(strict_types=1);

namespace App\Livewire\Charts;

final class AreaChart extends BaseChart
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
                    'fill' => true,
                ],
                'point' => [
                    'radius' => 0,
                    'hoverRadius' => 4,
                ],
            ],
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'top',
                ],
                'tooltip' => [
                    'enabled' => true,
                    'mode' => 'index',
                    'intersect' => false,
                ],
                'filler' => [
                    'propagate' => false,
                ],
            ],
        ]);
    }

    public function mount(
        array $data = [],
        ?array $options = null,
        ?string $height = null,
        ?string $width = null
    ): void {
        // Create a copy of the data to avoid mutating the reactive property
        $processedData = $data;

        // Ensure datasets have fill configuration for area charts
        if (isset($processedData['datasets'])) {
            foreach ($processedData['datasets'] as &$dataset) {
                if (! isset($dataset['fill'])) {
                    $dataset['fill'] = 'start';
                }
                if (! isset($dataset['backgroundColor'])) {
                    // Use borderColor with transparency if no backgroundColor is set
                    if (isset($dataset['borderColor'])) {
                        $dataset['backgroundColor'] = $this->hexToRgba($dataset['borderColor'], 0.1);
                    }
                }
            }
        }

        parent::mount($processedData, $options ?? [], $height, $width);
    }

    private function hexToRgba(string $hex, float $alpha): string
    {
        $hex = str_replace('#', '', $hex);

        if (strlen($hex) === 3) {
            $r = hexdec(substr($hex, 0, 1).substr($hex, 0, 1));
            $g = hexdec(substr($hex, 1, 1).substr($hex, 1, 1));
            $b = hexdec(substr($hex, 2, 1).substr($hex, 2, 1));
        } else {
            $r = hexdec(substr($hex, 0, 2));
            $g = hexdec(substr($hex, 2, 2));
            $b = hexdec(substr($hex, 4, 2));
        }

        return "rgba($r, $g, $b, $alpha)";
    }
}
