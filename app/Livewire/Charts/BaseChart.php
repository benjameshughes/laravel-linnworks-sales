<?php

declare(strict_types=1);

namespace App\Livewire\Charts;

use Livewire\Attributes\Reactive;
use Livewire\Component;

abstract class BaseChart extends Component
{
    #[Reactive]
    public array $data = [];

    #[Reactive]
    public ?array $options = [];

    public string $chartId;

    public string $height = '300px';

    public string $width = '100%';

    protected array $mergedOptions = [];

    abstract public function getChartType(): string;

    public function mount(
        array $data = [],
        ?array $options = null,
        ?string $height = null,
        ?string $width = null
    ): void {
        $this->chartId = 'chart-'.uniqid();
        $this->data = $data;
        $this->options = $options ?? [];
        $this->mergedOptions = array_merge($this->getDefaultOptions(), $this->options ?? []);

        if ($height) {
            $this->height = $height;
        }

        if ($width) {
            $this->width = $width;
        }
    }

    protected function getDefaultOptions(): array
    {
        return [
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
        ];
    }

    public function updatedData(): void
    {
        $this->updateMergedOptions();
        $this->dispatchChartUpdate();
    }

    public function updatedOptions(): void
    {
        $this->updateMergedOptions();
        $this->dispatchChartUpdate();
    }

    protected function dispatchChartUpdate(): void
    {
        $this->dispatch('chart-update-'.$this->chartId, $this->getChartData());
    }

    protected function updateMergedOptions(): void
    {
        $this->mergedOptions = array_merge($this->getDefaultOptions(), $this->options ?? []);
    }

    public function getChartData(): array
    {
        return [
            'type' => $this->getChartType(),
            'data' => $this->data,
            'options' => $this->mergedOptions,
        ];
    }

    public function render()
    {
        return view('livewire.charts.base-chart');
    }
}
