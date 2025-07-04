@props([
    'class' => ''
])

<thead {{ $attributes->merge(['class' => "bg-gray-50 dark:bg-gray-800 {$class}"]) }}>
    {{ $slot }}
</thead>