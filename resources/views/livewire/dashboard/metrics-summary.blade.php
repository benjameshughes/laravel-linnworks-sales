<div class="relative">
    {{-- Loading Overlay --}}
    <div wire:loading.flex wire:target="updateFilters" class="absolute inset-0 z-10 items-center justify-center bg-white/60 dark:bg-zinc-900/60 backdrop-blur-[1px] rounded-xl">
        <div class="flex items-center gap-3 px-4 py-2 bg-white dark:bg-zinc-800 rounded-lg shadow-lg border border-zinc-200 dark:border-zinc-700">
            <svg class="animate-spin size-5 text-blue-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <span class="text-sm font-medium text-zinc-700 dark:text-zinc-300">Crunching numbers...</span>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-6 transition-opacity duration-200"
         wire:loading.class="opacity-50" wire:target="updateFilters">
    {{-- Total Revenue --}}
    <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl shadow-sm p-6 text-white h-32">
        <div class="flex items-center justify-between h-full">
            <div>
                <p class="text-blue-100 text-sm font-medium">Total Revenue</p>
                <p class="text-3xl font-bold"
                   wire:key="revenue-{{ $this->metrics->get('total_revenue') }}"
                   x-data="{
                       current: $store.metrics.revenue || 0,
                       target: {{ $this->metrics->get('total_revenue') }}
                   }"
                   x-init="
                       let start = current;
                       let change = target - start;
                       let duration = 800;
                       let startTime = Date.now();

                       let doAnimate = () => {
                           let elapsed = Date.now() - startTime;
                           if (elapsed < duration) {
                               current = start + (change * (elapsed / duration));
                               requestAnimationFrame(doAnimate);
                           } else {
                               current = target;
                               $store.metrics.revenue = target;
                           }
                       };
                       requestAnimationFrame(doAnimate);
                   "
                   x-text="'£' + Math.round(current).toLocaleString()">
                </p>
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
                <p class="text-3xl font-bold"
                   wire:key="orders-{{ $this->metrics->get('total_orders') }}"
                   x-data="{
                       current: $store.metrics.orders || 0,
                       target: {{ $this->metrics->get('total_orders') }}
                   }"
                   x-init="
                       let start = current;
                       let change = target - start;
                       let duration = 800;
                       let startTime = Date.now();
                       let doAnimate = () => {
                           let elapsed = Date.now() - startTime;
                           if (elapsed < duration) {
                               current = start + (change * (elapsed / duration));
                               requestAnimationFrame(doAnimate);
                           } else {
                               current = target;
                               $store.metrics.orders = target;
                           }
                       };
                       requestAnimationFrame(doAnimate);
                   "
                   x-text="Math.round(current).toLocaleString()">
                </p>
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
                <p class="text-3xl font-bold"
                   wire:key="avg-{{ $this->metrics->get('average_order_value') }}"
                   x-data="{
                       current: $store.metrics.avgOrder || 0,
                       target: {{ $this->metrics->get('average_order_value') }}
                   }"
                   x-init="
                       let start = current;
                       let change = target - start;
                       let duration = 800;
                       let startTime = Date.now();
                       let doAnimate = () => {
                           let elapsed = Date.now() - startTime;
                           if (elapsed < duration) {
                               current = start + (change * (elapsed / duration));
                               requestAnimationFrame(doAnimate);
                           } else {
                               current = target;
                               $store.metrics.avgOrder = target;
                           }
                       };
                       requestAnimationFrame(doAnimate);
                   "
                   x-text="'£' + Math.round(current).toLocaleString()">
                </p>
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
                <p class="text-3xl font-bold"
                   wire:key="items-{{ $this->metrics->get('total_items') }}"
                   x-data="{
                       current: $store.metrics.items || 0,
                       target: {{ $this->metrics->get('total_items') }}
                   }"
                   x-init="
                       let start = current;
                       let change = target - start;
                       let duration = 800;
                       let startTime = Date.now();
                       let doAnimate = () => {
                           let elapsed = Date.now() - startTime;
                           if (elapsed < duration) {
                               current = start + (change * (elapsed / duration));
                               requestAnimationFrame(doAnimate);
                           } else {
                               current = target;
                               $store.metrics.items = target;
                           }
                       };
                       requestAnimationFrame(doAnimate);
                   "
                   x-text="Math.round(current).toLocaleString()">
                </p>
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
                    <p class="text-3xl font-bold"
                       wire:key="best-{{ $this->bestDay['revenue'] }}"
                       x-data="{
                           current: $store.metrics.bestDayRevenue || 0,
                           target: {{ $this->bestDay['revenue'] }}
                       }"
                       x-init="
                           let start = current;
                           let change = target - start;
                           let duration = 800;
                           let startTime = Date.now();
                           let doAnimate = () => {
                               let elapsed = Date.now() - startTime;
                               if (elapsed < duration) {
                                   current = start + (change * (elapsed / duration));
                                   requestAnimationFrame(doAnimate);
                               } else {
                                   current = target;
                                   $store.metrics.bestDayRevenue = target;
                               }
                           };
                           requestAnimationFrame(doAnimate);
                       "
                       x-text="'£' + Math.round(current).toLocaleString()">
                    </p>
                    <p class="text-sm text-pink-100 mt-1">{{ $this->bestDay['date'] }} • {{ $this->bestDay['orders'] }} orders</p>
                </div>
                <flux:icon name="fire" class="size-8 text-pink-200" />
            </div>
        </div>
    @endif
    </div>
</div>