<div class="relative">
    {{-- Loading Overlay --}}
    <div wire:loading.flex wire:target="updateFilters"
         class="absolute inset-0 z-10 items-center justify-center bg-white/60 dark:bg-zinc-800/60 backdrop-blur-sm rounded-lg">
        <div class="flex items-center gap-2">
            <flux:icon name="arrow-path" class="size-4 animate-spin text-zinc-500" />
            <span class="text-sm text-zinc-500">Updating...</span>
        </div>
    </div>

    {{-- Metrics Grid --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-2">
        {{-- Total Orders --}}
        <div class="bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-lg p-3">
            <div class="flex items-center justify-between mb-1">
                <span class="text-xs font-medium text-zinc-500 uppercase tracking-wide">Orders</span>
                <flux:icon name="shopping-bag" class="size-4 text-zinc-400" />
            </div>
            <p class="text-2xl font-semibold text-zinc-900 dark:text-zinc-100">
                {{ number_format($this->metrics->get('total_orders', 0)) }}
            </p>
            @if($this->comparison->isNotEmpty())
                <div class="flex items-center gap-1 mt-2 text-xs">
                    @if($this->comparison->get('changes')['orders'] >= 0)
                        <flux:icon name="arrow-up" class="size-3 text-emerald-500" />
                        <span class="text-emerald-600 dark:text-emerald-400">{{ number_format($this->comparison->get('changes')['orders'], 1) }}%</span>
                    @else
                        <flux:icon name="arrow-down" class="size-3 text-red-500" />
                        <span class="text-red-600 dark:text-red-400">{{ number_format(abs($this->comparison->get('changes')['orders']), 1) }}%</span>
                    @endif
                    <span class="text-zinc-400">vs prev</span>
                </div>
            @endif
        </div>

        {{-- Total Revenue --}}
        <div class="bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-lg p-3">
            <div class="flex items-center justify-between mb-1">
                <span class="text-xs font-medium text-zinc-500 uppercase tracking-wide">Revenue</span>
                <flux:icon name="currency-pound" class="size-4 text-zinc-400" />
            </div>
            <p class="text-2xl font-semibold text-zinc-900 dark:text-zinc-100">
                £{{ number_format($this->metrics->get('total_revenue', 0), 0) }}
            </p>
            @if($this->comparison->isNotEmpty())
                <div class="flex items-center gap-1 mt-2 text-xs">
                    @if($this->comparison->get('changes')['revenue'] >= 0)
                        <flux:icon name="arrow-up" class="size-3 text-emerald-500" />
                        <span class="text-emerald-600 dark:text-emerald-400">{{ number_format($this->comparison->get('changes')['revenue'], 1) }}%</span>
                    @else
                        <flux:icon name="arrow-down" class="size-3 text-red-500" />
                        <span class="text-red-600 dark:text-red-400">{{ number_format(abs($this->comparison->get('changes')['revenue']), 1) }}%</span>
                    @endif
                    <span class="text-zinc-400">vs prev</span>
                </div>
            @endif
        </div>

        {{-- Average Order Value --}}
        <div class="bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-lg p-3">
            <div class="flex items-center justify-between mb-1">
                <span class="text-xs font-medium text-zinc-500 uppercase tracking-wide">Avg Value</span>
                <flux:icon name="chart-bar" class="size-4 text-zinc-400" />
            </div>
            <p class="text-2xl font-semibold text-zinc-900 dark:text-zinc-100">
                £{{ number_format($this->metrics->get('avg_order_value', 0), 2) }}
            </p>
            @if($this->comparison->isNotEmpty())
                <div class="flex items-center gap-1 mt-2 text-xs">
                    @if($this->comparison->get('changes')['avg_value'] >= 0)
                        <flux:icon name="arrow-up" class="size-3 text-emerald-500" />
                        <span class="text-emerald-600 dark:text-emerald-400">{{ number_format($this->comparison->get('changes')['avg_value'], 1) }}%</span>
                    @else
                        <flux:icon name="arrow-down" class="size-3 text-red-500" />
                        <span class="text-red-600 dark:text-red-400">{{ number_format(abs($this->comparison->get('changes')['avg_value']), 1) }}%</span>
                    @endif
                    <span class="text-zinc-400">vs prev</span>
                </div>
            @endif
        </div>

        {{-- Items Sold --}}
        <div class="bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-lg p-3">
            <div class="flex items-center justify-between mb-1">
                <span class="text-xs font-medium text-zinc-500 uppercase tracking-wide">Items</span>
                <flux:icon name="cube" class="size-4 text-zinc-400" />
            </div>
            <p class="text-2xl font-semibold text-zinc-900 dark:text-zinc-100">
                {{ number_format($this->metrics->get('total_items', 0)) }}
            </p>
            <div class="flex items-center gap-2 mt-2 text-xs text-zinc-500">
                <span>{{ number_format($this->metrics->get('open_orders', 0)) }} open</span>
                <span class="text-zinc-300 dark:text-zinc-600">·</span>
                <span>{{ number_format($this->metrics->get('paid_orders', 0)) }} paid</span>
            </div>
        </div>
    </div>
</div>
