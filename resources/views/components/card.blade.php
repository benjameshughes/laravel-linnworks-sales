@props([
    'padding' => 'p-6',
    'shadow' => 'shadow-sm',
    'border' => 'border border-gray-200 dark:border-gray-700',
    'background' => 'bg-white dark:bg-gray-900',
    'rounded' => 'rounded-lg'
])

<div {{ $attributes->merge([
    'class' => "{$background} {$border} {$rounded} {$shadow} {$padding}"
]) }}>
    {{ $slot }}
</div>