@props(['enabled' => true])

<div
    @if($enabled)
    x-data="{ hover: false }"
    @mouseenter="hover = true"
    @mouseleave="hover = false"
    :class="hover ? '-translate-y-1 shadow-lg' : 'translate-y-0 shadow'"
    class="transition-all duration-200 ease-out"
    @endif
    {{ $attributes }}
>
    {{ $slot }}
</div>
