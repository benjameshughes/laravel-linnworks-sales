@props([
    'class' => ''
])

<tr {{ $attributes->merge(['class' => "border-b border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800/50 {$class}"]) }}>
    {{ $slot }}
</tr>