@props([
    'data' => [],
    'options' => [],
    'height' => '300px',
    'width' => '100%',
    'title' => null,
    'class' => '',
])

<div {{ $attributes->merge(['class' => 'bg-white dark:bg-zinc-800 rounded-xl shadow-sm border border-zinc-200 dark:border-zinc-700 p-6 ' . $class]) }}>
    @if($title)
        <h3 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100 mb-4">{{ $title }}</h3>
    @endif
    
    <livewire:charts.line-chart 
        :data="$data" 
        :options="$options" 
        :height="$height" 
        :width="$width" 
    />
</div>