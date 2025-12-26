<div class="space-y-4">
    {{-- Header with Controls --}}
    <div class="bg-white dark:bg-zinc-800 rounded-xl shadow-sm border border-zinc-200 dark:border-zinc-700 p-4">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
            <div class="flex items-center gap-6">
                <div>
                    <flux:heading size="lg" class="text-zinc-900 dark:text-zinc-100">Order Analytics</flux:heading>
                    <div class="flex items-center gap-2 text-sm text-zinc-600 dark:text-zinc-400 mt-1">
                        <span>{{ $this->periodLabel }}</span>
                    </div>
                </div>
            </div>

            {{-- Controls --}}
            <div class="flex flex-wrap items-center gap-3">
                {{-- Search Input --}}
                <div class="relative min-w-64">
                    <flux:input
                        wire:model.live.debounce.300ms="search"
                        placeholder="Search order number..."
                        size="sm"
                        icon="magnifying-glass"
                    />
                    @if($search)
                        <flux:button
                            variant="ghost"
                            size="sm"
                            wire:click="clearSearch"
                            icon="x-mark"
                            class="absolute right-2 top-1/2 transform -translate-y-1/2"
                        />
                    @endif
                </div>

                {{-- Period Selector --}}
                <flux:select wire:model.live="period" size="sm" class="min-w-32">
                    @foreach($this->periods as $periodOption)
                        <flux:select.option value="{{ $periodOption['value'] }}">
                            {{ $periodOption['label'] }}
                        </flux:select.option>
                    @endforeach
                </flux:select>

                {{-- Channel Filter --}}
                <flux:select wire:model.live="channel" size="sm" class="min-w-40">
                    @foreach($this->channels as $value => $label)
                        <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
                    @endforeach
                </flux:select>

                {{-- Status Filter --}}
                <flux:select wire:model.live="status" size="sm" class="min-w-28">
                    <flux:select.option value="all">All Status</flux:select.option>
                    <flux:select.option value="open">Open</flux:select.option>
                    <flux:select.option value="paid">Paid</flux:select.option>
                </flux:select>

                {{-- Refresh Button --}}
                <flux:button
                    wire:click="refresh"
                    variant="ghost"
                    size="sm"
                    icon="arrow-path"
                    wire:loading.attr="disabled"
                    wire:loading.class="animate-spin"
                    wire:target="refresh"
                />
            </div>
        </div>

        {{-- Custom Date Range (collapsible) --}}
        @if($showCustomRange || $period === 'custom')
            <div class="mt-4 pt-4 border-t border-zinc-200 dark:border-zinc-700">
                <div class="flex flex-wrap items-center gap-3">
                    <flux:input
                        wire:model="customFrom"
                        type="date"
                        size="sm"
                        label="From"
                    />
                    <flux:input
                        wire:model="customTo"
                        type="date"
                        size="sm"
                        label="To"
                    />
                    <flux:button
                        wire:click="applyCustomRange"
                        variant="primary"
                        size="sm"
                    >
                        Apply Range
                    </flux:button>
                    <flux:button
                        wire:click="clearCustomRange"
                        variant="ghost"
                        size="sm"
                    >
                        Clear
                    </flux:button>
                </div>
            </div>
        @endif
    </div>
</div>
