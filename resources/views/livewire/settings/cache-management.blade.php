<section class="w-full">
    @include('partials.settings-heading')

    <x-settings.layout :heading="__('Cache Management')" :subheading="__('Monitor and control dashboard metrics caching')">
        <div class="my-6 w-full space-y-10">

            {{-- Flash Messages --}}
            @if(session('cache-warmed'))
                <x-animations.fade-in-up :delay="0" class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg p-4">
                    <div class="flex items-start gap-2">
                        <flux:icon.check-circle class="size-5 text-green-600 dark:text-green-400 flex-shrink-0 mt-0.5" />
                        <p class="text-sm text-green-800 dark:text-green-200">
                            {{ session('cache-warmed') }}
                        </p>
                    </div>
                </x-animations.fade-in-up>
            @endif

            @if(session('cache-cleared'))
                <x-animations.fade-in-up :delay="0" class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
                    <div class="flex items-start gap-2">
                        <flux:icon.check-circle class="size-5 text-blue-600 dark:text-blue-400 flex-shrink-0 mt-0.5" />
                        <p class="text-sm text-blue-800 dark:text-blue-200">
                            {{ session('cache-cleared') }}
                        </p>
                    </div>
                </x-animations.fade-in-up>
            @endif

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
                    @foreach($this->cacheStatus as $period => $status)
                        <div class="p-4 rounded-lg border {{ $status['exists'] ? 'bg-green-50 dark:bg-green-900/10 border-green-200 dark:border-green-800' : 'bg-zinc-50 dark:bg-zinc-800/50 border-zinc-200 dark:border-zinc-700' }}">
                            <div class="flex items-center gap-2 mb-3">
                                @if($status['exists'])
                                    <flux:icon.check-circle class="size-5 text-green-600 dark:text-green-400" />
                                @else
                                    <flux:icon.x-circle class="size-5 text-zinc-400 dark:text-zinc-500" />
                                @endif
                                <span class="font-semibold text-sm {{ $status['exists'] ? 'text-green-900 dark:text-green-100' : 'text-zinc-600 dark:text-zinc-400' }}">
                                    {{ $period }} Period
                                </span>
                            </div>

                            @if($status['exists'])
                                <div class="space-y-1.5 text-xs">
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
                                            <span class="text-zinc-500 dark:text-zinc-400">Updated: {{ \Carbon\Carbon::parse($status['warmed_at'])->diffForHumans() }}</span>
                                        </div>
                                    @endif
                                </div>
                            @else
                                <p class="text-xs text-zinc-500 dark:text-zinc-400">Cache not warmed</p>
                            @endif
                        </div>
                    @endforeach
                </div>

                @if($this->queuedJobs > 0)
                    <div class="p-4 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg">
                        <div class="flex items-start gap-2">
                            <flux:icon.clock class="size-5 text-amber-600 dark:text-amber-400 flex-shrink-0 mt-0.5" />
                            <p class="text-sm text-amber-800 dark:text-amber-200">
                                {{ $this->queuedJobs }} job(s) queued. Cache warming in progress...
                            </p>
                        </div>
                    </div>
                @endif
            </x-animations.fade-in-up>

            {{-- Cache Controls Section --}}
            <x-animations.fade-in-up :delay="200" class="bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 p-6 space-y-6">
                <div class="flex items-start gap-3">
                    <div class="flex-shrink-0 w-10 h-10 bg-purple-100 dark:bg-purple-900/20 rounded-lg flex items-center justify-center">
                        <flux:icon.cog-6-tooth class="size-6 text-purple-600 dark:text-purple-400" />
                    </div>
                    <div class="flex-1">
                        <flux:heading size="lg">Cache Controls</flux:heading>
                        <flux:subheading>Manually manage dashboard cache</flux:subheading>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    {{-- Warm Cache Control --}}
                    <div class="p-4 rounded-lg space-y-3 transition-all duration-300 {{ $isWarming ? 'bg-orange-50 dark:bg-orange-900/20 border-2 border-orange-300 dark:border-orange-700' : 'bg-zinc-50 dark:bg-zinc-800/50 border-2 border-transparent' }}">
                        <div class="flex items-start gap-2">
                            <div class="flex-shrink-0 mt-0.5">
                                @if($isWarming)
                                    <flux:icon.fire class="size-5 text-orange-600 dark:text-orange-400 animate-pulse" />
                                @else
                                    <flux:icon.arrow-path class="size-5 text-purple-600 dark:text-purple-400" />
                                @endif
                            </div>
                            <div class="flex-1">
                                <h4 class="font-semibold text-sm {{ $isWarming ? 'text-orange-900 dark:text-orange-100' : 'text-zinc-900 dark:text-zinc-100' }}">
                                    Warm Cache
                                </h4>
                                <p class="text-xs {{ $isWarming ? 'text-orange-600 dark:text-orange-400' : 'text-zinc-600 dark:text-zinc-400' }} mt-1">
                                    @if($isWarming)
                                        Warming cache... {{ count($warmingPeriods) }}/3 periods completed
                                    @else
                                        Pre-calculate and store metrics for all periods. Takes ~30 seconds to complete.
                                    @endif
                                </p>
                            </div>
                        </div>

                        @if($isWarming)
                            <div class="space-y-2">
                                @foreach(['7d', '30d', '90d'] as $period)
                                    <div class="flex items-center gap-2 text-xs">
                                        @if(in_array($period, $warmingPeriods))
                                            <flux:icon.check-circle class="size-4 text-green-600 dark:text-green-400" />
                                            <span class="text-green-700 dark:text-green-300">{{ $period }} period cached</span>
                                        @else
                                            <div class="size-4 border-2 border-orange-400 border-t-transparent rounded-full animate-spin"></div>
                                            <span class="text-orange-700 dark:text-orange-300">Warming {{ $period }}...</span>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        <button
                            wire:click="warmCache"
                            class="w-full px-4 py-2 rounded-lg font-medium transition-all duration-300 transform {{ $isWarming ? 'bg-orange-600 hover:bg-orange-700 text-white scale-105' : 'bg-purple-600 hover:bg-purple-700 text-white' }} disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2"
                            {{ $isWarming ? 'disabled' : '' }}
                        >
                            @if($isWarming)
                                <flux:icon.fire class="size-5 animate-pulse" />
                                <span>Warming...</span>
                            @else
                                <flux:icon.arrow-path class="size-5" />
                                <span>Warm Cache Now</span>
                            @endif
                        </button>
                    </div>

                    {{-- Clear Cache Control --}}
                    <div class="p-4 rounded-lg space-y-3 transition-all duration-300 {{ $isClearing ? 'bg-blue-50 dark:bg-blue-900/20 border-2 border-blue-300 dark:border-blue-700' : 'bg-zinc-50 dark:bg-zinc-800/50 border-2 border-transparent' }}">
                        <div class="flex items-start gap-2">
                            <div class="flex-shrink-0 mt-0.5">
                                @if($isClearing)
                                    <svg class="size-5 text-blue-600 dark:text-blue-400 animate-pulse" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4" />
                                    </svg>
                                @else
                                    <flux:icon.trash class="size-5 text-red-600 dark:text-red-400" />
                                @endif
                            </div>
                            <div class="flex-1">
                                <h4 class="font-semibold text-sm {{ $isClearing ? 'text-blue-900 dark:text-blue-100' : 'text-zinc-900 dark:text-zinc-100' }}">
                                    Clear Cache
                                </h4>
                                <p class="text-xs {{ $isClearing ? 'text-blue-600 dark:text-blue-400' : 'text-zinc-600 dark:text-zinc-400' }} mt-1">
                                    @if($isClearing)
                                        Clearing cache... This will complete momentarily.
                                    @else
                                        Remove all cached metrics. Next dashboard load will recalculate from database.
                                    @endif
                                </p>
                            </div>
                        </div>

                        <button
                            wire:click="clearCache"
                            class="w-full px-4 py-2 rounded-lg font-medium transition-all duration-300 transform {{ $isClearing ? 'bg-blue-600 hover:bg-blue-700 text-white scale-105' : 'bg-red-600 hover:bg-red-700 text-white' }} disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2"
                            {{ $isClearing ? 'disabled' : '' }}
                        >
                            @if($isClearing)
                                <div class="size-5 border-2 border-white border-t-transparent rounded-full animate-spin"></div>
                                <span>Clearing...</span>
                            @else
                                <flux:icon.trash class="size-5" />
                                <span>Clear All Cache</span>
                            @endif
                        </button>
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
                            <span><strong>Parallel:</strong> All periods (7/30/90 days) compute concurrently using Laravel Concurrency</span>
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
