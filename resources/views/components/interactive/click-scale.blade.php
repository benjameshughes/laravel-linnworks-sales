@props(['enabled' => true])

<div
    @if($enabled)
    x-data="{ clicked: false }"
    @click="clicked = true; setTimeout(() => clicked = false, 150)"
    :class="clicked ? 'scale-95' : 'scale-100'"
    class="transition-transform duration-150 ease-out active:scale-95"
    @endif
    {{ $attributes }}
>
    {{ $slot }}
</div>
