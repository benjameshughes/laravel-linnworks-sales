{{-- Animated Theme Switcher with Sliding Indicator --}}
<div
    x-data="{
        selected: $flux.appearance,
        positions: { light: 0, dark: 1, system: 2 },
        init() {
            this.$watch('$flux.appearance', value => {
                this.selected = value
            })
        },
        getTransform() {
            const position = this.positions[this.selected] || 0
            return `translateX(${position * 100}%)`
        }
    }"
    class="relative"
    {{ $attributes }}
>
    {{-- Sliding Background Indicator --}}
    <div
        class="absolute inset-0 grid grid-cols-3 pointer-events-none"
        x-cloak
    >
        <div
            class="transition-transform duration-300 ease-out"
            :style="`transform: ${getTransform()}`"
        >
            <div class="h-full bg-blue-100 dark:bg-blue-900/30 rounded-lg m-0.5 shadow-sm"></div>
        </div>
    </div>

    {{-- Radio Group --}}
    <flux:radio.group variant="segmented" x-model="$flux.appearance" class="relative z-10">
        <flux:radio value="light" icon="sun" class="flex-1">{{ __('Light') }}</flux:radio>
        <flux:radio value="dark" icon="moon" class="flex-1">{{ __('Dark') }}</flux:radio>
        <flux:radio value="system" icon="computer-desktop" class="flex-1">{{ __('System') }}</flux:radio>
    </flux:radio.group>
</div>
