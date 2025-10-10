@props(['options' => []])

<div
    x-data="{
        selected: $flux.appearance,
        getTransform() {
            const index = this.options.indexOf(this.selected)
            return index >= 0 ? index * 100 : 0
        },
        options: {{ json_encode(array_column($options, 'value')) }}
    }"
    x-init="$watch('$flux.appearance', value => selected = value)"
    class="relative inline-flex w-full bg-zinc-100 dark:bg-zinc-800 rounded-lg p-1"
    role="radiogroup"
    {{ $attributes }}
>
    {{-- Sliding Indicator --}}
    <div
        class="absolute inset-1 grid grid-cols-{{ count($options) }} pointer-events-none"
        aria-hidden="true"
    >
        <div
            class="transition-transform duration-300 ease-out"
            :style="`transform: translateX(${getTransform()}%)`"
        >
            <div class="h-full bg-white dark:bg-zinc-700 rounded-md shadow-sm"></div>
        </div>
    </div>

    {{-- Radio Options --}}
    @foreach($options as $option)
        <label
            class="relative z-10 flex-1 flex items-center justify-center gap-2 px-3 py-2 cursor-pointer transition-colors duration-200 rounded-md"
            :class="selected === '{{ $option['value'] }}' ? 'text-zinc-900 dark:text-zinc-100' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-200'"
        >
            <input
                type="radio"
                name="appearance"
                value="{{ $option['value'] }}"
                x-model="$flux.appearance"
                class="sr-only"
            >

            @if(isset($option['icon']))
                <flux:icon.{{ $option['icon'] }} class="size-4" />
            @endif

            <span class="text-sm font-medium">{{ $option['label'] }}</span>
        </label>
    @endforeach>
</div>
