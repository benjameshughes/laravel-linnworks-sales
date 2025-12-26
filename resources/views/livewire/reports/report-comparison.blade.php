<div class="min-h-screen">
    <div class="space-y-3 p-3 lg:p-4">
        {{-- Page Header --}}
        <div class="flex items-center justify-between gap-4 pb-4 border-b border-zinc-200 dark:border-zinc-700">
            <div class="flex items-center gap-2">
                <flux:heading size="xl" class="text-zinc-900 dark:text-zinc-100">Report Comparison</flux:heading>
            </div>
            <flux:button variant="ghost" size="sm" wire:navigate href="/reports" icon="arrow-left">
                Back to Reports
            </flux:button>
        </div>

        {{-- Comparison Grid --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-3">
            {{-- Report A --}}
            <div class="bg-white dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700">
                <div class="p-3 border-b border-zinc-200 dark:border-zinc-700">
                    <div class="space-y-3">
                        <div class="flex items-center gap-2">
                            <div class="w-6 h-6 rounded-full bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center">
                                <span class="text-xs font-bold text-blue-600 dark:text-blue-400">A</span>
                            </div>
                            <span class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Report A</span>
                        </div>
                        <flux:select wire:model.live="reportClassA" size="sm">
                            @foreach($this->availableReports as $report)
                                <flux:select.option value="{{ get_class($report) }}">
                                    {{ $report->name() }}
                                </flux:select.option>
                            @endforeach
                        </flux:select>

                        @if($this->reportA)
                            <p class="text-xs text-zinc-500">
                                {{ $this->reportA->description() }}
                            </p>
                        @endif
                    </div>
                </div>

                @if($this->reportA)
                    <div class="p-3">
                        <div class="space-y-3">
                            @if(isset($filtersA['date_range']))
                                <div>
                                    <label class="text-xs font-medium text-zinc-500 uppercase tracking-wide mb-1.5 block">Date Range</label>
                                    <div class="flex items-center gap-2">
                                        <flux:input
                                            type="date"
                                            wire:model="filtersA.date_range.start"
                                            size="sm"
                                            class="flex-1"
                                        />
                                        <span class="text-zinc-400 text-sm">to</span>
                                        <flux:input
                                            type="date"
                                            wire:model="filtersA.date_range.end"
                                            size="sm"
                                            class="flex-1"
                                        />
                                    </div>
                                </div>
                            @endif

                            <div class="flex gap-2">
                                <flux:button wire:click="applyFiltersA" size="sm" class="flex-1" icon="funnel">
                                    Apply Filters
                                </flux:button>
                                <flux:button wire:click="resetFiltersA" variant="ghost" size="sm" icon="arrow-path">
                                    Reset
                                </flux:button>
                            </div>

                            @if($previewDataA)
                                <div class="pt-3 border-t border-zinc-200 dark:border-zinc-700">
                                    <p class="text-xs text-zinc-500 mb-2">
                                        Showing {{ $previewDataA->count() }} of {{ number_format($totalRowsA ?? 0) }} rows
                                    </p>
                                    <flux:table>
                                        <flux:table.columns>
                                            @foreach($this->reportA->columns() as $column)
                                                <flux:table.column>{{ $column['label'] }}</flux:table.column>
                                            @endforeach
                                        </flux:table.columns>

                                        <flux:table.rows>
                                            @foreach($previewDataA as $row)
                                                <flux:table.row>
                                                    @foreach(array_keys($this->reportA->columns()) as $key)
                                                        <flux:table.cell>{{ $row->$key }}</flux:table.cell>
                                                    @endforeach
                                                </flux:table.row>
                                            @endforeach
                                        </flux:table.rows>
                                    </flux:table>
                                </div>
                            @endif
                        </div>
                    </div>
                @endif
            </div>

            {{-- Report B --}}
            <div class="bg-white dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700">
                <div class="p-3 border-b border-zinc-200 dark:border-zinc-700">
                    <div class="space-y-3">
                        <div class="flex items-center gap-2">
                            <div class="w-6 h-6 rounded-full bg-purple-100 dark:bg-purple-900/30 flex items-center justify-center">
                                <span class="text-xs font-bold text-purple-600 dark:text-purple-400">B</span>
                            </div>
                            <span class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Report B</span>
                        </div>
                        <flux:select wire:model.live="reportClassB" size="sm">
                            @foreach($this->availableReports as $report)
                                <flux:select.option value="{{ get_class($report) }}">
                                    {{ $report->name() }}
                                </flux:select.option>
                            @endforeach
                        </flux:select>

                        @if($this->reportB)
                            <p class="text-xs text-zinc-500">
                                {{ $this->reportB->description() }}
                            </p>
                        @endif
                    </div>
                </div>

                @if($this->reportB)
                    <div class="p-3">
                        <div class="space-y-3">
                            @if(isset($filtersB['date_range']))
                                <div>
                                    <label class="text-xs font-medium text-zinc-500 uppercase tracking-wide mb-1.5 block">Date Range</label>
                                    <div class="flex items-center gap-2">
                                        <flux:input
                                            type="date"
                                            wire:model="filtersB.date_range.start"
                                            size="sm"
                                            class="flex-1"
                                        />
                                        <span class="text-zinc-400 text-sm">to</span>
                                        <flux:input
                                            type="date"
                                            wire:model="filtersB.date_range.end"
                                            size="sm"
                                            class="flex-1"
                                        />
                                    </div>
                                </div>
                            @endif

                            <div class="flex gap-2">
                                <flux:button wire:click="applyFiltersB" size="sm" class="flex-1" icon="funnel">
                                    Apply Filters
                                </flux:button>
                                <flux:button wire:click="resetFiltersB" variant="ghost" size="sm" icon="arrow-path">
                                    Reset
                                </flux:button>
                            </div>

                            @if($previewDataB)
                                <div class="pt-3 border-t border-zinc-200 dark:border-zinc-700">
                                    <p class="text-xs text-zinc-500 mb-2">
                                        Showing {{ $previewDataB->count() }} of {{ number_format($totalRowsB ?? 0) }} rows
                                    </p>
                                    <flux:table>
                                        <flux:table.columns>
                                            @foreach($this->reportB->columns() as $column)
                                                <flux:table.column>{{ $column['label'] }}</flux:table.column>
                                            @endforeach
                                        </flux:table.columns>

                                        <flux:table.rows>
                                            @foreach($previewDataB as $row)
                                                <flux:table.row>
                                                    @foreach(array_keys($this->reportB->columns()) as $key)
                                                        <flux:table.cell>{{ $row->$key }}</flux:table.cell>
                                                    @endforeach
                                                </flux:table.row>
                                            @endforeach
                                        </flux:table.rows>
                                    </flux:table>
                                </div>
                            @endif
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
