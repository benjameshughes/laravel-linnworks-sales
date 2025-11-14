<?php

declare(strict_types=1);

namespace App\Livewire;

use Livewire\Attributes\Reactive;
use Livewire\Component;

final class Chart extends Component
{
    public string $type = 'line';

    #[Reactive]
    public array $data = [];

    #[Reactive]
    public ?array $options = [];

    public string $chartId;

    public string $height = '300px';

    public string $width = '100%';

    protected array $mergedOptions = [];

    public function mount(
        string $type = 'line',
        array $data = [],
        ?array $options = null,
        ?string $height = null,
        ?string $width = null
    ): void {
        $this->type = $type;
        $this->chartId = 'chart-'.uniqid();
        $this->options = $options ?? [];

        // For area charts, ensure datasets have fill configuration
        if ($type === 'area' && isset($data['datasets'])) {
            foreach ($data['datasets'] as &$dataset) {
                if (! isset($dataset['fill'])) {
                    $dataset['fill'] = 'start';
                }
                if (! isset($dataset['backgroundColor']) && isset($dataset['borderColor'])) {
                    $dataset['backgroundColor'] = $this->hexToRgba($dataset['borderColor'], 0.1);
                }
            }
        }

        $this->data = $data;
        $this->mergedOptions = array_merge($this->getDefaultOptionsForType($type), $this->options ?? []);

        if ($height) {
            $this->height = $height;
        }

        if ($width) {
            $this->width = $width;
        }
    }

    public function updatedData(): void
    {
        $this->mergedOptions = array_merge($this->getDefaultOptionsForType($this->type), $this->options ?? []);

        // Dispatch event to Alpine to update the chart
        $this->dispatch('chart-update-'.$this->chartId, $this->getChartData());
    }

    public function updatedOptions(): void
    {
        $this->mergedOptions = array_merge($this->getDefaultOptionsForType($this->type), $this->options ?? []);

        // Dispatch event to Alpine to update the chart
        $this->dispatch('chart-update-'.$this->chartId, $this->getChartData());
    }

    public function getChartData(): array
    {
        // Determine Chart.js type (area uses 'line' in Chart.js)
        $chartType = $this->type === 'area' ? 'line' : $this->type;

        return [
            'type' => $chartType,
            'data' => $this->data,
            'options' => $this->mergedOptions,
        ];
    }

    protected function getDefaultOptionsForType(string $type): array
    {
        return match ($type) {
            'line' => [
                'responsive' => true,
                'maintainAspectRatio' => false,
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
                ],
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
            ],

            'area' => [
                'responsive' => true,
                'maintainAspectRatio' => false,
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
            ],

            'bar' => [
                'responsive' => true,
                'maintainAspectRatio' => false,
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
                ],
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
            ],

            'doughnut' => [
                'responsive' => true,
                'maintainAspectRatio' => false,
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
            ],

            'pie' => [
                'responsive' => true,
                'maintainAspectRatio' => false,
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
            ],

            'mixed' => [
                'responsive' => true,
                'maintainAspectRatio' => false,
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
                ],
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
            ],

            default => [
                'responsive' => true,
                'maintainAspectRatio' => false,
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
                ],
            ],
        };
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

    public function render()
    {
        return view('livewire.chart');
    }
}
