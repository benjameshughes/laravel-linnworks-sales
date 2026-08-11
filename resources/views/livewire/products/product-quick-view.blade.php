<div>
    @if($this->productDetails)

        {{-- Sales Chart Section --}}
        @if($showChart)
            <div class="mb-6">
                <div class="flex items-center justify-between mb-4">
                    <flux:heading size="lg">Sales Trend: {{ $this->product->title ?? 'Selected Product' }}</flux:heading>
                    <flux:button variant="ghost" size="sm" wire:click="toggleChart" icon="eye-slash">Hide Chart</flux:button>
                </div>
                <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-7 gap-3">
                    @foreach($this->productSalesChart as $day)
                        <div class="text-center p-3 rounded-lg bg-zinc-50 dark:bg-zinc-700 border border-zinc-200 dark:border-zinc-600">
                            <div class="text-xs text-zinc-600 dark:text-zinc-400 font-medium">{{ $day['date'] }}</div>
                            <div class="text-lg font-bold text-zinc-900 dark:text-zinc-100 mt-1">{{ $day['quantity'] }}</div>
                            <div class="text-xs text-zinc-600 dark:text-zinc-400">£{{ number_format($day['revenue'], 0) }}</div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Header --}}
        <div class="mb-6">
            <flux:heading size="lg">{{ $this->product->title }}</flux:heading>
            <div class="flex items-center gap-4 text-sm text-zinc-600 dark:text-zinc-400 mt-1">
                <span>SKU: {{ $this->product->sku }}</span>
                @if($this->product->category_name)
                    <span>|</span>
                    <span>{{ $this->product->category_name }}</span>
                @endif
                <span>|</span>
                <span>{{ number_format($this->profit['total_sold'] ?? 0) }} units sold</span>
            </div>
            @if(!$showChart)
                <flux:button variant="ghost" size="sm" wire:click="toggleChart" icon="chart-bar" class="mt-2">Show Chart</flux:button>
            @endif
        </div>

        {{-- KPI Cards --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div class="text-center p-4 rounded-lg bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800">
                <div class="text-2xl font-bold text-blue-600 dark:text-blue-400">£{{ number_format($this->profit['total_revenue'] ?? 0, 2) }}</div>
                <div class="text-sm text-blue-600/80 dark:text-blue-400/80">Revenue</div>
            </div>
            <div class="text-center p-4 rounded-lg bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800">
                <div class="text-2xl font-bold text-green-600 dark:text-green-400">£{{ number_format($this->profit['total_profit'] ?? 0, 2) }}</div>
                <div class="text-sm text-green-600/80 dark:text-green-400/80">Profit</div>
            </div>
            <div class="text-center p-4 rounded-lg bg-purple-50 dark:bg-purple-900/20 border border-purple-200 dark:border-purple-800">
                <div class="text-2xl font-bold text-purple-600 dark:text-purple-400">{{ number_format($this->profit['profit_margin_percent'] ?? 0, 1) }}%</div>
                <div class="text-sm text-purple-600/80 dark:text-purple-400/80">Margin</div>
            </div>
            <div class="text-center p-4 rounded-lg bg-orange-50 dark:bg-orange-900/20 border border-orange-200 dark:border-orange-800">
                <div class="text-2xl font-bold text-orange-600 dark:text-orange-400">{{ number_format($this->stock['current_stock'] ?? 0) }}</div>
                <div class="text-sm text-orange-600/80 dark:text-orange-400/80">Stock</div>
            </div>
        </div>

        {{-- Cost Breakdown --}}
        <div class="mb-6 p-4 rounded-lg bg-zinc-50 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700">
            <flux:heading size="sm" class="mb-3">Profit Breakdown</flux:heading>
            <div class="space-y-2 text-sm">
                <div class="flex justify-between">
                    <span class="text-zinc-500 dark:text-zinc-400">Revenue</span>
                    <span class="font-medium text-emerald-600 dark:text-emerald-400">£{{ number_format($this->profit['total_revenue'] ?? 0, 2) }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-zinc-500 dark:text-zinc-400">Cost of Goods</span>
                    <span class="font-medium text-red-500 dark:text-red-400">-£{{ number_format($this->profit['cogs'] ?? 0, 2) }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-zinc-500 dark:text-zinc-400">Channel Fees</span>
                    <span class="font-medium text-red-500 dark:text-red-400">-£{{ number_format($this->profit['channel_fees'] ?? 0, 2) }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-zinc-500 dark:text-zinc-400">Shipping</span>
                    <span class="font-medium text-red-500 dark:text-red-400">-£{{ number_format($this->profit['shipping_cost'] ?? 0, 2) }}</span>
                </div>
                <div class="flex justify-between pt-2 border-t border-zinc-200 dark:border-zinc-700">
                    <span class="font-semibold text-zinc-700 dark:text-zinc-300">True Profit</span>
                    <span class="font-bold {{ ($this->profit['total_profit'] ?? 0) >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400' }}">£{{ number_format($this->profit['total_profit'] ?? 0, 2) }}</span>
                </div>
            </div>
        </div>

        {{-- Channel Performance --}}
        @if($this->channels->isNotEmpty())
            <div>
                <flux:heading size="sm" class="mb-3">Channel Performance</flux:heading>
                <div class="space-y-2">
                    @foreach($this->channels as $channel)
                        <div class="flex items-center justify-between p-3 rounded-lg bg-zinc-50 dark:bg-zinc-700 border border-zinc-200 dark:border-zinc-600">
                            <div>
                                <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ \App\Enums\Channel::displayName($channel->channel) }}</div>
                                <div class="text-xs text-zinc-600 dark:text-zinc-400">{{ $channel->quantity }} units · {{ $channel->order_count }} orders</div>
                            </div>
                            <div class="font-bold text-zinc-900 dark:text-zinc-100">£{{ number_format($channel->revenue, 2) }}</div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    @endif
</div>
