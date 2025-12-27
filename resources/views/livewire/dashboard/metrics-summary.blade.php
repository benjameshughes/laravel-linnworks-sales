<div class="grid grid-cols-2 lg:grid-cols-4 gap-2">
    {{-- Total Revenue --}}
    <div class="bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-lg p-3">
        <div class="flex items-center gap-3">
            <div class="flex-shrink-0 w-10 h-10 rounded-lg bg-emerald-50 dark:bg-emerald-900/30 flex items-center justify-center">
                <flux:icon name="currency-pound" class="size-5 text-emerald-600 dark:text-emerald-400" />
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-xs font-medium text-zinc-500 uppercase tracking-wide">Revenue</p>
                <p class="text-xl font-semibold text-emerald-600 dark:text-emerald-400 truncate"
                   x-data="currencyCounter({{ $this->metrics->get('total_revenue') }}, '£', 'totalRevenue')"
                   x-text="formattedValue">
                </p>
            </div>
            <div class="flex-shrink-0 text-right">
                @if($this->metrics->get('growth_rate') != 0)
                    <div class="flex items-center gap-1 text-xs">
                        @if($this->metrics->get('growth_rate') >= 0)
                            <flux:icon name="arrow-up" class="size-3 text-emerald-500" />
                            <span class="text-emerald-600 dark:text-emerald-400">{{ number_format($this->metrics->get('growth_rate'), 1) }}%</span>
                        @else
                            <flux:icon name="arrow-down" class="size-3 text-red-500" />
                            <span class="text-red-600 dark:text-red-400">{{ number_format(abs($this->metrics->get('growth_rate')), 1) }}%</span>
                        @endif
                    </div>
                    <p class="text-xs text-zinc-400">vs prev</p>
                @endif
            </div>
        </div>
    </div>

    {{-- Total Orders --}}
    <div class="bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-lg p-3">
        <div class="flex items-center gap-3">
            <div class="flex-shrink-0 w-10 h-10 rounded-lg bg-blue-50 dark:bg-blue-900/30 flex items-center justify-center">
                <flux:icon name="shopping-bag" class="size-5 text-blue-600 dark:text-blue-400" />
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-xs font-medium text-zinc-500 uppercase tracking-wide">Orders</p>
                <p class="text-xl font-semibold text-zinc-900 dark:text-zinc-100 truncate"
                   x-data="integerCounter({{ $this->metrics->get('total_orders') }}, 'totalOrders')"
                   x-text="formattedValue">
                </p>
            </div>
            <div class="flex-shrink-0 text-right">
                <p class="text-sm font-medium text-zinc-600 dark:text-zinc-400">{{ number_format($this->metrics->get('orders_per_day'), 1) }}</p>
                <p class="text-xs text-zinc-400">per day</p>
            </div>
        </div>
    </div>

    {{-- Average Order Value --}}
    <div class="bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-lg p-3">
        <div class="flex items-center gap-3">
            <div class="flex-shrink-0 w-10 h-10 rounded-lg bg-purple-50 dark:bg-purple-900/30 flex items-center justify-center">
                <flux:icon name="calculator" class="size-5 text-purple-600 dark:text-purple-400" />
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-xs font-medium text-zinc-500 uppercase tracking-wide">Avg Value</p>
                <p class="text-xl font-semibold text-zinc-900 dark:text-zinc-100 truncate"
                   x-data="currencyCounter({{ $this->metrics->get('average_order_value') }}, '£', 'avgOrderValue')"
                   x-text="formattedValue">
                </p>
            </div>
            <div class="flex-shrink-0 text-right">
                <p class="text-sm font-medium text-zinc-600 dark:text-zinc-400">{{ number_format($this->metrics->get('total_orders')) }}</p>
                <p class="text-xs text-zinc-400">orders</p>
            </div>
        </div>
    </div>

    {{-- Items Sold --}}
    <div class="bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-lg p-3">
        <div class="flex items-center gap-3">
            <div class="flex-shrink-0 w-10 h-10 rounded-lg bg-amber-50 dark:bg-amber-900/30 flex items-center justify-center">
                <flux:icon name="cube" class="size-5 text-amber-600 dark:text-amber-400" />
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-xs font-medium text-zinc-500 uppercase tracking-wide">Items</p>
                <p class="text-xl font-semibold text-zinc-900 dark:text-zinc-100 truncate"
                   x-data="integerCounter({{ $this->metrics->get('total_items') }}, 'totalItems')"
                   x-text="formattedValue">
                </p>
            </div>
            <div class="flex-shrink-0 text-right">
                <p class="text-sm font-medium text-zinc-600 dark:text-zinc-400">{{ number_format($this->metrics->get('total_orders') > 0 ? $this->metrics->get('total_items') / $this->metrics->get('total_orders') : 0, 1) }}</p>
                <p class="text-xs text-zinc-400">per order</p>
            </div>
        </div>
    </div>
</div>
