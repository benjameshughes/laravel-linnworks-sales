<section class="w-full">
    @include('partials.settings-heading')

    <x-settings.layout :heading="__('Import Orders')" :subheading="__('Import historical orders from Linnworks')">
        <div class="my-6 w-full space-y-6">
            {{-- Import Controls --}}
            <div class="flex items-end gap-4">
                <flux:date-picker
                    wire:model.live="dateRange"
                    mode="range"
                    with-presets
                >
                    <x-slot name="trigger">
                        <flux:date-picker.input label="From" />
                        <flux:date-picker.input label="To" />
                    </x-slot>
                </flux:date-picker>

                <flux:button
                    variant="primary"
                    wire:click="startImport"
                    :disabled="$this->activeSync !== null"
                    icon="arrow-down-tray"
                >
                    {{ $this->activeSync ? 'Importing...' : 'Start Import' }}
                </flux:button>
            </div>

            {{-- Import History Table --}}
            @if (count($this->imports) > 0)
                <div class="overflow-x-auto -mx-6 px-6">
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>Status</flux:table.column>
                        <flux:table.column>Date Range</flux:table.column>
                        <flux:table.column>Started</flux:table.column>
                        <flux:table.column align="end">Processed</flux:table.column>
                        <flux:table.column align="end">Created</flux:table.column>
                        <flux:table.column align="end">Updated</flux:table.column>
                        <flux:table.column align="end">Failed</flux:table.column>
                        <flux:table.column align="end">Duration</flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @foreach ($this->imports as $import)
                            {{-- Active import row --}}
                            @if ($import['is_active'])
                                <flux:table.row>
                                    <flux:table.cell>
                                        <flux:badge size="sm" color="blue">
                                            {{ number_format($import['progress']['percentage'] ?? 0, 0) }}%
                                        </flux:badge>
                                    </flux:table.cell>
                                    <flux:table.cell class="text-zinc-600 dark:text-zinc-400">
                                        {{ $import['date_range'] ?? '-' }}
                                    </flux:table.cell>
                                    <flux:table.cell>{{ $import['started_at'] }}</flux:table.cell>
                                    <flux:table.cell align="end" class="font-semibold text-blue-600 dark:text-blue-400">
                                        {{ number_format($import['progress']['processed'] ?? 0) }}
                                    </flux:table.cell>
                                    <flux:table.cell align="end" class="text-emerald-600 dark:text-emerald-400">
                                        {{ number_format($import['stats']['created']) }}
                                    </flux:table.cell>
                                    <flux:table.cell align="end" class="text-amber-600 dark:text-amber-400">
                                        {{ number_format($import['stats']['updated']) }}
                                    </flux:table.cell>
                                    <flux:table.cell align="end" class="{{ $import['stats']['failed'] > 0 ? 'text-red-600 dark:text-red-400' : 'text-zinc-500' }}">
                                        {{ number_format($import['stats']['failed']) }}
                                    </flux:table.cell>
                                    <flux:table.cell align="end" class="text-zinc-500">
                                        @if (($import['progress']['speed'] ?? 0) > 0)
                                            {{ number_format($import['progress']['speed'], 1) }}/s
                                        @else
                                            -
                                        @endif
                                    </flux:table.cell>
                                </flux:table.row>
                            @else
                                {{-- Completed/failed import row --}}
                                <flux:table.row>
                                    <flux:table.cell>
                                        <flux:badge size="sm" :color="$import['status_color']">
                                            {{ $import['status_label'] }}
                                        </flux:badge>
                                    </flux:table.cell>
                                    <flux:table.cell class="text-zinc-600 dark:text-zinc-400">
                                        {{ $import['date_range'] ?? '-' }}
                                    </flux:table.cell>
                                    <flux:table.cell>{{ $import['started_at'] }}</flux:table.cell>
                                    <flux:table.cell align="end" variant="strong">
                                        {{ number_format($import['stats']['processed']) }}
                                    </flux:table.cell>
                                    <flux:table.cell align="end" class="text-emerald-600 dark:text-emerald-400">
                                        {{ number_format($import['stats']['created']) }}
                                    </flux:table.cell>
                                    <flux:table.cell align="end" class="text-amber-600 dark:text-amber-400">
                                        {{ number_format($import['stats']['updated']) }}
                                    </flux:table.cell>
                                    <flux:table.cell align="end" class="{{ $import['stats']['failed'] > 0 ? 'text-red-600 dark:text-red-400' : 'text-zinc-500' }}">
                                        {{ number_format($import['stats']['failed']) }}
                                    </flux:table.cell>
                                    <flux:table.cell align="end" class="text-zinc-500">
                                        {{ $import['duration'] ?? '-' }}
                                    </flux:table.cell>
                                </flux:table.row>

                                {{-- Error message row --}}
                                @if ($import['error_message'])
                                    <flux:table.row class="bg-red-50 dark:bg-red-900/10">
                                        <flux:table.cell colspan="8" class="text-red-700 dark:text-red-400 text-sm">
                                            <span class="font-medium">Error:</span> {{ Str::limit($import['error_message'], 200) }}
                                        </flux:table.cell>
                                    </flux:table.row>
                                @endif
                            @endif
                        @endforeach
                    </flux:table.rows>
                </flux:table>
                </div>
            @else
                <div class="text-center py-12 text-zinc-500">
                    <flux:icon name="inbox" class="size-12 mx-auto mb-4 text-zinc-300" />
                    <p>No imports yet. Select a date range and click Start Import.</p>
                </div>
            @endif
        </div>
    </x-settings.layout>
</section>
