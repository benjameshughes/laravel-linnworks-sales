<div class="space-y-6">
    <div class="flex items-center justify-between">
        <h2 class="text-2xl font-bold text-zinc-900 dark:text-zinc-100">
            Report Comparison
        </h2>
        <flux:button variant="ghost" wire:navigate href="/reports">
            <flux:icon.arrow-left class="size-5" />
            Back to Reports
        </flux:button>
    </div>

    <div class="grid grid-cols-2 gap-6">
        {{-- Report A --}}
        <div class="bg-white dark:bg-zinc-800 rounded-xl shadow-sm border border-zinc-200 dark:border-zinc-700 overflow-hidden">
            <div class="px-6 py-4 border-b border-zinc-200 dark:border-zinc-700 bg-gradient-to-r from-blue-50 to-cyan-50 dark:from-blue-900/10 dark:to-cyan-900/10">
                <div class="space-y-3">
                    <flux:label>Report A</flux:label>
                    <flux:select wire:model.live="reportClassA">
                        @foreach($this->availableReports as $report)
                            <flux:select.option value="{{ get_class($report) }}">
                                {{ $report->name() }}
                            </flux:select.option>
                        @endforeach
                    </flux:select>

                    @if($this->reportA)
                        <div class="flex items-center gap-2">
                            <flux:icon :name="$this->reportA->icon()" class="size-5 text-blue-600 dark:text-blue-400" />
                            <p class="text-sm text-zinc-600 dark:text-zinc-400">
                                {{ $this->reportA->description() }}
                            </p>
                        </div>
                    @endif
                </div>
            </div>

            @if($this->reportA)
                <div class="px-6 py-4">
                    <div class="space-y-4">
                        {{-- Simple filters display --}}
                        @if(isset($filtersA['date_range']))
                            <div>
                                <flux:label>Date Range</flux:label>
                                <div class="flex items-center gap-2">
                                    <flux:input
                                        type="date"
                                        wire:model="filtersA.date_range.start"
                                        class="flex-1"
                                    />
                                    <span class="text-zinc-400">to</span>
                                    <flux:input
                                        type="date"
                                        wire:model="filtersA.date_range.end"
                                        class="flex-1"
                                    />
                                </div>
                            </div>
                        @endif

                        <div class="flex gap-2">
                            <flux:button wire:click="applyFiltersA" variant="primary" class="flex-1">
                                Apply Filters
                            </flux:button>
                            <flux:button wire:click="resetFiltersA" variant="ghost">
                                Reset
                            </flux:button>
                        </div>

                        @if($previewDataA)
                            <div class="mt-4">
                                <div class="text-sm text-zinc-600 dark:text-zinc-400 mb-2">
                                    Showing {{ $previewDataA->count() }} of {{ number_format($totalRowsA ?? 0) }} rows
                                </div>
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
        <div class="bg-white dark:bg-zinc-800 rounded-xl shadow-sm border border-zinc-200 dark:border-zinc-700 overflow-hidden">
            <div class="px-6 py-4 border-b border-zinc-200 dark:border-zinc-700 bg-gradient-to-r from-purple-50 to-pink-50 dark:from-purple-900/10 dark:to-pink-900/10">
                <div class="space-y-3">
                    <flux:label>Report B</flux:label>
                    <flux:select wire:model.live="reportClassB">
                        @foreach($this->availableReports as $report)
                            <flux:select.option value="{{ get_class($report) }}">
                                {{ $report->name() }}
                            </flux:select.option>
                        @endforeach
                    </flux:select>

                    @if($this->reportB)
                        <div class="flex items-center gap-2">
                            <flux:icon :name="$this->reportB->icon()" class="size-5 text-purple-600 dark:text-purple-400" />
                            <p class="text-sm text-zinc-600 dark:text-zinc-400">
                                {{ $this->reportB->description() }}
                            </p>
                        </div>
                    @endif
                </div>
            </div>

            @if($this->reportB)
                <div class="px-6 py-4">
                    <div class="space-y-4">
                        {{-- Simple filters display --}}
                        @if(isset($filtersB['date_range']))
                            <div>
                                <flux:label>Date Range</flux:label>
                                <div class="flex items-center gap-2">
                                    <flux:input
                                        type="date"
                                        wire:model="filtersB.date_range.start"
                                        class="flex-1"
                                    />
                                    <span class="text-zinc-400">to</span>
                                    <flux:input
                                        type="date"
                                        wire:model="filtersB.date_range.end"
                                        class="flex-1"
                                    />
                                </div>
                            </div>
                        @endif

                        <div class="flex gap-2">
                            <flux:button wire:click="applyFiltersB" variant="primary" class="flex-1">
                                Apply Filters
                            </flux:button>
                            <flux:button wire:click="resetFiltersB" variant="ghost">
                                Reset
                            </flux:button>
                        </div>

                        @if($previewDataB)
                            <div class="mt-4">
                                <div class="text-sm text-zinc-600 dark:text-zinc-400 mb-2">
                                    Showing {{ $previewDataB->count() }} of {{ number_format($totalRowsB ?? 0) }} rows
                                </div>
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
