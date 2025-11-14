<div
    wire:ignore
    x-data="baseChart()"
    x-init="initChart(@js($this->getChartData()), @js($chartId))"
    class="relative"
    style="height: {{ $height }}; width: {{ $width }};"
>
    <canvas x-ref="canvas"></canvas>
</div>
