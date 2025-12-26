<div>
    @if($this->productDetails)
        @php
            $details = $this->productDetails;
            $product = $details['product'];
            $profit = $details['profit_analysis'];
            $channels = $details['channel_performance'];
            $stock = $details['stock_info'];
        @endphp

        {{-- Sales Chart Section --}}
        @if($showChart)
            <div class="bg-white dark:bg-zinc-800 rounded-xl shadow-sm border border-zinc-200 dark:border-zinc-700 p-6 mb-6">
            <div class="flex items-center justify-between mb-6">
                <flux:heading size="lg" class="text-zinc-900 dark:text-zinc-100">
                    Sales Trend: {{ $product->title ?? 'Selected Product' }}
                </flux:heading>
                <flux:button variant="ghost" size="sm" wire:click="toggleChart" icon="eye-slash">
                    Hide Chart
                </flux:button>
            </div>
            <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-7 gap-4">
                @foreach($this->productSalesChart as $day)
                    <div class="text-center p-3 rounded-lg bg-zinc-50 dark:bg-zinc-700 border border-zinc-200 dark:border-zinc-600">
                        <div class="text-xs text-zinc-600 dark:text-zinc-400 font-medium">{{ $day['date'] }}</div>
                        <div class="text-lg font-bold text-zinc-900 dark:text-zinc-100 mt-1">{{ $day['quantity'] }}</div>
                        <div class="text-xs text-zinc-600 dark:text-zinc-400">{{ number_format($day['revenue'], 0) }}</div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Product Details Panel --}}
    <div class="bg-white dark:bg-zinc-800 rounded-xl shadow-sm border border-zinc-200 dark:border-zinc-700">
        <div class="p-6 border-b border-zinc-200 dark:border-zinc-700">
            <div class="flex items-center justify-between">
                <div>
                    <flux:heading size="lg" class="text-zinc-900 dark:text-zinc-100">{{ $product->title }}</flux:heading>
                    <div class="flex items-center gap-4 text-sm text-zinc-600 dark:text-zinc-400 mt-1">
                        <span>SKU: {{ $product->sku }}</span>
                        @if($product->category_name)
                            <span>|</span>
                            <span>{{ $product->category_name }}</span>
                        @endif
                        <span>|</span>
                        <span>{{ number_format($profit['total_sold']) }} units sold</span>
                    </div>
                </div>
                <div class="flex gap-2">
                    @if(!$showChart)
                        <flux:button variant="ghost" size="sm" wire:click="toggleChart" icon="chart-bar">
                            Show Chart
                        </flux:button>
                    @endif
                    <flux:button variant="ghost" size="sm" wire:click="clearSelection" icon="x-mark">
                        Close
                    </flux:button>
                </div>
            </div>
        </div>

        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                {{-- Revenue --}}
                <div class="text-center p-4 rounded-lg bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800">
                    <div class="text-2xl font-bold text-blue-600 dark:text-blue-400">
                        {{ number_format($profit['total_revenue'], 2) }}
                    </div>
                    <div class="text-sm text-blue-600/80 dark:text-blue-400/80">Total Revenue</div>
                </div>

                {{-- Profit --}}
                <div class="text-center p-4 rounded-lg bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800">
                    <div class="text-2xl font-bold text-green-600 dark:text-green-400">
                        {{ number_format($profit['total_profit'], 2) }}
                    </div>
                    <div class="text-sm text-green-600/80 dark:text-green-400/80">Total Profit</div>
                </div>

                {{-- Margin --}}
                <div class="text-center p-4 rounded-lg bg-purple-50 dark:bg-purple-900/20 border border-purple-200 dark:border-purple-800">
                    <div class="text-2xl font-bold text-purple-600 dark:text-purple-400">
                        {{ number_format($profit['profit_margin_percent'], 1) }}%
                    </div>
                    <div class="text-sm text-purple-600/80 dark:text-purple-400/80">Profit Margin</div>
                </div>

                {{-- Stock --}}
                <div class="text-center p-4 rounded-lg bg-orange-50 dark:bg-orange-900/20 border border-orange-200 dark:border-orange-800">
                    <div class="text-2xl font-bold text-orange-600 dark:text-orange-400">
                        {{ number_format($stock['current_stock']) }}
                    </div>
                    <div class="text-sm text-orange-600/80 dark:text-orange-400/80">Current Stock</div>
                </div>
            </div>

            {{-- Channel Performance --}}
            @if($channels->isNotEmpty())
                <div>
                    <flux:heading size="md" class="text-zinc-900 dark:text-zinc-100 mb-4">Channel Performance</flux:heading>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        @foreach($channels as $channel)
                            <div class="p-4 rounded-lg bg-zinc-50 dark:bg-zinc-700 border border-zinc-200 dark:border-zinc-600">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $channel['channel'] }}</div>
                                        <div class="text-sm text-zinc-600 dark:text-zinc-400">{{ $channel['quantity_sold'] }} units</div>
                                    </div>
                                    <div class="text-right">
                                        <div class="font-bold text-zinc-900 dark:text-zinc-100">{{ number_format($channel['revenue'], 2) }}</div>
                                        <div class="text-xs text-zinc-500">{{ $channel['order_count'] }} orders</div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </div>
    @endif
</div>
