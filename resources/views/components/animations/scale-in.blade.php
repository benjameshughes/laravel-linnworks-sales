@props(['delay' => 0])

<div
    x-data="{ show: false }"
    x-init="setTimeout(() => show = true, {{ $delay }})"
    x-show="show"
    x-transition:enter="transition ease-out duration-400"
    x-transition:enter-start="opacity-0 transform scale-95"
    x-transition:enter-end="opacity-100 transform scale-100"
    {{ $attributes }}
>
    {{ $slot }}
</div>
