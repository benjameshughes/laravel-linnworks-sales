<div class="transition-opacity duration-200" wire:loading.class="opacity-50">
    <div class="bg-white dark:bg-zinc-800 rounded-xl shadow-sm border border-zinc-200 dark:border-zinc-700">
        <div class="p-6 pb-4 border-b border-zinc-200 dark:border-zinc-700">
            <div class="flex items-center justify-between">
                <div>
                    <div class="flex items-center gap-3">
                        <flux:icon name="chart-line" class="size-5 text-zinc-500 dark:text-zinc-400" />
                        <h3 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">Sales Trend</h3>
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
                            <flux:radio value="revenue" icon="currency-pound">Revenue</flux:radio>
                            <flux:radio value="orders" icon="shopping-cart">Orders</flux:radio>
                        </flux:radio.group>
                    </div>
                </div>
            </div>
        </div>

        <div class="p-6">
            @if(empty($this->chartData['labels']))
                <div class="text-center text-zinc-500 dark:text-zinc-400 py-8">
                    No data available
                </div>
            @else
                <div
                    wire:ignore
                    x-data="salesTrendChart(
                        @entangle('chartData').live,
                        @js($this->chartOptions)
                    )"
                    class="relative"
                    style="height: 350px"
                >
                    <canvas x-ref="canvas"></canvas>
                </div>
            @endif
        </div>
    </div>
</div>