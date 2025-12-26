<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
    {{-- Total Products --}}
    <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl shadow-sm p-6 text-white">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-blue-100 text-sm font-medium">Products Analyzed</p>
                <p class="text-3xl font-bold">{{ number_format($this->metrics->get('total_products')) }}</p>
                <p class="text-sm text-blue-100 mt-1">
                    Active products with sales
                </p>
            </div>
            <flux:icon name="cube" class="size-8 text-blue-200" />
        </div>
    </div>

    {{-- Total Units Sold --}}
    <div class="bg-gradient-to-br from-emerald-500 to-emerald-600 rounded-xl shadow-sm p-6 text-white">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-emerald-100 text-sm font-medium">Units Sold</p>
                <p class="text-3xl font-bold">{{ number_format($this->metrics->get('total_units_sold')) }}</p>
                <p class="text-sm text-emerald-100 mt-1">
                    Total quantity moved
                </p>
            </div>
            <flux:icon name="shopping-cart" class="size-8 text-emerald-200" />
        </div>
    </div>

    {{-- Total Revenue --}}
    <div class="bg-gradient-to-br from-purple-500 to-purple-600 rounded-xl shadow-sm p-6 text-white">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-purple-100 text-sm font-medium">Product Revenue</p>
                <p class="text-3xl font-bold">{{ number_format($this->metrics->get('total_revenue'), 0) }}</p>
                <p class="text-sm text-purple-100 mt-1">Total sales value</p>
            </div>
            <flux:icon name="currency-pound" class="size-8 text-purple-200" />
        </div>
    </div>

    {{-- Average Profit Margin --}}
    <div class="bg-gradient-to-br from-orange-500 to-orange-600 rounded-xl shadow-sm p-6 text-white">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-orange-100 text-sm font-medium">Avg Profit Margin</p>
                <p class="text-3xl font-bold">{{ number_format($this->metrics->get('avg_profit_margin'), 1) }}%</p>
                <p class="text-sm text-orange-100 mt-1">Across all products</p>
            </div>
            <flux:icon name="chart-bar" class="size-8 text-orange-200" />
        </div>
    </div>
</div>
