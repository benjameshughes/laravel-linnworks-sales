<section class="w-full" @if($isImporting) wire:poll.3s="loadPersistedState" @endif>
    @include('partials.settings-heading')

    <x-settings.layout :heading="__('Import Orders')" :subheading="__('Import historical orders from Linnworks with real-time progress tracking')">
        <div class="my-6 w-full space-y-10">
            {{-- Import Configuration --}}
            @if (!$isImporting && !$isCompleted)
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
            @if ($isImporting || $isCompleted)
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

                    {{-- Stage Indicator --}}
                    @if ($isImporting)
                        <div class="flex items-center gap-3 pb-4">
                            @if ($currentStage === 1)
                                {{-- Stage 1: Streaming --}}
                                <div class="flex items-center gap-2 px-3 py-2 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg flex-1">
                                    <div class="flex items-center gap-2 flex-1">
                                        <div class="flex size-6 items-center justify-center rounded-full bg-blue-500 text-white text-xs font-bold animate-pulse">1</div>
                                        <div class="flex-1">
                                            <div class="text-xs font-medium text-blue-900 dark:text-blue-100">Streaming Order IDs</div>
                                            <div class="text-xs text-blue-600 dark:text-blue-400">
                                                @if($streamingTotalPages > 0)
                                                    Page {{ number_format($streamingPage) }}/{{ number_format($streamingTotalPages) }}
                                                @endif
                                                @if($streamingFetched > 0)
                                                    • {{ number_format($streamingFetched) }} found
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                {{-- Stage 2: Waiting --}}
                                <div class="flex items-center gap-2 px-3 py-2 bg-zinc-50 dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-lg flex-1 opacity-50">
                                    <div class="flex items-center gap-2 flex-1">
                                        <div class="flex size-6 items-center justify-center rounded-full bg-zinc-300 dark:bg-zinc-700 text-zinc-600 dark:text-zinc-400 text-xs font-bold">2</div>
                                        <div class="flex-1">
                                            <div class="text-xs font-medium text-zinc-600 dark:text-zinc-400">Importing Orders</div>
                                            <div class="text-xs text-zinc-500 dark:text-zinc-500">Waiting...</div>
                                        </div>
                                    </div>
                                </div>
                            @else
                                {{-- Stage 1: Completed --}}
                                <div class="flex items-center gap-2 px-3 py-2 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg flex-1">
                                    <div class="flex items-center gap-2 flex-1">
                                        <div class="flex size-6 items-center justify-center rounded-full bg-green-500 text-white text-xs font-bold">✓</div>
                                        <div class="flex-1">
                                            <div class="text-xs font-medium text-green-900 dark:text-green-100">Streaming Complete</div>
                                            <div class="text-xs text-green-600 dark:text-green-400">{{ number_format($totalOrders) }} orders found</div>
                                        </div>
                                    </div>
                                </div>

                                {{-- Stage 2: Active --}}
                                <div class="flex items-center gap-2 px-3 py-2 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg flex-1">
                                    <div class="flex items-center gap-2 flex-1">
                                        <div class="flex size-6 items-center justify-center rounded-full bg-blue-500 text-white text-xs font-bold animate-pulse">2</div>
                                        <div class="flex-1">
                                            <div class="text-xs font-medium text-blue-900 dark:text-blue-100">Importing Orders</div>
                                            <div class="text-xs text-blue-600 dark:text-blue-400">
                                                {{ number_format($totalProcessed) }}/{{ number_format($totalOrders) }}
                                                @if($batchNumber > 0)
                                                    • Batch {{ number_format($batchNumber) }}
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endif
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

                    {{-- Additional Stats --}}
                    @if ($totalSkipped > 0 || $startedAt)
                        <div class="border-t border-zinc-200 dark:border-zinc-700 pt-4 space-y-2 text-sm">
                            @if ($totalSkipped > 0)
                                <div class="flex justify-between">
                                    <span class="text-zinc-600 dark:text-zinc-400">Skipped (already exists)</span>
                                    <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ number_format($totalSkipped) }}</span>
                                </div>
                            @endif
                            @if ($startedAt)
                                <div class="flex justify-between">
                                    <span class="text-zinc-600 dark:text-zinc-400">Started at</span>
                                    <span class="font-medium text-zinc-900 dark:text-zinc-100">
                                        {{ \Carbon\Carbon::parse($startedAt)->format('Y-m-d H:i:s') }}
                                    </span>
                                </div>
                            @endif
                        </div>
                    @endif
                </x-animations.fade-in-up>
            @endif
        </div>
    </x-settings.layout>
</section>
