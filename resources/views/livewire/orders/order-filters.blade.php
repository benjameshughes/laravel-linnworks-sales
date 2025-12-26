<div class="flex items-center justify-between gap-4 pb-4 border-b border-zinc-200 dark:border-zinc-700">
    <div class="flex items-center gap-2">
        <flux:heading size="xl" class="text-zinc-900 dark:text-zinc-100">Orders</flux:heading>
        <span class="text-sm text-zinc-400">{{ $this->periodLabel }}</span>
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

        <flux:select wire:model.live="period" size="sm" class="!w-auto">
            @foreach($this->periods as $periodOption)
                <flux:select.option value="{{ $periodOption['value'] }}">
                    {{ $periodOption['label'] }}
                </flux:select.option>
            @endforeach
        </flux:select>

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

{{-- Custom Date Range --}}
@if($showCustomRange || $period === 'custom')
    <div class="flex items-end gap-2 pt-3">
        <flux:input wire:model="customFrom" type="date" size="sm" label="From" class="w-32" />
        <flux:input wire:model="customTo" type="date" size="sm" label="To" class="w-32" />
        <flux:button wire:click="applyCustomRange" variant="primary" size="sm">Apply</flux:button>
        <flux:button wire:click="clearCustomRange" variant="ghost" size="sm">Clear</flux:button>
    </div>
@endif
