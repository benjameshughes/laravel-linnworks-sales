@props([
    'sortable' => false,
    'sorted' => false,
    'direction' => 'asc',
    'class' => ''
])

<th {{ $attributes->merge(['class' => "px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider {$class}"]) }}>
    @if($sortable)
        <button class="flex items-center space-x-1 hover:text-gray-700 dark:hover:text-gray-300">
            <span>{{ $slot }}</span>
            @if($sorted)
                <flux:icon.chevron-up class="size-4 {{ $direction === 'desc' ? 'rotate-180' : '' }}" />
            @endif
        </button>
    @else
        {{ $slot }}
    @endif
</th>