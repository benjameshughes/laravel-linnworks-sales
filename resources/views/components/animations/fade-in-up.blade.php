@props(['delay' => 0])

<div
    x-data="{ show: false }"
    x-init="setTimeout(() => show = true, {{ $delay }})"
    x-show="show"
    x-transition:enter="transition ease-out duration-500"
    x-transition:enter-start="opacity-0 transform translate-y-4"
    x-transition:enter-end="opacity-100 transform translate-y-0"
    {{ $attributes }}
>
    {{ $slot }}
</div>
