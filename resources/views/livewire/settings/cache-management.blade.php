<section class="w-full">
    @include('partials.settings-heading')

    <style>
        @keyframes flame-flicker {
            0%, 100% { transform: translateY(0) scale(1); filter: brightness(1); }
            25% { transform: translateY(-2px) scale(1.05); filter: brightness(1.2); }
            50% { transform: translateY(-1px) scale(0.98); filter: brightness(0.9); }
            75% { transform: translateY(-3px) scale(1.03); filter: brightness(1.1); }
        }

        @keyframes glow-pulse {
            0%, 100% {
                box-shadow: 0 0 10px rgba(251, 146, 60, 0.3),
                           0 0 20px rgba(251, 146, 60, 0.2),
                           0 0 30px rgba(251, 146, 60, 0.1);
            }
            50% {
                box-shadow: 0 0 15px rgba(251, 146, 60, 0.5),
                           0 0 30px rgba(251, 146, 60, 0.3),
                           0 0 45px rgba(251, 146, 60, 0.2);
            }
        }

        @keyframes tick-in {
            0% { transform: scale(0) rotate(-45deg); opacity: 0; }
            50% { transform: scale(1.2) rotate(-45deg); opacity: 1; }
            100% { transform: scale(1) rotate(0deg); opacity: 1; }
        }

        .flame-animate { animation: flame-flicker 0.8s ease-in-out infinite; }
        .glow-orange { animation: glow-pulse 2s ease-in-out infinite; }
        .tick-in { animation: tick-in 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55) forwards; }
    </style>

    <x-settings.layout :heading="__('Cache Management')" :subheading="__('Monitor and control dashboard metrics caching')">
        <div class="my-6 w-full space-y-10">

            {{-- Cache Status Section --}}
            <x-animations.fade-in-up :delay="100" class="bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 p-6 space-y-6">
                <div class="flex items-start gap-3">
                    <div class="flex-shrink-0 w-10 h-10 bg-blue-100 dark:bg-blue-900/20 rounded-lg flex items-center justify-center">
                        <flux:icon.chart-bar class="size-6 text-blue-600 dark:text-blue-400" />
                    </div>
                    <div class="flex-1">
                        <flux:heading size="lg">Cache Status</flux:heading>
                        <flux:subheading>Current state of dashboard metrics cache</flux:subheading>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    @foreach(['7d', '30d', '90d'] as $period)
                        @php
                            $periodKey = str_replace('d', '', $period);
                            $status = $this->cacheStatus[$periodKey] ?? ['exists' => false];

                            // Simple 3-state logic: Clearing → Cached → Warming → Cold
                            // State is determined by:
                            // 1. Is this period currently warming? Check $currentlyWarmingPeriod
                            // 2. Is cache populated? Check $status['exists'] (reads from actual cache)

                            $isCurrentlyWarming = $currentlyWarmingPeriod === $period;

                            if ($isClearing && $status['exists']) {
                                // Currently clearing cache
                                $bgClass = 'bg-cyan-50 dark:bg-cyan-900/10 border-cyan-200 dark:border-cyan-800';
                                $textClass = 'text-cyan-900 dark:text-cyan-100';
                                $icon = 'trash';
                                $iconClass = 'text-cyan-600 dark:text-cyan-400 animate-pulse';
                                $label = 'Clearing...';
                            } elseif ($status['exists']) {
                                // Cache exists in Laravel cache - show green
                                $bgClass = 'bg-green-50 dark:bg-green-900/10 border-green-200 dark:border-green-800';
                                $textClass = 'text-green-900 dark:text-green-100';
                                $icon = 'check-circle';
                                $iconClass = 'text-green-600 dark:text-green-400';
                                $label = 'Cached';
                            } elseif ($isCurrentlyWarming) {
                                // This period is actively warming right now
                                $bgClass = 'bg-yellow-50 dark:bg-yellow-900/10 border-yellow-200 dark:border-yellow-800';
                                $textClass = 'text-yellow-900 dark:text-yellow-100';
                                $icon = 'fire';
                                $iconClass = 'text-yellow-600 dark:text-yellow-400 animate-pulse';
                                $label = 'Warming...';
                            } else {
                                // Cold, not cached
                                $bgClass = 'bg-blue-50 dark:bg-blue-900/10 border-blue-200 dark:border-blue-800';
                                $textClass = 'text-blue-900 dark:text-blue-100';
                                $icon = 'snowflake';
                                $iconClass = 'text-blue-400 dark:text-blue-500';
                                $label = 'Cold';
                            }
                        @endphp

                        <div wire:key="status-{{ $period }}-{{ $status['exists'] ? 'cached' : 'cold' }}-{{ $isCurrentlyWarming ? 'warming' : '' }}" class="p-4 rounded-lg border transition-all duration-500 {{ $bgClass }}">
                            <div class="flex items-center justify-between gap-2 mb-3">
                                <div class="flex items-center gap-2">
                                    <flux:icon.{{ $icon }} class="size-5 {{ $iconClass }}" />
                                    <span class="font-semibold text-sm {{ $textClass }}">
                                        {{ $period }}
                                    </span>
                                </div>
                                @if(!$status['exists'])
                                    <span class="text-xs {{ $textClass }}">{{ $label }}</span>
                                @endif
                            </div>

                            @if($status['exists'])
                                <div wire:transition class="space-y-1.5 text-xs">
                                    <div class="flex justify-between">
                                        <span class="text-zinc-600 dark:text-zinc-400">Revenue:</span>
                                        <span class="font-medium text-zinc-900 dark:text-zinc-100">£{{ number_format($status['revenue'], 2) }}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-zinc-600 dark:text-zinc-400">Orders:</span>
                                        <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ number_format($status['orders']) }}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-zinc-600 dark:text-zinc-400">Items:</span>
                                        <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ number_format($status['items']) }}</span>
                                    </div>
                                    @if($status['warmed_at'])
                                        <div class="pt-1 mt-2 border-t border-green-200 dark:border-green-800">
                                            <span class="text-zinc-500 dark:text-zinc-400">{{ \Carbon\Carbon::parse($status['warmed_at'])->diffForHumans() }}</span>
                                        </div>
                                    @endif
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </x-animations.fade-in-up>

            {{-- Batch Progress Section --}}
            @if($this->activeBatch)
                <div {{ !$this->activeBatch['finished'] ? 'wire:poll.2s' : '' }}>
                <x-animations.fade-in-up
                    :delay="150"
                    class="bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 p-6 space-y-6">
                    <div class="flex items-start gap-3">
                        <div class="flex-shrink-0 w-10 h-10 bg-indigo-100 dark:bg-indigo-900/20 rounded-lg flex items-center justify-center">
                            <flux:icon.queue-list class="size-6 text-indigo-600 dark:text-indigo-400" />
                        </div>
                        <div class="flex-1">
                            <flux:heading size="lg">Cache Warming Progress</flux:heading>
                            <flux:subheading>Current job batch status</flux:subheading>
                        </div>
                    </div>

                    <div class="p-4 bg-indigo-50 dark:bg-indigo-900/10 border border-indigo-200 dark:border-indigo-800/50 rounded-lg space-y-4">
                        {{-- Progress Bar --}}
                        <div>
                            <div class="flex justify-between items-center mb-2">
                                <span class="text-sm font-medium text-indigo-900 dark:text-indigo-100">
                                    @if($this->activeBatch['finished'])
                                        Completed
                                    @else
                                        Processing Jobs
                                    @endif
                                </span>
                                <span class="text-sm text-indigo-600 dark:text-indigo-400">
                                    {{ $this->activeBatch['processed_jobs'] }} / {{ $this->activeBatch['total_jobs'] }} jobs
                                </span>
                            </div>
                            <div class="w-full bg-indigo-200 dark:bg-indigo-800 rounded-full h-2.5">
                                <div
                                    class="bg-indigo-600 dark:bg-indigo-400 h-2.5 rounded-full transition-all duration-500 {{ $this->activeBatch['finished'] ? '' : 'animate-pulse' }}"
                                    style="width: {{ $this->activeBatch['progress'] }}%"
                                ></div>
                            </div>
                        </div>

                        {{-- Batch Stats --}}
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                            <div class="p-3 bg-white dark:bg-zinc-800 rounded-lg">
                                <div class="text-xs text-zinc-600 dark:text-zinc-400">Total Jobs</div>
                                <div class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">{{ $this->activeBatch['total_jobs'] }}</div>
                            </div>
                            <div class="p-3 bg-white dark:bg-zinc-800 rounded-lg">
                                <div class="text-xs text-zinc-600 dark:text-zinc-400">Pending</div>
                                <div class="text-lg font-semibold text-yellow-600 dark:text-yellow-400">{{ $this->activeBatch['pending_jobs'] }}</div>
                            </div>
                            <div class="p-3 bg-white dark:bg-zinc-800 rounded-lg">
                                <div class="text-xs text-zinc-600 dark:text-zinc-400">Completed</div>
                                <div class="text-lg font-semibold text-green-600 dark:text-green-400">{{ $this->activeBatch['processed_jobs'] }}</div>
                            </div>
                            <div class="p-3 bg-white dark:bg-zinc-800 rounded-lg">
                                <div class="text-xs text-zinc-600 dark:text-zinc-400">Failed</div>
                                <div class="text-lg font-semibold text-red-600 dark:text-red-400">{{ $this->activeBatch['failed_jobs'] }}</div>
                            </div>
                        </div>

                        {{-- Timing Info --}}
                        <div class="pt-3 border-t border-indigo-200 dark:border-indigo-800">
                            <div class="flex justify-between text-xs">
                                <span class="text-zinc-600 dark:text-zinc-400">Started:</span>
                                <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ \Carbon\Carbon::parse($this->activeBatch['created_at'])->format('H:i:s') }}</span>
                            </div>
                            @if($this->activeBatch['finished'])
                                <div class="flex justify-between text-xs mt-1">
                                    <span class="text-zinc-600 dark:text-zinc-400">Finished:</span>
                                    <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ \Carbon\Carbon::parse($this->activeBatch['finished_at'])->format('H:i:s') }}</span>
                                </div>
                                <div class="flex justify-between text-xs mt-1">
                                    <span class="text-zinc-600 dark:text-zinc-400">Duration:</span>
                                    <span class="font-medium text-zinc-900 dark:text-zinc-100">
                                        {{ \Carbon\Carbon::parse($this->activeBatch['created_at'])->diffInSeconds(\Carbon\Carbon::parse($this->activeBatch['finished_at'])) }}s
                                    </span>
                                </div>
                            @endif
                        </div>
                    </div>
                </x-animations.fade-in-up>
                </div>
            @endif

            {{-- Recent Cache Warming Section --}}
            @if(count($this->recentCacheWarming) > 0)
                <x-animations.fade-in-up :delay="175" class="bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 p-6 space-y-6">
                    <div class="flex items-start gap-3">
                        <div class="flex-shrink-0 w-10 h-10 bg-amber-100 dark:bg-amber-900/20 rounded-lg flex items-center justify-center">
                            <flux:icon.clock class="size-6 text-amber-600 dark:text-amber-400" />
                        </div>
                        <div class="flex-1">
                            <flux:heading size="lg">Recent Cache Warming Operations</flux:heading>
                            <flux:subheading>Last 5 cache warming jobs with memory statistics</flux:subheading>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-zinc-200 dark:border-zinc-800">
                                    <th class="text-left py-2 px-3 text-xs font-medium text-zinc-600 dark:text-zinc-400">Time</th>
                                    <th class="text-left py-2 px-3 text-xs font-medium text-zinc-600 dark:text-zinc-400">Period</th>
                                    <th class="text-right py-2 px-3 text-xs font-medium text-zinc-600 dark:text-zinc-400">Orders</th>
                                    <th class="text-right py-2 px-3 text-xs font-medium text-zinc-600 dark:text-zinc-400">Memory Used</th>
                                    <th class="text-right py-2 px-3 text-xs font-medium text-zinc-600 dark:text-zinc-400">Peak Memory</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($this->recentCacheWarming as $log)
                                    <tr class="border-b border-zinc-100 dark:border-zinc-800/50 hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                                        <td class="py-2 px-3 text-zinc-600 dark:text-zinc-400">
                                            {{ \Carbon\Carbon::parse($log['timestamp'])->format('H:i:s') }}
                                        </td>
                                        <td class="py-2 px-3 font-medium text-zinc-900 dark:text-zinc-100">
                                            {{ $log['cache_key'] }}
                                        </td>
                                        <td class="py-2 px-3 text-right text-zinc-900 dark:text-zinc-100">
                                            {{ number_format($log['orders_count']) }}
                                        </td>
                                        <td class="py-2 px-3 text-right">
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium
                                                {{ $log['memory_used_mb'] > 50 ? 'bg-orange-100 dark:bg-orange-900/20 text-orange-700 dark:text-orange-400' : 'bg-green-100 dark:bg-green-900/20 text-green-700 dark:text-green-400' }}">
                                                {{ number_format($log['memory_used_mb'], 1) }} MB
                                            </span>
                                        </td>
                                        <td class="py-2 px-3 text-right">
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium
                                                {{ $log['peak_memory_mb'] > 100 ? 'bg-red-100 dark:bg-red-900/20 text-red-700 dark:text-red-400' : 'bg-blue-100 dark:bg-blue-900/20 text-blue-700 dark:text-blue-400' }}">
                                                {{ number_format($log['peak_memory_mb'], 1) }} MB
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="p-3 bg-amber-50 dark:bg-amber-900/10 border border-amber-200 dark:border-amber-800/50 rounded-lg">
                        <div class="flex items-start gap-2">
                            <flux:icon.information-circle class="size-4 text-amber-600 dark:text-amber-400 flex-shrink-0 mt-0.5" />
                            <p class="text-xs text-amber-800 dark:text-amber-200">
                                Memory usage shown is per job. Sequential processing keeps peak memory under 128MB PHP limit, preventing out-of-memory errors.
                            </p>
                        </div>
                    </div>
                </x-animations.fade-in-up>
            @endif

            {{-- Cache Controls Section --}}
            <x-animations.fade-in-up :delay="200" class="bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 p-6 space-y-6">
                <div class="flex items-start gap-3">
                    <div class="flex-shrink-0 w-10 h-10 bg-purple-100 dark:bg-purple-900/20 rounded-lg flex items-center justify-center">
                        <flux:icon.cog-6-tooth class="size-6 text-purple-600 dark:text-purple-400" />
                    </div>
                    <div class="flex-1">
                        <flux:heading size="lg">Cache Controls</flux:heading>
                        <flux:subheading>Manually trigger cache operations</flux:subheading>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    {{-- Warm Cache Control --}}
                    <div class="p-6 rounded-lg bg-zinc-50 dark:bg-zinc-800/50 border border-zinc-200 dark:border-zinc-700">
                        <div class="flex items-start gap-3 mb-4">
                            <div class="flex-shrink-0 w-10 h-10 bg-orange-100 dark:bg-orange-900/20 rounded-lg flex items-center justify-center">
                                <flux:icon.fire class="size-6 text-orange-600 dark:text-orange-400" />
                            </div>
                            <div class="flex-1">
                                <h4 class="font-semibold text-sm text-zinc-900 dark:text-zinc-100">
                                    Warm Cache
                                </h4>
                                <p class="text-xs text-zinc-600 dark:text-zinc-400 mt-1">
                                    Pre-calculate metrics for all periods (7d, 30d, 90d)
                                </p>
                            </div>
                        </div>

                        <flux:button
                            wire:click="warmCache"
                            :disabled="$isWarming"
                            variant="primary"
                            class="w-full"
                            :icon="$isWarming ? 'fire' : 'arrow-path'"
                            :icon-class="$isWarming ? 'animate-pulse' : ''"
                        >
                            @if($isWarming)
                                Warming...
                            @else
                                Warm Cache Now
                            @endif
                        </flux:button>
                    </div>

                    {{-- Clear Cache Control --}}
                    <div class="p-6 rounded-lg bg-zinc-50 dark:bg-zinc-800/50 border border-zinc-200 dark:border-zinc-700">
                        <div class="flex items-start gap-3 mb-4">
                            <div class="flex-shrink-0 w-10 h-10 bg-red-100 dark:bg-red-900/20 rounded-lg flex items-center justify-center">
                                <flux:icon.trash class="size-6 text-red-600 dark:text-red-400" />
                            </div>
                            <div class="flex-1">
                                <h4 class="font-semibold text-sm text-zinc-900 dark:text-zinc-100">
                                    Clear Cache
                                </h4>
                                <p class="text-xs text-zinc-600 dark:text-zinc-400 mt-1">
                                    Remove all cached metrics from memory
                                </p>
                            </div>
                        </div>

                        <flux:button
                            wire:click="clearCache"
                            :disabled="$isClearing"
                            variant="danger"
                            class="w-full"
                            icon="trash"
                        >
                            @if($isClearing)
                                Clearing...
                            @else
                                Clear All Cache
                            @endif
                        </flux:button>
                    </div>
                </div>
            </x-animations.fade-in-up>

            {{-- How It Works Section --}}
            <x-animations.fade-in-up :delay="300" class="bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 p-6 space-y-6">
                <div class="flex items-start gap-3">
                    <div class="flex-shrink-0 w-10 h-10 bg-green-100 dark:bg-green-900/20 rounded-lg flex items-center justify-center">
                        <flux:icon.information-circle class="size-6 text-green-600 dark:text-green-400" />
                    </div>
                    <div class="flex-1">
                        <flux:heading size="lg">How Cache Warming Works</flux:heading>
                        <flux:subheading>Understanding the event-driven caching system</flux:subheading>
                    </div>
                </div>

                <div class="p-4 bg-green-50 dark:bg-green-900/10 border border-green-200 dark:border-green-800/50 rounded-lg">
                    <ul class="space-y-2.5 text-sm text-green-900 dark:text-green-100">
                        <li class="flex items-start gap-2">
                            <flux:icon.check-circle class="size-5 text-green-600 dark:text-green-400 flex-shrink-0 mt-0.5" />
                            <span><strong>Automatic:</strong> Cache warms automatically 30 seconds after orders sync</span>
                        </li>
                        <li class="flex items-start gap-2">
                            <flux:icon.check-circle class="size-5 text-green-600 dark:text-green-400 flex-shrink-0 mt-0.5" />
                            <span><strong>Sequential:</strong> All periods (7/30/90 days) processed one at a time via job batching for optimal memory usage</span>
                        </li>
                        <li class="flex items-start gap-2">
                            <flux:icon.check-circle class="size-5 text-green-600 dark:text-green-400 flex-shrink-0 mt-0.5" />
                            <span><strong>Fast:</strong> Dashboard loads are ~617x faster (~0.3ms vs 203ms)</span>
                        </li>
                        <li class="flex items-start gap-2">
                            <flux:icon.check-circle class="size-5 text-green-600 dark:text-green-400 flex-shrink-0 mt-0.5" />
                            <span><strong>Smart:</strong> Uses Cache::flexible() - serves stale data while recalculating</span>
                        </li>
                        <li class="flex items-start gap-2">
                            <flux:icon.check-circle class="size-5 text-green-600 dark:text-green-400 flex-shrink-0 mt-0.5" />
                            <span><strong>Selective:</strong> Only standard periods cached (custom dates always live)</span>
                        </li>
                    </ul>
                </div>
            </x-animations.fade-in-up>
        </div>
    </x-settings.layout>
</section>
