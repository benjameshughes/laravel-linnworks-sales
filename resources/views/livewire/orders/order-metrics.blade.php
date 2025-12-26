<div class="relative">
    {{-- Loading Overlay --}}
    <div wire:loading.flex wire:target="updateFilters"
         class="absolute inset-0 z-10 items-center justify-center bg-white/60 dark:bg-zinc-800/60 backdrop-blur-sm rounded-xl">
        <div class="flex flex-col items-center gap-2">
            <flux:icon name="arrow-path" class="size-6 animate-spin text-blue-500" />
            <span class="text-sm text-zinc-600 dark:text-zinc-400">Calculating metrics...</span>
        </div>
    </div>

    {{-- Metrics Grid --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        {{-- Total Orders --}}
        <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl p-5 text-white shadow-lg">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-blue-100 text-sm font-medium">Total Orders</p>
                    <p class="text-3xl font-bold mt-1">{{ number_format($this->metrics->get('total_orders', 0)) }}</p>
                </div>
                <flux:icon name="shopping-bag" class="size-10 text-blue-200/50" />
            </div>
            @if($this->comparison->isNotEmpty())
                <div class="flex items-center gap-1 mt-3 text-sm">
                    @if($this->comparison->get('changes')['orders'] >= 0)
                        <flux:icon name="arrow-trending-up" class="size-4 text-green-300" />
                        <span class="text-green-200">+{{ number_format($this->comparison->get('changes')['orders'], 1) }}%</span>
                    @else
                        <flux:icon name="arrow-trending-down" class="size-4 text-red-300" />
                        <span class="text-red-200">{{ number_format($this->comparison->get('changes')['orders'], 1) }}%</span>
                    @endif
                    <span class="text-blue-200 text-xs ml-1">vs previous</span>
                </div>
            @endif
        </div>

        {{-- Total Revenue --}}
        <div class="bg-gradient-to-br from-green-500 to-green-600 rounded-xl p-5 text-white shadow-lg">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-green-100 text-sm font-medium">Total Revenue</p>
                    <p class="text-3xl font-bold mt-1">£{{ number_format($this->metrics->get('total_revenue', 0), 0) }}</p>
                </div>
                <flux:icon name="currency-pound" class="size-10 text-green-200/50" />
            </div>
            @if($this->comparison->isNotEmpty())
                <div class="flex items-center gap-1 mt-3 text-sm">
                    @if($this->comparison->get('changes')['revenue'] >= 0)
                        <flux:icon name="arrow-trending-up" class="size-4 text-green-300" />
                        <span class="text-green-200">+{{ number_format($this->comparison->get('changes')['revenue'], 1) }}%</span>
                    @else
                        <flux:icon name="arrow-trending-down" class="size-4 text-red-300" />
                        <span class="text-red-200">{{ number_format($this->comparison->get('changes')['revenue'], 1) }}%</span>
                    @endif
                    <span class="text-green-200 text-xs ml-1">vs previous</span>
                </div>
            @endif
        </div>

        {{-- Average Order Value --}}
        <div class="bg-gradient-to-br from-purple-500 to-purple-600 rounded-xl p-5 text-white shadow-lg">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-purple-100 text-sm font-medium">Avg Order Value</p>
                    <p class="text-3xl font-bold mt-1">£{{ number_format($this->metrics->get('avg_order_value', 0), 2) }}</p>
                </div>
                <flux:icon name="chart-bar" class="size-10 text-purple-200/50" />
            </div>
            @if($this->comparison->isNotEmpty())
                <div class="flex items-center gap-1 mt-3 text-sm">
                    @if($this->comparison->get('changes')['avg_value'] >= 0)
                        <flux:icon name="arrow-trending-up" class="size-4 text-green-300" />
                        <span class="text-green-200">+{{ number_format($this->comparison->get('changes')['avg_value'], 1) }}%</span>
                    @else
                        <flux:icon name="arrow-trending-down" class="size-4 text-red-300" />
                        <span class="text-red-200">{{ number_format($this->comparison->get('changes')['avg_value'], 1) }}%</span>
                    @endif
                    <span class="text-purple-200 text-xs ml-1">vs previous</span>
                </div>
            @endif
        </div>

        {{-- Total Items Sold --}}
        <div class="bg-gradient-to-br from-orange-500 to-orange-600 rounded-xl p-5 text-white shadow-lg">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-orange-100 text-sm font-medium">Items Sold</p>
                    <p class="text-3xl font-bold mt-1">{{ number_format($this->metrics->get('total_items', 0)) }}</p>
                </div>
                <flux:icon name="cube" class="size-10 text-orange-200/50" />
            </div>
            <div class="flex items-center gap-2 mt-3 text-sm">
                <span class="text-orange-200">
                    {{ number_format($this->metrics->get('open_orders', 0)) }} open
                </span>
                <span class="text-orange-300">·</span>
                <span class="text-orange-200">
                    {{ number_format($this->metrics->get('paid_orders', 0)) }} paid
                </span>
            </div>
        </div>
    </div>
</div>
