<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-3">
    {{-- Total Products --}}
    <div class="bg-white dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700 p-3">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-zinc-600 dark:text-zinc-400 text-sm font-medium">Products Analyzed</p>
                <p class="text-3xl font-bold text-zinc-900 dark:text-zinc-100">{{ number_format($this->metrics->get('total_products')) }}</p>
                <p class="text-sm text-zinc-500 dark:text-zinc-400 mt-1">
                    Active products with sales
                </p>
            </div>
            <flux:icon name="cube" class="size-8 text-blue-500" />
        </div>
    </div>

    {{-- Total Units Sold --}}
    <div class="bg-white dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700 p-3">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-zinc-600 dark:text-zinc-400 text-sm font-medium">Units Sold</p>
                <p class="text-3xl font-bold text-zinc-900 dark:text-zinc-100">{{ number_format($this->metrics->get('total_units_sold')) }}</p>
                <p class="text-sm text-zinc-500 dark:text-zinc-400 mt-1">
                    Total quantity moved
                </p>
            </div>
            <flux:icon name="shopping-cart" class="size-8 text-emerald-500" />
        </div>
    </div>

    {{-- Total Revenue --}}
    <div class="bg-white dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700 p-3">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-zinc-600 dark:text-zinc-400 text-sm font-medium">Product Revenue</p>
                <p class="text-3xl font-bold text-emerald-600 dark:text-emerald-400">£{{ number_format($this->metrics->get('total_revenue'), 0) }}</p>
                <p class="text-sm text-zinc-500 dark:text-zinc-400 mt-1">Total sales value</p>
            </div>
            <flux:icon name="currency-pound" class="size-8 text-purple-500" />
        </div>
    </div>

    {{-- Average Profit Margin --}}
    <div class="bg-white dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700 p-3">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-zinc-600 dark:text-zinc-400 text-sm font-medium">Avg Profit Margin</p>
                <p class="text-3xl font-bold text-zinc-900 dark:text-zinc-100">{{ number_format($this->metrics->get('avg_profit_margin'), 1) }}%</p>
                <p class="text-sm text-zinc-500 dark:text-zinc-400 mt-1">Across all products</p>
            </div>
            <flux:icon name="chart-bar" class="size-8 text-amber-500" />
        </div>
    </div>
</div>
