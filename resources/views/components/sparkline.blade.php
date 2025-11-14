@props([
    'data' => [],
    'color' => '#3B82F6',
    'height' => '40px',
    'width' => '100px',
    'showValue' => true,
    'label' => null,
    'value' => null,
    'trend' => null, // 'up', 'down', or null
    'class' => '',
])

@php
    $chartData = [
        'labels' => array_keys($data),
        'datasets' => [
            [
                'data' => array_values($data),
                'borderColor' => $color,
                'backgroundColor' => 'transparent',
                'fill' => false,
            ]
        ]
    ];
@endphp

<div {{ $attributes->merge(['class' => 'flex items-center gap-3 ' . $class]) }}>
    @if($showValue || $label)
        <div class="flex-1">
            @if($label)
                <div class="text-xs text-zinc-600 dark:text-zinc-400">{{ $label }}</div>
            @endif
            @if($showValue && $value)
                <div class="flex items-center gap-2">
                    <div class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">{{ $value }}</div>
                    @if($trend)
                        <div class="flex items-center">
                            @if($trend === 'up')
                                <flux:icon name="arrow-trending-up" class="size-4 text-green-500" />
                            @elseif($trend === 'down')
                                <flux:icon name="arrow-trending-down" class="size-4 text-red-500" />
                            @endif
                        </div>
                    @endif
                </div>
            @endif
        </div>
    @endif
    
    <div style="width: {{ $width }}; height: {{ $height }};">
        <livewire:chart
            type="line"
            :data="$chartData"
            :height="$height"
            :width="$width"
        />
    </div>
</div>