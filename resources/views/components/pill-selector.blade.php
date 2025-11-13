@props([
    'options' => [],
    'selected' => [],
    'label' => null,
    'placeholder' => 'Select options',
    'wireModel' => null,
    'multiple' => true,
    'toggleMethod' => 'toggleChannel',
    'clearMethod' => 'clearFilters',
])

<div {{ $attributes->class(['items-center text-sm font-medium [:where(&)]:text-zinc-800 [:where(&)]:dark:text-white w-full text-zinc-500 dark:text-zinc-400']) }}>
    @if($label)
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
            {{ $label }}
            @if($multiple && count($selected) > 0)
                <span class="text-gray-500 dark:text-gray-400">({{ count($selected) }} selected)</span>
            @endif
        </label>
    @endif

    <div class="relative" x-data="{ open: false }">
        {{-- Trigger Button --}}
        <button
            type="button"
            @click="open = !open"
            @click.away="open = false"
            class="w-full border rounded-lg flex justify-between disabled:shadow-none dark:shadow-none appearance-none text-base sm:text-sm py-2 h-10 leading-[1.375rem] ps-3 pe-3 bg-white dark:bg-white/10 dark:disabled:bg-white/[7%] text-zinc-700 disabled:text-zinc-500 placeholder-zinc-400 disabled:placeholder-zinc-400/70 dark:text-zinc-300 dark:disabled:text-zinc-400 dark:placeholder-zinc-400 dark:disabled:placeholder-zinc-500 shadow-xs border-zinc-200 border-b-zinc-300/80 disabled:border-b-zinc-200 dark:border-white/10 dark:disabled:border-white/5"
        >
            <span class="text-sm text-gray-700 dark:text-gray-300">
                @if(count($selected) === 0)
                    {{ $placeholder }}
                @elseif(count($selected) === 1)
                    {{ is_array($options) ? collect($options)->firstWhere('value', $selected[0])['label'] ?? $selected[0] : $selected[0] }}
                @else
                    {{ count($selected) }} selected
                @endif
            </span>
            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
            </svg>
        </button>

        {{-- Dropdown --}}
        <div
            x-show="open"
            x-transition:enter="transition ease-out duration-100"
            x-transition:enter-start="transform opacity-0 scale-95"
            x-transition:enter-end="transform opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-75"
            x-transition:leave-start="transform opacity-100 scale-100"
            x-transition:leave-end="transform opacity-0 scale-95"
            class="absolute z-50 mt-2 w-full bg-white dark:bg-zinc-900 border border-gray-200 dark:border-zinc-700 rounded-lg shadow-lg max-h-60 overflow-auto"
            style="display: none;"
        >
            <div class="p-2 space-y-1">
                @foreach($options as $option)
                    @php
                        $value = is_array($option) ? $option['value'] : $option;
                        $label = is_array($option) ? $option['label'] : $option;
                        $isSelected = in_array($value, $selected);
                    @endphp
                    <button
                        type="button"
                        wire:click="{{ $toggleMethod }}('{{ $value }}')"
                        class="w-full flex items-center justify-between px-3 py-2 rounded-md hover:bg-gray-100 dark:hover:bg-zinc-800 transition-colors {{ $isSelected ? 'bg-blue-50 dark:bg-blue-900/20' : '' }}"
                    >
                        <span class="text-sm {{ $isSelected ? 'text-blue-700 dark:text-blue-400 font-medium' : 'text-gray-700 dark:text-gray-300' }}">
                            {{ $label }}
                        </span>
                        @if($isSelected)
                            <svg class="w-5 h-5 text-blue-600 dark:text-blue-400" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                            </svg>
                        @endif
                    </button>
                @endforeach
            </div>
        </div>
    </div>

    {{-- Selected Pills --}}
    @if(count($selected) > 0)
        <div class="flex flex-wrap gap-2 mt-3">
            @foreach($selected as $value)
                @php
                    $label = is_array($options)
                        ? collect($options)->firstWhere('value', $value)['label'] ?? $value
                        : $value;
                @endphp
                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-sm font-medium bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300 border border-blue-200 dark:border-blue-800">
                    {{ $label }}
                    <button
                        type="button"
                        wire:click="{{ $toggleMethod }}('{{ $value }}')"
                        class="hover:bg-blue-200 dark:hover:bg-blue-800 rounded-full p-0.5 transition-colors"
                    >
                        <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                        </svg>
                    </button>
                </span>
            @endforeach

            @if(count($selected) > 1)
                <button
                    type="button"
                    wire:click="{{ $clearMethod }}"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-sm font-medium text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-zinc-800 transition-colors"
                >
                    Clear all
                </button>
            @endif
        </div>
    @endif
</div>
