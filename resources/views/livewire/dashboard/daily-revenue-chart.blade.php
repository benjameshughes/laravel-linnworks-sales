<div class="bg-white dark:bg-zinc-800 rounded-xl shadow-sm border border-zinc-200 dark:border-zinc-700">
    <div class="p-6 pb-4 border-b border-zinc-200 dark:border-zinc-700">
        <div class="flex items-center justify-between">
            <div>
                <div class="flex items-center gap-3">
                    <flux:icon name="{{ $viewMode === 'items' ? 'cube' : 'chart-bar' }}" class="size-5 text-zinc-500 dark:text-zinc-400" />
                    <h3 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">{{ $viewMode === 'items' ? 'Items Sold' : 'Orders vs Revenue' }}</h3>
                </div>
                <p class="text-sm text-zinc-600 dark:text-zinc-400 mt-1">{{ $this->periodLabel }}</p>
            </div>
            <div class="flex items-center gap-2">
                <div x-data="{ cooldown: false }">
                    <flux:radio.group
                        wire:model.live="viewMode"
                        variant="segmented"
                        class="[&>label]:transition-all [&>label]:duration-200"
                        wire:loading.attr="disabled"
                        x-bind:disabled="cooldown"
                        x-on:change="cooldown = true; setTimeout(() => cooldown = false, 500)"
                    >
                        <flux:radio value="orders_revenue" icon="chart-bar">Orders/Revenue</flux:radio>
                        <flux:radio value="items" icon="cube">Items</flux:radio>
                    </flux:radio.group>
                </div>
            </div>
        </div>
    </div>

    <div class="p-6">
        @if(empty($dailyBreakdown))
            <div class="text-center text-zinc-500 dark:text-zinc-400 py-8">
                <div class="animate-pulse space-y-4">
                    <div class="h-4 bg-zinc-200 dark:bg-zinc-700 rounded w-3/4 mx-auto"></div>
                    <div class="h-4 bg-zinc-200 dark:bg-zinc-700 rounded w-1/2 mx-auto"></div>
                </div>
                <p class="mt-4">No data available</p>
            </div>
        @else
            <div
                wire:ignore
                x-data="dailyRevenueChart(
                    @entangle('dailyBreakdown').live,
                    @entangle('viewMode').live
                )"
                class="relative"
                style="height: 350px"
            >
                <!-- Chart canvas (always visible - Chart.js needs context) -->
                <canvas x-ref="canvas" class="w-full h-full"></canvas>

                <!-- Loading skeleton (overlays canvas while initializing) -->
                <div x-show="loading" class="absolute inset-0 bg-white dark:bg-zinc-800 flex flex-col justify-center p-6">
                    <div class="animate-pulse space-y-3">
                        <div class="h-4 bg-zinc-200 dark:bg-zinc-700 rounded w-3/4"></div>
                        <div class="h-4 bg-zinc-200 dark:bg-zinc-700 rounded w-5/6"></div>
                        <div class="h-4 bg-zinc-200 dark:bg-zinc-700 rounded w-4/6"></div>
                        <div class="h-32 bg-zinc-200 dark:bg-zinc-700 rounded mt-4"></div>
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>