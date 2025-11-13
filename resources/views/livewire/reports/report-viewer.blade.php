<div>
    <div class="bg-white dark:bg-zinc-800 rounded-xl shadow-sm border border-zinc-200 dark:border-zinc-700 overflow-hidden">
        {{-- Header --}}
        <div class="px-6 py-4 border-b border-zinc-200 dark:border-zinc-700 bg-gradient-to-r from-pink-50 to-purple-50 dark:from-pink-900/10 dark:to-purple-900/10">
            <div class="flex items-center gap-3">
                <div class="p-2 bg-gradient-to-br from-pink-500 to-purple-600 rounded-lg">
                    <flux:icon :name="$this->report->icon()" class="size-6 text-white" />
                </div>
                <div class="flex-1">
                    <h2 class="text-xl font-bold text-zinc-900 dark:text-zinc-100">
                        {{ $this->report->name() }}
                    </h2>
                    <p class="text-sm text-zinc-600 dark:text-zinc-400">
                        {{ $this->report->description() }}
                    </p>
                </div>
            </div>
        </div>

        {{-- Filters --}}
        <div class="px-6 py-4 border-b border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-900/50">
            <h3 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100 mb-3 uppercase tracking-wide">Filters</h3>

            <div class="space-y-4">
                {{-- Date Range Filter --}}
                @if(isset($filters['date_range']))
                    <div>
                        <flux:label>Date Range</flux:label>
                        <div class="flex items-center gap-2">
                            <flux:input
                                type="date"
                                wire:model.live.debounce.500ms="filters.date_range.start"
                                class="flex-1"
                            />
                            <span class="text-zinc-400">to</span>
                            <flux:input
                                type="date"
                                wire:model.live.debounce.500ms="filters.date_range.end"
                                class="flex-1"
                            />
                        </div>
                    </div>
                @endif

                <div class="grid grid-cols-2 gap-4">
                    {{-- SKU Filter --}}
                    @if(isset($filters['skus']))
                        <div>
                            <x-pill-selector
                                :options="collect($availableSkus)->map(fn($sku) => ['value' => $sku, 'label' => $sku])->toArray()"
                                :selected="$filters['skus']"
                                label="SKUs"
                                :placeholder="'All SKUs (' . count($availableSkus) . ')'"
                                toggle-method="toggleSku"
                                clear-method="clearFilters"
                            />
                        </div>
                    @endif

                    {{-- Subsource Filter --}}
                    @if(isset($filters['subsources']))
                        <div>
                            <x-pill-selector
                                :options="collect($availableSubsources)->map(fn($sub) => ['value' => $sub, 'label' => $sub])->toArray()"
                                :selected="$filters['subsources']"
                                label="Subsources"
                                :placeholder="'All Subsources (' . count($availableSubsources) . ')'"
                                toggle-method="toggleSubsource"
                                clear-method="clearFilters"
                            />
                        </div>
                    @endif
                </div>

                <div class="flex items-center gap-2 pt-2">
                    <flux:button
                        wire:click="applyFilters"
                        icon="funnel"
                    >
                        Generate Report
                    </flux:button>
                    <flux:button
                        wire:click="resetFilters"
                        variant="ghost"
                        icon="arrow-path"
                    >
                        Reset
                    </flux:button>
                </div>
            </div>
        </div>

        {{-- Preview --}}
        @if($previewData !== null)
            <div class="px-6 py-4">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h3 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100 uppercase tracking-wide">Preview</h3>
                        <p class="text-xs text-zinc-600 dark:text-zinc-400 mt-1">
                            Showing first {{ $previewData->count() }} of {{ number_format($totalRows) }} rows
                        </p>
                    </div>
                    <flux:button
                        wire:click="download('xlsx')"
                        class="bg-gradient-to-r from-pink-500 to-purple-600 hover:from-pink-600 hover:to-purple-700 text-white"
                        icon="arrow-down-tray"
                    >
                        <span wire:loading.remove wire:target="download">Download XLSX</span>
                        <span wire:loading wire:target="download">Preparing...</span>
                    </flux:button>
                </div>

                @if($previewData->isNotEmpty())
                    <div class="overflow-x-auto border border-zinc-200 dark:border-zinc-700 rounded-lg">
                        <table class="w-full">
                            <thead class="bg-gradient-to-r from-zinc-100 to-zinc-50 dark:from-zinc-900 dark:to-zinc-800 border-b-2 border-zinc-300 dark:border-zinc-600">
                                <tr>
                                    @foreach($this->report->columns() as $columnKey => $columnConfig)
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-zinc-900 dark:text-zinc-100 uppercase tracking-wider">
                                            {{ $columnConfig['label'] ?? $columnKey }}
                                        </th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                                @foreach($previewData as $row)
                                    <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-700/50 transition-colors">
                                        @foreach($this->report->columns() as $columnKey => $columnConfig)
                                            <td class="px-4 py-3 text-sm text-zinc-700 dark:text-zinc-300">
                                                @php
                                                    $value = $row->{$columnKey} ?? '';
                                                    $type = $columnConfig['type'] ?? 'string';
                                                @endphp

                                                @if($type === 'currency')
                                                    <span class="font-semibold text-green-600 dark:text-green-400">
                                                        £{{ number_format($value, 2) }}
                                                    </span>
                                                @elseif($type === 'integer')
                                                    <span class="font-mono">
                                                        {{ number_format($value) }}
                                                    </span>
                                                @elseif($type === 'percentage')
                                                    {{ number_format($value, 2) }}%
                                                @else
                                                    {{ $value }}
                                                @endif
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="text-center py-12">
                        <flux:icon name="chart-bar" class="size-12 mx-auto mb-2 text-zinc-300 dark:text-zinc-600" />
                        <p class="text-zinc-500 dark:text-zinc-400">No data found for the selected filters</p>
                    </div>
                @endif
            </div>
        @else
            <div class="px-6 py-12">
                <div class="text-center text-zinc-500 dark:text-zinc-400">
                    <flux:icon name="funnel" class="size-12 mx-auto mb-2 text-zinc-300 dark:text-zinc-600" />
                    <p>Configure filters and click "Apply Filters" to generate preview</p>
                </div>
            </div>
        @endif
    </div>
</div>
