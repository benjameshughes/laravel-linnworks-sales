@props([
    'type' => 'line',
    'data' => [],
    'options' => [],
    'class' => 'h-64'
])

<div
    x-data="{ chart: null }"
    x-init="
        chart = new Chart($refs.canvas, {
            type: '{{ $type }}',
            data: {{ Js::from($data) }},
            options: Object.assign({
                responsive: true,
                maintainAspectRatio: false
            }, {{ Js::from($options) }})
        })
    "
    {{ $attributes->merge(['class' => $class]) }}
>
    <canvas x-ref="canvas"></canvas>
</div>
