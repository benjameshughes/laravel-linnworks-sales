@props([
    'type' => 'line',
    'data' => [],
    'options' => [],
    'class' => 'h-64'
])

<div
    x-data="{
        init() {
            let existing = Chart.getChart(this.$refs.canvas);
            if (existing) existing.destroy();

            new Chart(this.$refs.canvas, {
                type: '{{ $type }}',
                data: {{ Js::from($data) }},
                options: Object.assign({
                    responsive: true,
                    maintainAspectRatio: false
                }, {{ Js::from($options) }})
            });
        }
    }"
    {{ $attributes->merge(['class' => $class]) }}
>
    <canvas x-ref="canvas"></canvas>
</div>
