@props(['index' => 0, 'baseDelay' => 50])

@php
    $delay = $baseDelay * $index;
@endphp

<div
    x-data="{ show: false }"
    x-init="setTimeout(() => show = true, {{ $delay }})"
    x-show="show"
    x-transition:enter="transition ease-out duration-300"
    x-transition:enter-start="opacity-0 transform translate-x-2"
    x-transition:enter-end="opacity-100 transform translate-x-0"
    {{ $attributes }}
>
    {{ $slot }}
</div>
