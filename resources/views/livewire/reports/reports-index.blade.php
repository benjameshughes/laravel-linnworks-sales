<div class="min-h-screen max-w-7xl py-12">
    {{-- Page Header --}}
    <div class="mb-8 flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
        <div>
            <flux:heading size="xl" class="text-zinc-900 dark:text-zinc-100">
                Reports Dashboard
            </flux:heading>
            <flux:subheading class="text-zinc-600 dark:text-zinc-400">
                Generate and download custom reports for your sales data
            </flux:subheading>
        </div>
        <flux:button variant="ghost" wire:navigate href="/reports/compare" icon="arrows-right-left">
            Compare Reports
        </flux:button>
    </div>

    {{-- Single Column Layout --}}
    <div class="space-y-6">
        {{-- Report Selector Section --}}
        <div class="bg-white dark:bg-zinc-800 rounded-xl shadow-sm border border-zinc-200 dark:border-zinc-700 overflow-hidden">
            <div class="px-3 py-2 border-b border-zinc-200 dark:border-zinc-700 dark:from-pink-900/10 dark:to-purple-900/10">
                <div class="flex items-center gap-3">
                    <div class="p-2 rounded-lg">
                        <flux:icon name="document-text" class="size-5" />
                    </div>
                    <div class="flex-1">
                        <h2 class="text-md font-bold text-zinc-900 dark:text-zinc-100">
                            Select Report
                        </h2>
                        <p class="text-xs text-zinc-600 dark:text-zinc-400">
                            Choose a report type and configure filters
                        </p>
                    </div>
                </div>
            </div>

            <div class="px-6 py-5">
                {{-- Report Dropdown --}}
                <div class="mb-2">
                    <flux:field>
                        <flux:label>Report Type</flux:label>
                        <flux:select wire:model.live="selectedReportSlug">
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
                    </flux:field>
                </div>

                {{-- Selected Report Info --}}
                @if($this->selectedReport)
                    <div class="flex items-start mb-4">
                        <div class="flex-1 min-w-0">
                            <p class="text-sm text-zinc-600 dark:text-zinc-400 mt-1">
                                {{ $this->selectedReport->description() }}
                            </p>
                        </div>
                    </div>

                    {{-- Filters Section --}}
                    <div class="space-y-4">
                        <h3 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100 uppercase tracking-wide flex items-center gap-2">
                            <flux:icon name="funnel" class="size-4" />
                            Filters
                        </h3>

                        {{-- Date Range Filter --}}
                        @if(isset($filters['date_range']))
                            <flux:field>
                                <flux:label>Date Range</flux:label>
                                <div class="flex items-center gap-2">
                                    <flux:input
                                        type="date"
                                        wire:model.live.debounce.500ms="filters.date_range.start"
                                        class="flex-1"
                                    />
                                    <span class="text-zinc-400 dark:text-zinc-500">to</span>
                                    <flux:input
                                        type="date"
                                        wire:model.live.debounce.500ms="filters.date_range.end"
                                        class="flex-1"
                                    />
                                </div>
                            </flux:field>
                        @endif

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
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

                        {{-- Action Buttons --}}
                        <div class="flex items-center gap-2 pt-2">
                            <flux:button
                                wire:click="applyFilters"
                                class=""
                                icon="funnel"
                            >
                                <span wire:loading.remove wire:target="applyFilters">Generate Report</span>
                                <span wire:loading wire:target="applyFilters">Generating Report...</span>
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
                @endif
            </div>
        </div>

        {{-- Preview Section --}}
        @if($this->selectedReport && $previewData !== null)
            <div class="bg-white dark:bg-zinc-800 rounded-xl shadow-sm border border-zinc-200 dark:border-zinc-700 overflow-hidden">
                <div class="px-6 py-4 border-b border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-900/50">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                        <div>
                            <h3 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100 uppercase tracking-wide flex items-center gap-2">
                                <flux:icon name="table-cells" class="size-4" />
                                Preview
                            </h3>
                            <p class="text-xs text-zinc-600 dark:text-zinc-400 mt-1">
                                Showing first {{ $previewData->count() }} of {{ number_format($totalRows) }} rows
                            </p>
                        </div>
                        <flux:button
                            wire:click="download('xlsx')"
                            icon="arrow-down-tray"
                        >
                            <span wire:loading.remove wire:target="download">Download XLSX</span>
                            <span wire:loading wire:target="download">Preparing...</span>
                        </flux:button>
                    </div>
                </div>

                <div>
                    @if($previewData->isNotEmpty())
                        <div class="overflow-x-auto">
                            <table class="w-full">
                                <thead class="bg-zinc-100 dark:bg-zinc-900 border-b border-zinc-300 dark:border-zinc-600">
                                    <tr>
                                        @foreach($this->selectedReport->columns() as $columnKey => $columnConfig)
                                            <th class="px-4 py-3 text-left text-xs font-semibold text-zinc-900 dark:text-zinc-100 uppercase tracking-wider whitespace-nowrap">
                                                {{ $columnConfig['label'] ?? $columnKey }}
                                            </th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                                    @foreach($previewData as $row)
                                        <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-700/50 transition-colors">
                                            @foreach($this->selectedReport->columns() as $columnKey => $columnConfig)
                                                <td class="px-4 py-3 text-sm text-zinc-700 dark:text-zinc-300 whitespace-nowrap">
                                                    @php
                                                        $value = $row->{$columnKey} ?? '';
                                                        $type = $columnConfig['type'] ?? 'string';
                                                    @endphp

                                                    @if($type === 'currency')
                                                        <span class="font-semibold text-green-600 dark:text-green-400">
                                                            £{{ number_format((float)($value ?: 0), 2) }}
                                                        </span>
                                                    @elseif($type === 'integer')
                                                        <span class="font-mono">
                                                            {{ number_format((int)($value ?: 0)) }}
                                                        </span>
                                                    @elseif($type === 'percentage')
                                                        {{ number_format((float)($value ?: 0), 2) }}%
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
            </div>
        @elseif($this->selectedReport)
            <div class="bg-white dark:bg-zinc-800 rounded-xl shadow-sm border border-zinc-200 dark:border-zinc-700 p-12">
                <div class="text-center text-zinc-500 dark:text-zinc-400">
                    <flux:icon name="funnel" class="size-12 mx-auto mb-2 text-zinc-300 dark:text-zinc-600" />
                    <p class="text-lg font-medium mb-2">Configure Filters</p>
                    <p class="text-sm">Adjust the filters above and click "Apply Filters" to generate a preview</p>
                </div>
            </div>
        @endif
    </div>
</div>
