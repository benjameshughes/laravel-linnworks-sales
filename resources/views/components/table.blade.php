@props([
    'class' => ''
])

<div {{ $attributes->merge(['class' => "overflow-hidden border border-gray-200 dark:border-gray-700 rounded-lg bg-white dark:bg-gray-900 {$class}"]) }}>
    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
        {{ $slot }}
    </table>
</div>