<div class="grid grid-cols-2 lg:grid-cols-4 gap-2">
    {{-- Total Revenue --}}
    <div class="bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-lg p-3">
        <div class="flex items-center justify-between mb-1">
            <span class="text-xs font-medium text-zinc-500 uppercase tracking-wide">Revenue</span>
            <flux:icon name="currency-pound" class="size-4 text-zinc-400" />
        </div>
        <p class="text-2xl font-semibold text-emerald-600 dark:text-emerald-400">
            £{{ number_format($this->metrics->get('total_revenue'), 0) }}
        </p>
        @if($this->metrics->get('growth_rate') != 0)
            <div class="flex items-center gap-1 mt-2 text-xs">
                @if($this->metrics->get('growth_rate') >= 0)
                    <flux:icon name="arrow-up" class="size-3 text-emerald-500" />
                    <span class="text-emerald-600 dark:text-emerald-400">{{ number_format($this->metrics->get('growth_rate'), 1) }}%</span>
                @else
                    <flux:icon name="arrow-down" class="size-3 text-red-500" />
                    <span class="text-red-600 dark:text-red-400">{{ number_format(abs($this->metrics->get('growth_rate')), 1) }}%</span>
                @endif
                <span class="text-zinc-400">vs prev</span>
            </div>
        @endif
    </div>

    {{-- Total Orders --}}
    <div class="bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-lg p-3">
        <div class="flex items-center justify-between mb-1">
            <span class="text-xs font-medium text-zinc-500 uppercase tracking-wide">Orders</span>
            <flux:icon name="shopping-bag" class="size-4 text-zinc-400" />
        </div>
        <p class="text-2xl font-semibold text-zinc-900 dark:text-zinc-100">
            {{ number_format($this->metrics->get('total_orders')) }}
        </p>
        <p class="text-xs text-zinc-500 mt-2">
            {{ number_format($this->metrics->get('orders_per_day'), 1) }} per day
        </p>
    </div>

    {{-- Average Order Value --}}
    <div class="bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-lg p-3">
        <div class="flex items-center justify-between mb-1">
            <span class="text-xs font-medium text-zinc-500 uppercase tracking-wide">Avg Value</span>
            <flux:icon name="calculator" class="size-4 text-zinc-400" />
        </div>
        <p class="text-2xl font-semibold text-zinc-900 dark:text-zinc-100">
            £{{ number_format($this->metrics->get('average_order_value'), 0) }}
        </p>
        <p class="text-xs text-zinc-500 mt-2">Per order value</p>
    </div>

    {{-- Items Sold --}}
    <div class="bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-lg p-3">
        <div class="flex items-center justify-between mb-1">
            <span class="text-xs font-medium text-zinc-500 uppercase tracking-wide">Items</span>
            <flux:icon name="cube" class="size-4 text-zinc-400" />
        </div>
        <p class="text-2xl font-semibold text-zinc-900 dark:text-zinc-100">
            {{ number_format($this->metrics->get('total_items')) }}
        </p>
        <p class="text-xs text-zinc-500 mt-2">Total units</p>
    </div>
</div>
