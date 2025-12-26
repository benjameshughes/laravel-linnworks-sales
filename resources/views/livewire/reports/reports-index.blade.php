<div class="min-h-screen">
    <div class="space-y-3 p-3 lg:p-4">
        {{-- Page Header - matches Dashboard/Orders/Products pattern --}}
        <div class="flex items-center justify-between gap-4 pb-4 border-b border-zinc-200 dark:border-zinc-700">
            <div class="flex items-center gap-2">
                <flux:heading size="xl" class="text-zinc-900 dark:text-zinc-100">Reports</flux:heading>
            </div>
            <flux:button variant="ghost" size="sm" wire:navigate href="/reports/compare" icon="arrows-right-left">
                Compare
            </flux:button>
        </div>

        {{-- Report Selector Card --}}
        <div class="bg-white dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700 p-3">
            <div class="flex items-center justify-between mb-3">
                <span class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Select Report</span>
            </div>

            {{-- Report Dropdown --}}
            <div class="mb-3">
                <flux:select wire:model.live="selectedReportSlug" size="sm">
                    @foreach($reportsByCategory as $categoryValue => $reports)
                        @php
                            $category = \App\Reports\Enums\ReportCategory::from($categoryValue);
                        @endphp
                        <optgroup label="{{ $category->label() }}">
                            @foreach($reports as $report)
                                <flux:select.option value="{{ $report->slug() }}">
                                    {{ $report->name() }}
                                </flux:select.option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </flux:select>
            </div>

            @if($this->selectedReport)
                {{-- Description --}}
                <p class="text-xs text-zinc-500 mb-4">
                    {{ $this->selectedReport->description() }}
                </p>

                {{-- Filters Section --}}
                <div class="space-y-3 pt-3 border-t border-zinc-200 dark:border-zinc-700">
                    {{-- Date Range Filter --}}
                    @if(isset($filters['date_range']))
                        <div>
                            <label class="text-xs font-medium text-zinc-500 uppercase tracking-wide mb-1.5 block">Date Range</label>
                            <div class="flex items-center gap-2">
                                <flux:input
                                    type="date"
                                    wire:model.live.debounce.500ms="filters.date_range.start"
                                    size="sm"
                                    class="flex-1"
                                />
                                <span class="text-zinc-400 text-sm">to</span>
                                <flux:input
                                    type="date"
                                    wire:model.live.debounce.500ms="filters.date_range.end"
                                    size="sm"
                                    class="flex-1"
                                />
                            </div>
                        </div>
                    @endif

                    {{-- Additional Filters --}}
                    @if(isset($filters['skus']) || isset($filters['subsources']))
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            @if(isset($filters['skus']))
                                <x-pill-selector
                                    :options="collect($availableSkus)->map(fn($sku) => ['value' => $sku, 'label' => $sku])->toArray()"
                                    :selected="$filters['skus']"
                                    label="SKUs"
                                    :placeholder="'All SKUs (' . count($availableSkus) . ')'"
                                    toggle-method="toggleSku"
                                    clear-method="clearFilters"
                                />
                            @endif

                            @if(isset($filters['subsources']))
                                <x-pill-selector
                                    :options="collect($availableSubsources)->map(fn($sub) => ['value' => $sub, 'label' => $sub])->toArray()"
                                    :selected="$filters['subsources']"
                                    label="Subsources"
                                    :placeholder="'All Subsources (' . count($availableSubsources) . ')'"
                                    toggle-method="toggleSubsource"
                                    clear-method="clearFilters"
                                />
                            @endif
                        </div>
                    @endif

                    {{-- Action Buttons --}}
                    <div class="flex items-center gap-2 pt-2">
                        <flux:button
                            wire:click="applyFilters"
                            size="sm"
                            icon="funnel"
                        >
                            <span wire:loading.remove wire:target="applyFilters">Generate Report</span>
                            <span wire:loading wire:target="applyFilters">Generating...</span>
                        </flux:button>
                        <flux:button
                            wire:click="resetFilters"
                            variant="ghost"
                            size="sm"
                            icon="arrow-path"
                        >
                            Reset
                        </flux:button>
                    </div>
                </div>
            @endif
        </div>

        {{-- Preview Section --}}
        @if($this->selectedReport && $previewData !== null)
            <div class="bg-white dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700">
                <div class="flex items-center justify-between p-3 border-b border-zinc-200 dark:border-zinc-700">
                    <div>
                        <span class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Preview</span>
                        <p class="text-xs text-zinc-500 mt-0.5">
                            Showing {{ $previewData->count() }} of {{ number_format($totalRows) }} rows
                        </p>
                    </div>
                    <flux:button
                        wire:click="download('xlsx')"
                        size="sm"
                        icon="arrow-down-tray"
                    >
                        <span wire:loading.remove wire:target="download">Download</span>
                        <span wire:loading wire:target="download">Preparing...</span>
                    </flux:button>
                </div>

                @if($previewData->isNotEmpty())
                    <flux:table>
                        <flux:table.columns>
                            @foreach($this->selectedReport->columns() as $columnKey => $columnConfig)
                                <flux:table.column>{{ $columnConfig['label'] ?? $columnKey }}</flux:table.column>
                            @endforeach
                        </flux:table.columns>

                        <flux:table.rows>
                            @foreach($previewData as $row)
                                <flux:table.row>
                                    @foreach($this->selectedReport->columns() as $columnKey => $columnConfig)
                                        <flux:table.cell>
                                            @php
                                                $value = $row->{$columnKey} ?? '';
                                                $type = $columnConfig['type'] ?? 'string';
                                            @endphp

                                            @if($type === 'currency')
                                                <span class="font-semibold text-emerald-600 dark:text-emerald-400">
                                                    £{{ number_format((float)($value ?: 0), 2) }}
                                                </span>
                                            @elseif($type === 'integer')
                                                <span class="font-mono">{{ number_format((int)($value ?: 0)) }}</span>
                                            @elseif($type === 'percentage')
                                                {{ number_format((float)($value ?: 0), 2) }}%
                                            @else
                                                {{ $value }}
                                            @endif
                                        </flux:table.cell>
                                    @endforeach
                                </flux:table.row>
                            @endforeach
                        </flux:table.rows>
                    </flux:table>
                @else
                    <div class="text-center py-8">
                        <flux:icon name="chart-bar" class="size-10 mx-auto mb-2 text-zinc-300 dark:text-zinc-600" />
                        <p class="text-sm text-zinc-500 dark:text-zinc-400">No data found for the selected filters</p>
                    </div>
                @endif
            </div>
        @elseif($this->selectedReport)
            <div class="bg-white dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700 p-8">
                <div class="text-center text-zinc-500 dark:text-zinc-400">
                    <flux:icon name="funnel" class="size-10 mx-auto mb-2 text-zinc-300 dark:text-zinc-600" />
                    <p class="text-sm font-medium mb-1">Configure Filters</p>
                    <p class="text-xs">Adjust the filters above and click "Generate Report" to preview</p>
                </div>
            </div>
        @endif
    </div>
</div>
