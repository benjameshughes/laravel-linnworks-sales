@props([
    'class' => ''
])

<tbody {{ $attributes->merge(['class' => "bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700 {$class}"]) }}>
    {{ $slot }}
</tbody>