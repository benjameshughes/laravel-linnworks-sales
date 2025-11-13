@props([
    'filter',
    'value' => null,
    'availableOptions' => [],
    'componentId' => null,
])

@php
    $filterName = $filter->name();
    $filterType = $filter->type();
    $filterLabel = $filter->label();
    $componentId = $componentId ?? 'filter-' . $filterName;
@endphp

{{-- Date Range Filter --}}
@if($filterType === 'date_range')
    <flux:field>
        <flux:label>{{ $filterLabel }}</flux:label>
        <div class="flex items-center gap-2">
            <flux:input
                type="date"
                wire:model.live.debounce.500ms="filters.{{ $filterName }}.start"
                class="flex-1"
            />
            <span class="text-zinc-400 dark:text-zinc-500">to</span>
            <flux:input
                type="date"
                wire:model.live.debounce.500ms="filters.{{ $filterName }}.end"
                class="flex-1"
            />
        </div>
    </flux:field>

{{-- Multi-Select Filter (Pills) --}}
@elseif($filterType === 'multi_select')
    <div>
        @php
            // For dynamic options (SKUs, subsources, channels), use availableOptions
            // For static options (statuses), use filter->options()
            $options = !empty($availableOptions)
                ? collect($availableOptions)->map(fn($opt) => ['value' => $opt, 'label' => $opt])->toArray()
                : collect($filter->options())->map(fn($opt) => ['value' => $opt, 'label' => ucfirst($opt)])->toArray();

            $selected = is_array($value) ? $value : [];
            $placeholder = $filter->placeholder() ?? 'All ' . $filterLabel . ' (' . count($options) . ')';

            // Map filter names to their specific toggle methods
            // For backwards compatibility with existing hardcoded methods
            $toggleMethodMap = [
                'skus' => 'toggleSku',
                'subsources' => 'toggleSubsource',
            ];

            $toggleMethod = $toggleMethodMap[$filterName] ?? 'toggleFilterValue';
        @endphp

        @if(isset($toggleMethodMap[$filterName]))
            {{-- Use existing hardcoded methods for SKUs and subsources --}}
            <x-pill-selector
                :options="$options"
                :selected="$selected"
                :label="$filterLabel"
                :placeholder="$placeholder"
                :toggle-method="$toggleMethod"
                clear-method="clearFilters"
            />
        @else
            {{-- For new filter types like statuses, render manually --}}
            <div class="items-center text-sm font-medium [:where(&)]:text-zinc-800 [:where(&)]:dark:text-white w-full text-zinc-500 dark:text-zinc-400">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                    {{ $filterLabel }}
                    @if(count($selected) > 0)
                        <span class="text-gray-500 dark:text-gray-400">({{ count($selected) }} selected)</span>
                    @endif
                </label>

                <div class="relative" x-data="{ open: false }">
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
                                {{ collect($options)->firstWhere('value', $selected[0])['label'] ?? $selected[0] }}
                            @else
                                {{ count($selected) }} selected
                            @endif
                        </span>
                        <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </button>

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
                                    $optionValue = $option['value'];
                                    $optionLabel = $option['label'];
                                    $isSelected = in_array($optionValue, $selected);
                                @endphp
                                <button
                                    type="button"
                                    wire:click="toggleFilterValue('{{ $filterName }}', '{{ $optionValue }}')"
                                    class="w-full flex items-center justify-between px-3 py-2 rounded-md hover:bg-gray-100 dark:hover:bg-zinc-800 transition-colors {{ $isSelected ? 'bg-blue-50 dark:bg-blue-900/20' : '' }}"
                                >
                                    <span class="text-sm {{ $isSelected ? 'text-blue-700 dark:text-blue-400 font-medium' : 'text-gray-700 dark:text-gray-300' }}">
                                        {{ $optionLabel }}
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

                @if(count($selected) > 0)
                    <div class="flex flex-wrap gap-2 mt-3">
                        @foreach($selected as $selectedValue)
                            @php
                                $selectedLabel = collect($options)->firstWhere('value', $selectedValue)['label'] ?? $selectedValue;
                            @endphp
                            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-sm font-medium bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300 border border-blue-200 dark:border-blue-800">
                                {{ $selectedLabel }}
                                <button
                                    type="button"
                                    wire:click="toggleFilterValue('{{ $filterName }}', '{{ $selectedValue }}')"
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
                                wire:click="clearFilterValues('{{ $filterName }}')"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-sm font-medium text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-zinc-800 transition-colors"
                            >
                                Clear all
                            </button>
                        @endif
                    </div>
                @endif
            </div>
        @endif
    </div>

{{-- Single Select Dropdown --}}
@elseif($filterType === 'select')
    <flux:field>
        <flux:label>{{ $filterLabel }}</flux:label>
        <flux:select wire:model.live="filters.{{ $filterName }}">
            <flux:select.option value="">{{ $filter->placeholder() ?? 'Select ' . $filterLabel }}</flux:select.option>
            @foreach($filter->options() as $optionValue => $optionLabel)
                <flux:select.option value="{{ is_numeric($optionValue) ? $optionLabel : $optionValue }}">
                    {{ is_numeric($optionValue) ? ucfirst($optionLabel) : $optionLabel }}
                </flux:select.option>
            @endforeach
        </flux:select>
    </flux:field>

{{-- Text Input --}}
@elseif($filterType === 'text')
    <flux:field>
        <flux:label>{{ $filterLabel }}</flux:label>
        <flux:input
            type="text"
            wire:model.live.debounce.500ms="filters.{{ $filterName }}"
            placeholder="{{ $filter->placeholder() ?? '' }}"
        />
        @if($filter->helpText())
            <flux:description>{{ $filter->helpText() }}</flux:description>
        @endif
    </flux:field>

{{-- Search Input --}}
@elseif($filterType === 'search')
    <flux:field>
        <flux:label>{{ $filterLabel }}</flux:label>
        <flux:input
            type="search"
            wire:model.live.debounce.500ms="filters.{{ $filterName }}"
            placeholder="{{ $filter->placeholder() ?? 'Search...' }}"
        />
        @if($filter->helpText())
            <flux:description>{{ $filter->helpText() }}</flux:description>
        @endif
    </flux:field>

{{-- Number Range Filter --}}
@elseif($filterType === 'number_range')
    <flux:field>
        <flux:label>{{ $filterLabel }}</flux:label>
        <div class="flex items-center gap-2">
            <flux:input
                type="number"
                wire:model.live.debounce.500ms="filters.{{ $filterName }}.min"
                placeholder="{{ $filter->options()['min'] ?? 'Min' }}"
                class="flex-1"
            />
            <span class="text-zinc-400 dark:text-zinc-500">to</span>
            <flux:input
                type="number"
                wire:model.live.debounce.500ms="filters.{{ $filterName }}.max"
                placeholder="{{ $filter->options()['max'] ?? 'Max' }}"
                class="flex-1"
            />
        </div>
        @if($filter->helpText())
            <flux:description>{{ $filter->helpText() }}</flux:description>
        @endif
    </flux:field>

{{-- Toggle/Switch --}}
@elseif($filterType === 'toggle')
    <flux:field>
        <div class="flex items-center justify-between">
            <flux:label>{{ $filterLabel }}</flux:label>
            <flux:switch wire:model.live="filters.{{ $filterName }}" />
        </div>
        @if($filter->helpText())
            <flux:description>{{ $filter->helpText() }}</flux:description>
        @endif
    </flux:field>

{{-- Checkbox --}}
@elseif($filterType === 'checkbox')
    <flux:field>
        <flux:checkbox wire:model.live="filters.{{ $filterName }}">
            {{ $filterLabel }}
        </flux:checkbox>
        @if($filter->helpText())
            <flux:description>{{ $filter->helpText() }}</flux:description>
        @endif
    </flux:field>

{{-- Fallback for unknown filter types --}}
@else
    <div class="p-4 bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-700 rounded-lg">
        <p class="text-sm text-yellow-800 dark:text-yellow-200">
            Unknown filter type: <code class="font-mono">{{ $filterType }}</code>
        </p>
    </div>
@endif