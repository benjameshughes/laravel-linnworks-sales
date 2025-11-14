@props([
    'type' => 'line', // line, bar, doughnut, pie, area, mixed
    'data' => [],
    'options' => [],
    'height' => '300px',
    'width' => '100%',
    'title' => null,
    'subtitle' => null,
    'icon' => null,
    'class' => '',
    'containerClass' => '',
    'actions' => null, // Slot for action buttons
    'chartKey' => null, // Unique key for forcing chart re-render
])

<div {{ $attributes->merge(['class' => 'bg-white dark:bg-zinc-800 rounded-xl shadow-sm border border-zinc-200 dark:border-zinc-700 ' . $containerClass]) }}>
    @if($title || $subtitle || $actions)
        <div class="p-6 pb-4 border-b border-zinc-200 dark:border-zinc-700">
            <div class="flex items-center justify-between">
                <div>
                    @if($title)
                        <div class="flex items-center gap-3">
                            @if($icon)
                                <flux:icon :name="$icon" class="size-5 text-zinc-500 dark:text-zinc-400" />
                            @endif
                            <h3 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">{{ $title }}</h3>
                        </div>
                    @endif
                    @if($subtitle)
                        <p class="text-sm text-zinc-600 dark:text-zinc-400 mt-1">{{ $subtitle }}</p>
                    @endif
                </div>
                @if($actions)
                    <div class="flex items-center gap-2">
                        {{ $actions }}
                    </div>
                @endif
            </div>
        </div>
    @endif
    
    <div class="p-6 {{ $class }}">
        <livewire:chart
            :type="$type"
            :data="$data"
            :options="$options"
            :height="$height"
            :width="$width"
        />
    </div>
    
    {{ $slot }}
</div>