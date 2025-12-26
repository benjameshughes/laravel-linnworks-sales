<section class="w-full">
    @include('partials.settings-heading')

    <x-settings.layout :heading="__('Import Orders')" :subheading="__('Import historical orders from Linnworks with real-time progress tracking')">
        <div class="my-6 w-full space-y-10">
            {{-- Import Configuration --}}
            @if (!$this->showProgress)
                <x-animations.fade-in-up :delay="100" class="bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 p-6 space-y-6">
                    <div class="flex items-start gap-3">
                        <div class="flex-shrink-0 w-10 h-10 bg-blue-100 dark:bg-blue-900/20 rounded-lg flex items-center justify-center">
                            <flux:icon.arrow-down-tray class="size-6 text-blue-600 dark:text-blue-400" />
                        </div>
                        <div class="flex-1">
                            <flux:heading size="lg">Configure Import</flux:heading>
                            <flux:subheading>Set the date range and batch size for your import</flux:subheading>
                        </div>
                    </div>

                    <div class="space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <flux:field>
                                <flux:label>From Date</flux:label>
                                <flux:input type="date" wire:model="fromDate" />
                                <flux:error name="fromDate" />
                            </flux:field>

                            <flux:field>
                                <flux:label>To Date</flux:label>
                                <flux:input type="date" wire:model="toDate" />
                                <flux:error name="toDate" />
                            </flux:field>
                        </div>

                        <flux:field>
                            <flux:label>Batch Size (50-200)</flux:label>
                            <flux:input type="number" wire:model="batchSize" min="50" max="200" />
                            <flux:error name="batchSize" />
                            <flux:description>Number of orders to fetch per API request. Higher values are faster but may hit rate limits</flux:description>
                        </flux:field>

                        <flux:separator />

                        <div class="flex justify-end">
                            <flux:button variant="primary" wire:click="startImport" icon="arrow-down-tray">
                                Start Import
                            </flux:button>
                        </div>
                    </div>
                </x-animations.fade-in-up>

                {{-- Help Text --}}
                <x-animations.fade-in-up :delay="200" class="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg p-4">
                    <div class="flex gap-3">
                        <flux:icon.exclamation-triangle class="size-5 text-amber-500 flex-shrink-0 mt-0.5" />
                        <div class="flex-1">
                            <h4 class="font-semibold text-amber-900 dark:text-amber-100 mb-1">Important Notes</h4>
                            <ul class="text-sm text-amber-800 dark:text-amber-200 space-y-1 list-disc list-inside">
                                <li>This will import all processed orders from Linnworks within the specified date range</li>
                                <li>Large imports may take several minutes to complete</li>
                                <li>Existing orders will be updated with the latest data</li>
                                <li>The page will update in real-time as the import progresses</li>
                            </ul>
                        </div>
                    </div>
                </x-animations.fade-in-up>
            @endif

            {{-- Progress Display --}}
            @if ($this->showProgress && $this->syncLog)
                @php
                    $sync = $this->syncLog;
                    $data = $sync->progress_data ?? [];
                    $isImporting = $sync->isInProgress();
                    $isCompleted = !$isImporting;
                    $success = $sync->isCompleted();
                    $percentage = $sync->progress_percentage;
                    $message = $data['message'] ?? ($isCompleted ? ($success ? 'Import completed!' : 'Import failed') : 'Importing...');
                    $totalProcessed = $data['total_processed'] ?? $sync->total_fetched ?? 0;
                    $totalOrders = $data['total_expected'] ?? $totalProcessed;
                    $batchNumber = $data['current_batch'] ?? 0;
                    $created = $isCompleted ? ($sync->total_created ?? 0) : ($data['created'] ?? 0);
                    $updated = $isCompleted ? ($sync->total_updated ?? 0) : ($data['updated'] ?? 0);
                    $totalErrors = $isCompleted ? ($sync->total_failed ?? 0) : ($data['failed'] ?? 0);
                    $ordersPerSecond = $data['orders_per_second'] ?? 0;
                    $memoryMb = $data['memory_mb'] ?? 0;
                    $timeElapsed = $data['time_elapsed'] ?? 0;
                    $estimatedRemaining = $data['estimated_remaining'] ?? null;
                @endphp

                <x-animations.fade-in-up :delay="100" class="bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 p-6 space-y-6">
                    {{-- Status Header --}}
                    <div class="flex items-center justify-between">
                        <flux:heading size="lg">
                            @if ($isImporting)
                                <span class="flex items-center gap-2">
                                    <flux:icon.arrow-path class="size-5 text-blue-500 animate-spin" />
                                    Importing Orders...
                                </span>
                            @elseif ($success)
                                <span class="flex items-center gap-2">
                                    <flux:icon.check-circle class="size-5 text-green-500" />
                                    Import Completed
                                </span>
                            @else
                                <span class="flex items-center gap-2">
                                    <flux:icon.x-circle class="size-5 text-red-500" />
                                    Import Failed
                                </span>
                            @endif
                        </flux:heading>

                        @if ($isCompleted)
                            <flux:button variant="ghost" wire:click="resetImport" size="sm">
                                Start New Import
                            </flux:button>
                        @endif
                    </div>

                    {{-- Current Status --}}
                    @if ($isImporting && $message)
                        <div class="flex items-center gap-2 px-4 py-3 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg">
                            <flux:icon.arrow-path class="size-4 text-blue-500 animate-spin flex-shrink-0" />
                            <div class="flex-1">
                                <div class="text-sm font-medium text-blue-900 dark:text-blue-100">{{ $message }}</div>
                                @if($totalOrders > 0)
                                    <div class="text-xs text-blue-600 dark:text-blue-400">
                                        {{ number_format($totalProcessed) }}/{{ number_format($totalOrders) }} orders
                                        @if($batchNumber > 0)
                                            - Batch {{ number_format($batchNumber) }}
                                        @endif
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endif

                    {{-- Progress Bar --}}
                    <div class="space-y-2">
                        <div class="flex justify-between text-sm">
                            <span class="text-zinc-600 dark:text-zinc-400">Progress</span>
                            <span class="font-semibold text-zinc-900 dark:text-zinc-100">{{ number_format($percentage, 1) }}%</span>
                        </div>
                        <div class="w-full bg-zinc-200 dark:bg-zinc-700 rounded-full h-3 overflow-hidden">
                            <div
                                class="h-full transition-all duration-300 ease-out {{ $success ? 'bg-green-500' : ($isImporting ? 'bg-blue-500' : 'bg-red-500') }}"
                                style="width: {{ $percentage }}%"
                            ></div>
                        </div>
                    </div>

                    {{-- Stats Grid --}}
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <div class="bg-zinc-50 dark:bg-zinc-900 rounded-lg p-4">
                            <div class="text-sm text-zinc-600 dark:text-zinc-400">Processed</div>
                            <div class="text-2xl font-bold text-blue-600 dark:text-blue-400">{{ number_format($totalProcessed) }}</div>
                        </div>

                        <div class="bg-zinc-50 dark:bg-zinc-900 rounded-lg p-4">
                            <div class="text-sm text-zinc-600 dark:text-zinc-400">Created</div>
                            <div class="text-2xl font-bold text-green-600 dark:text-green-400">{{ number_format($created) }}</div>
                        </div>

                        <div class="bg-zinc-50 dark:bg-zinc-900 rounded-lg p-4">
                            <div class="text-sm text-zinc-600 dark:text-zinc-400">Updated</div>
                            <div class="text-2xl font-bold text-amber-600 dark:text-amber-400">{{ number_format($updated) }}</div>
                        </div>

                        <div class="bg-zinc-50 dark:bg-zinc-900 rounded-lg p-4">
                            <div class="text-sm text-zinc-600 dark:text-zinc-400">Errors</div>
                            <div class="text-2xl font-bold {{ $totalErrors > 0 ? 'text-red-600 dark:text-red-400' : 'text-zinc-900 dark:text-zinc-100' }}">
                                {{ number_format($totalErrors) }}
                            </div>
                        </div>
                    </div>

                    {{-- Performance Metrics --}}
                    @if ($isImporting && $ordersPerSecond > 0)
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 pt-4 border-t border-zinc-200 dark:border-zinc-700">
                            <div class="bg-zinc-50 dark:bg-zinc-900 rounded-lg p-4">
                                <div class="text-sm text-zinc-600 dark:text-zinc-400">Speed</div>
                                <div class="text-lg font-bold text-indigo-600 dark:text-indigo-400">{{ number_format($ordersPerSecond, 2) }}/s</div>
                            </div>

                            <div class="bg-zinc-50 dark:bg-zinc-900 rounded-lg p-4">
                                <div class="text-sm text-zinc-600 dark:text-zinc-400">Memory</div>
                                <div class="text-lg font-bold text-purple-600 dark:text-purple-400">{{ number_format($memoryMb, 1) }} MB</div>
                            </div>

                            <div class="bg-zinc-50 dark:bg-zinc-900 rounded-lg p-4">
                                <div class="text-sm text-zinc-600 dark:text-zinc-400">Elapsed</div>
                                <div class="text-lg font-bold text-zinc-900 dark:text-zinc-100">{{ number_format($timeElapsed, 1) }}s</div>
                            </div>

                            <div class="bg-zinc-50 dark:bg-zinc-900 rounded-lg p-4">
                                <div class="text-sm text-zinc-600 dark:text-zinc-400">Remaining</div>
                                <div class="text-lg font-bold text-zinc-900 dark:text-zinc-100">
                                    @if ($estimatedRemaining)
                                        {{ number_format($estimatedRemaining, 1) }}s
                                    @else
                                        -
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endif

                    {{-- Started At --}}
                    @if ($sync->started_at)
                        <div class="border-t border-zinc-200 dark:border-zinc-700 pt-4 space-y-2 text-sm">
                            <div class="flex justify-between">
                                <span class="text-zinc-600 dark:text-zinc-400">Started at</span>
                                <span class="font-medium text-zinc-900 dark:text-zinc-100">
                                    {{ $sync->started_at->format('Y-m-d H:i:s') }}
                                </span>
                            </div>
                            @if ($sync->error_message)
                                <div class="flex justify-between">
                                    <span class="text-zinc-600 dark:text-zinc-400">Error</span>
                                    <span class="font-medium text-red-600 dark:text-red-400">
                                        {{ Str::limit($sync->error_message, 100) }}
                                    </span>
                                </div>
                            @endif
                        </div>
                    @endif
                </x-animations.fade-in-up>
            @endif

            {{-- Sync History --}}
            @if (count($syncHistory) > 0)
                <x-animations.fade-in-up :delay="300" class="bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 p-6 space-y-4">
                    <div class="flex items-center justify-between">
                        <flux:heading size="lg">Import History</flux:heading>
                        <flux:badge color="zinc">{{ count($syncHistory) }} syncs</flux:badge>
                    </div>

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
                            @foreach ($syncHistory as $historyItem)
                                <flux:table.row>
                                    <flux:table.cell>
                                        <flux:badge size="sm" :color="$historyItem['status_color']">
                                            {{ $historyItem['status_label'] }}
                                        </flux:badge>
                                    </flux:table.cell>
                                    <flux:table.cell class="text-zinc-500">{{ $historyItem['date_range'] ?? '-' }}</flux:table.cell>
                                    <flux:table.cell>{{ $historyItem['started_at'] }}</flux:table.cell>
                                    <flux:table.cell align="end" variant="strong">{{ number_format($historyItem['total_processed']) }}</flux:table.cell>
                                    <flux:table.cell align="end" class="text-emerald-600 dark:text-emerald-400">{{ number_format($historyItem['created']) }}</flux:table.cell>
                                    <flux:table.cell align="end" class="text-amber-600 dark:text-amber-400">{{ number_format($historyItem['updated']) }}</flux:table.cell>
                                    <flux:table.cell align="end" class="{{ $historyItem['failed'] > 0 ? 'text-red-600 dark:text-red-400' : 'text-zinc-500' }}">{{ number_format($historyItem['failed']) }}</flux:table.cell>
                                    <flux:table.cell align="end" class="text-zinc-500">{{ $historyItem['duration'] ?? '-' }}</flux:table.cell>
                                </flux:table.row>
                                @if ($historyItem['error_message'])
                                    <flux:table.row class="bg-red-50 dark:bg-red-900/10">
                                        <flux:table.cell colspan="8" class="text-red-700 dark:text-red-400">
                                            <span class="font-medium">Error:</span> {{ Str::limit($historyItem['error_message'], 200) }}
                                        </flux:table.cell>
                                    </flux:table.row>
                                @endif
                            @endforeach
                        </flux:table.rows>
                    </flux:table>
                </x-animations.fade-in-up>
            @endif
        </div>
    </x-settings.layout>
</section>
