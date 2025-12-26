<div class="flex items-center justify-between gap-4 pb-4 border-b border-zinc-200 dark:border-zinc-700">
    <div class="flex items-center gap-2">
        <flux:heading size="xl" class="text-zinc-900 dark:text-zinc-100">Orders</flux:heading>

        {{-- Date Picker with custom trigger --}}
        <flux:date-picker
            wire:model.live="dateRange"
            wire:change="applyCustomRange"
            mode="range"
            with-presets
            with-inputs
            selectable-header
            max="{{ now()->format('Y-m-d') }}"
        >
            <x-slot name="trigger">
                <flux:button variant="ghost" size="sm" class="gap-1.5 font-normal text-zinc-500 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100">
                    <flux:icon name="calendar-days" variant="outline" class="size-4" />
                    <span>{{ $this->formattedDateRange }}</span>
                    @if($period === 'custom')
                        <flux:badge color="blue" size="sm">Custom</flux:badge>
                    @endif
                </flux:button>
            </x-slot>
        </flux:date-picker>
    </div>

    {{-- Inline Filter Bar --}}
    <div class="flex items-center gap-2">
        <flux:input
            wire:model.live.debounce.300ms="search"
            placeholder="Search..."
            size="sm"
            icon="magnifying-glass"
            class="w-32"
        />

        <flux:select wire:model.live="channel" size="sm" class="!w-auto">
            @foreach($this->channels as $value => $label)
                <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:select wire:model.live="status" size="sm" class="!w-auto">
            <flux:select.option value="all">All</flux:select.option>
            <flux:select.option value="open">Open</flux:select.option>
            <flux:select.option value="paid">Paid</flux:select.option>
        </flux:select>

        <flux:button
            wire:click="refresh"
            variant="ghost"
            size="sm"
            icon="arrow-path"
            wire:loading.attr="disabled"
            wire:target="refresh"
        />
    </div>
</div>
