@props([
    'class' => ''
])

<td {{ $attributes->merge(['class' => "px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white {$class}"]) }}>
    {{ $slot }}
</td>