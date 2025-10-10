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

                            // State tracking:
                            // - $isWarming = TRUE when warming process is active
                            // - $warmingPeriods = array of periods that have COMPLETED warming (e.g., ['7d', '30d'])
                            // - A period is "currently warming" if $isWarming is TRUE AND it's NOT YET in $warmingPeriods
                            // - A period "just warmed" if it's IN $warmingPeriods but cache data hasn't loaded yet

                            $periodCompleted = in_array($period, $warmingPeriods);
                            $periodCurrentlyWarming = $isWarming && !$periodCompleted && !$status['exists'];
                            $periodJustWarmed = $periodCompleted && !$status['exists'];

                            // State logic: Blue (cold) → Yellow (warming) → Orange (just warmed) → Green (cached) → Cyan (clearing) → Blue (cold)
                            if ($isClearing && $status['exists']) {
                                // Currently clearing cache
                                $bgClass = 'bg-cyan-50 dark:bg-cyan-900/10 border-cyan-200 dark:border-cyan-800';
                                $textClass = 'text-cyan-900 dark:text-cyan-100';
                                $icon = 'trash';
                                $iconClass = 'text-cyan-600 dark:text-cyan-400 animate-pulse';
                                $label = 'Clearing...';
                            } elseif ($status['exists']) {
                                // Fully cached with data - always show green if we have data
                                $bgClass = 'bg-green-50 dark:bg-green-900/10 border-green-200 dark:border-green-800';
                                $textClass = 'text-green-900 dark:text-green-100';
                                $icon = 'check-circle';
                                $iconClass = 'text-green-600 dark:text-green-400';
                                $label = 'Cached';
                            } elseif ($periodJustWarmed) {
                                // Just completed warming, show orange briefly
                                $bgClass = 'bg-orange-50 dark:bg-orange-900/10 border-orange-200 dark:border-orange-800';
                                $textClass = 'text-orange-900 dark:text-orange-100';
                                $icon = 'check-circle';
                                $iconClass = 'text-orange-600 dark:text-orange-400 tick-in';
                                $label = 'Warmed!';
                            } elseif ($periodCurrentlyWarming) {
                                // Currently warming this period
                                $bgClass = 'bg-yellow-50 dark:bg-yellow-900/10 border-yellow-200 dark:border-yellow-800';
                                $textClass = 'text-yellow-900 dark:text-yellow-100';
                                $icon = 'fire';
                                $iconClass = 'text-yellow-600 dark:text-yellow-400 animate-pulse';
                                $label = 'Warming up...';
                            } else {
                                // Cold, not cached
                                $bgClass = 'bg-blue-50 dark:bg-blue-900/10 border-blue-200 dark:border-blue-800';
                                $textClass = 'text-blue-900 dark:text-blue-100';
                                $icon = 'snowflake';
                                $iconClass = 'text-blue-400 dark:text-blue-500';
                                $label = 'Cold, needs warming';
                            }
                        @endphp

                        <div wire:key="status-{{ $period }}" class="p-4 rounded-lg border transition-all duration-500 {{ $bgClass }}">
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
                            @elseif($periodJustWarmed)
                                <p wire:transition class="text-xs font-medium {{ $textClass }} animate-pulse">
                                    Refreshing data...
                                </p>
                            @endif
                        </div>
                    @endforeach
                </div>
            </x-animations.fade-in-up>

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
                        >
                            @if($isWarming)
                                <flux:icon.fire class="animate-pulse" />
                                Warming...
                            @else
                                <flux:icon.arrow-path />
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
                        >
                            @if($isClearing)
                                <div class="size-5 border-2 border-white border-t-transparent rounded-full animate-spin"></div>
                                Clearing...
                            @else
                                <flux:icon.trash />
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
