@props(['growth'])

<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1 text-xs font-medium '.$growth->toneClass()]) }}>
    <flux:icon :name="$growth->icon()" class="size-3.5 shrink-0" />
    {{ $growth->label() }}
</span>
