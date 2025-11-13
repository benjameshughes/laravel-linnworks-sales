<div class="min-h-screen max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="mb-8">
        <flux:heading size="xl" class="text-zinc-900 dark:text-zinc-100">
            Reports Dashboard
        </flux:heading>
        <flux:subheading class="text-zinc-600 dark:text-zinc-400">
            Generate and download custom reports for your sales data
        </flux:subheading>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Left Panel: Report List --}}
        <div class="lg:col-span-1">
            <div class="bg-white dark:bg-zinc-800 rounded-xl shadow-sm border border-zinc-200 dark:border-zinc-700 overflow-hidden">
                @foreach($reportsByCategory as $categoryValue => $reports)
                    @php
                        $category = \App\Reports\Enums\ReportCategory::from($categoryValue);
                    @endphp
                    <div class="border-b border-zinc-200 dark:border-zinc-700 last:border-b-0">
                        <div class="px-4 py-3 bg-zinc-50 dark:bg-zinc-900/50">
                            <div class="flex items-center gap-2">
                                <flux:icon :name="$category->icon()" class="size-5 text-zinc-600 dark:text-zinc-400" />
                                <h3 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100 uppercase tracking-wide">
                                    {{ $category->label() }}
                                </h3>
                            </div>
                        </div>
                        <div class="divide-y divide-zinc-200 dark:divide-zinc-700">
                            @foreach($reports as $report)
                                <button
                                    wire:click="selectReport('{{ get_class($report) }}')"
                                    class="w-full px-4 py-3 text-left hover:bg-zinc-50 dark:hover:bg-zinc-700/50 transition-colors {{ $selectedReportClass === get_class($report) ? 'bg-pink-50 dark:bg-pink-900/20 border-l-4 border-pink-500' : '' }}"
                                >
                                    <div class="flex items-start gap-3">
                                        <flux:icon :name="$report->icon()" class="size-5 mt-0.5 {{ $selectedReportClass === get_class($report) ? 'text-pink-600 dark:text-pink-400' : 'text-zinc-500 dark:text-zinc-400' }}" />
                                        <div class="flex-1 min-w-0">
                                            <h4 class="text-sm font-medium {{ $selectedReportClass === get_class($report) ? 'text-pink-900 dark:text-pink-100' : 'text-zinc-900 dark:text-zinc-100' }}">
                                                {{ $report->name() }}
                                            </h4>
                                            <p class="text-xs text-zinc-600 dark:text-zinc-400 mt-0.5 line-clamp-2">
                                                {{ $report->description() }}
                                            </p>
                                        </div>
                                    </div>
                                </button>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Right Panel: Report Viewer --}}
        <div class="lg:col-span-2">
            @if($selectedReportClass)
                <livewire:reports.report-viewer :reportClass="$selectedReportClass" :key="$selectedReportClass" />
            @else
                <div class="bg-white dark:bg-zinc-800 rounded-xl shadow-sm border border-zinc-200 dark:border-zinc-700 p-12">
                    <div class="text-center text-zinc-500 dark:text-zinc-400">
                        <flux:icon name="chart-bar" class="size-16 mx-auto mb-4 text-zinc-300 dark:text-zinc-600" />
                        <p class="text-lg font-medium mb-2">No Report Selected</p>
                        <p class="text-sm">Select a report from the list to get started</p>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
