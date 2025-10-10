<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-6 transition-opacity duration-200" wire:loading.class="opacity-50">
    {{-- Total Revenue --}}
    <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl shadow-sm p-6 text-white h-32">
        <div class="flex items-center justify-between h-full">
            <div>
                <p class="text-blue-100 text-sm font-medium">Total Revenue</p>
                <p class="text-3xl font-bold">£{{ number_format($this->metrics->get('total_revenue'), 0) }}</p>
                @if($this->metrics->get('growth_rate') != 0)
                    <p class="text-sm text-blue-100 mt-1">
                        {{ $this->metrics->get('growth_rate') > 0 ? '+' : '' }}{{ number_format($this->metrics->get('growth_rate'), 1) }}% vs previous period
                    </p>
                @endif
            </div>
            <flux:icon name="currency-pound" class="size-8 text-blue-200" />
        </div>
    </div>

    {{-- Total Orders --}}
    <div class="bg-gradient-to-br from-emerald-500 to-emerald-600 rounded-xl shadow-sm p-6 text-white h-32">
        <div class="flex items-center justify-between h-full">
            <div>
                <p class="text-emerald-100 text-sm font-medium">Total Orders</p>
                <p class="text-3xl font-bold">{{ number_format($this->metrics->get('total_orders')) }}</p>
                <p class="text-sm text-emerald-100 mt-1">
                    {{ number_format($this->metrics->get('orders_per_day'), 1) }} per day
                </p>
            </div>
            <flux:icon name="shopping-bag" class="size-8 text-emerald-200" />
        </div>
    </div>

    {{-- Average Order Value --}}
    <div class="bg-gradient-to-br from-purple-500 to-purple-600 rounded-xl shadow-sm p-6 text-white h-32">
        <div class="flex items-center justify-between h-full">
            <div>
                <p class="text-purple-100 text-sm font-medium">Average Order</p>
                <p class="text-3xl font-bold">£{{ number_format($this->metrics->get('average_order_value'), 0) }}</p>
                <p class="text-sm text-purple-100 mt-1">Per order value</p>
            </div>
            <flux:icon name="calculator" class="size-8 text-purple-200" />
        </div>
    </div>

    {{-- Total Items --}}
    <div class="bg-gradient-to-br from-orange-500 to-orange-600 rounded-xl shadow-sm p-6 text-white h-32">
        <div class="flex items-center justify-between h-full">
            <div>
                <p class="text-orange-100 text-sm font-medium">Items Sold</p>
                <p class="text-3xl font-bold">{{ number_format($this->metrics->get('total_items')) }}</p>
                <p class="text-sm text-orange-100 mt-1">Total units</p>
            </div>
            <flux:icon name="cube" class="size-8 text-orange-200" />
        </div>
    </div>

    {{-- Best Day --}}
    @if($this->bestDay)
        <div class="bg-gradient-to-br from-pink-500 to-rose-600 rounded-xl shadow-sm p-6 text-white h-32">
            <div class="flex items-center justify-between h-full">
                <div>
                    <div class="flex items-center gap-1.5">
                        <p class="text-pink-100 text-sm font-medium">Best Day</p>
                        <flux:icon name="star" class="size-3 text-pink-200" />
                    </div>
                    <p class="text-3xl font-bold">£{{ number_format($this->bestDay['revenue'], 0) }}</p>
                    <p class="text-sm text-pink-100 mt-1">{{ $this->bestDay['date'] }} • {{ $this->bestDay['orders'] }} orders</p>
                </div>
                <flux:icon name="fire" class="size-8 text-pink-200" />
            </div>
        </div>
    @endif
</div>