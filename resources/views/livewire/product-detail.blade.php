<div class="min-h-screen bg-zinc-50 dark:bg-zinc-900">
    <div class="space-y-6 p-6">
        {{-- Header with Product Info --}}
        <div class="bg-white dark:bg-zinc-800 rounded-xl shadow-sm border border-zinc-200 dark:border-zinc-700 p-6">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                <div class="flex items-center gap-6">
                    <div class="flex items-center gap-3">
                        <flux:button 
                            variant="ghost" 
                            size="sm" 
                            href="{{ route('products.analytics') }}"
                            icon="arrow-left"
                        >
                            Back to Products
                        </flux:button>
                        <div class="w-px h-6 bg-zinc-300 dark:bg-zinc-600"></div>
                    </div>
                    <div>
                        <flux:heading size="lg" class="text-zinc-900 dark:text-zinc-100">
                            {{ $product->title }}
                        </flux:heading>
                        <div class="flex items-center gap-4 text-sm text-zinc-600 dark:text-zinc-400 mt-1">
                            <span class="font-medium">{{ $product->sku }}</span>
                            @if($product->category_name)
                                <span class="text-zinc-400">•</span>
                                <span>{{ $product->category_name }}</span>
                            @endif
                            <span class="text-zinc-400">•</span>
                            <span>{{ $this->period }}-day analysis</span>
                        </div>
                        
                        {{-- Product Badges --}}
                        @if($this->productBadges->isNotEmpty())
                            <div class="flex flex-wrap gap-2 mt-3">
                                @foreach($this->productBadges as $badge)
                                    <flux:badge 
                                        color="{{ $badge['color'] }}" 
                                        size="sm"
                                        title="{{ $badge['description'] }}"
                                    >
                                        <flux:icon name="{{ $badge['icon'] }}" class="size-4 mr-1" />
                                        {{ $badge['label'] }}
                                    </flux:badge>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
                
                {{-- Controls --}}
                <div class="flex flex-wrap gap-2">
                    <flux:select wire:model.live="period" size="sm" class="min-w-32">
                        <flux:select.option value="7">7 days</flux:select.option>
                        <flux:select.option value="30">30 days</flux:select.option>
                        <flux:select.option value="90">90 days</flux:select.option>
                        <flux:select.option value="365">1 year</flux:select.option>
                    </flux:select>
                    
                    <flux:button variant="ghost" size="sm" wire:click="$refresh" icon="arrow-path">
                        Refresh
                    </flux:button>
                </div>
            </div>
        </div>

        {{-- Key Metrics Grid --}}
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            {{-- Total Revenue --}}
            <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl shadow-sm p-6 text-white">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-blue-100 text-sm font-medium">Total Revenue</p>
                        <p class="text-3xl font-bold">£{{ number_format($this->profitAnalysis['total_revenue'], 2) }}</p>
                        <p class="text-sm text-blue-100 mt-1">
                            £{{ number_format($this->profitAnalysis['avg_selling_price'], 2) }} avg price
                        </p>
                    </div>
                    <flux:icon name="currency-pound" class="size-8 text-blue-200" />
                </div>
            </div>

            {{-- Total Profit --}}
            <div class="bg-gradient-to-br from-green-500 to-green-600 rounded-xl shadow-sm p-6 text-white">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-green-100 text-sm font-medium">Total Profit</p>
                        <p class="text-3xl font-bold">£{{ number_format($this->profitAnalysis['total_profit'], 2) }}</p>
                        <p class="text-sm text-green-100 mt-1">
                            {{ number_format($this->profitAnalysis['profit_margin'], 1) }}% margin
                        </p>
                    </div>
                    <flux:icon name="chart-bar" class="size-8 text-green-200" />
                </div>
            </div>

            {{-- Units Sold --}}
            <div class="bg-gradient-to-br from-purple-500 to-purple-600 rounded-xl shadow-sm p-6 text-white">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-purple-100 text-sm font-medium">Units Sold</p>
                        <p class="text-3xl font-bold">{{ number_format($this->profitAnalysis['total_sold']) }}</p>
                        <p class="text-sm text-purple-100 mt-1">
                            {{ number_format($this->profitAnalysis['total_sold'] / max($this->period, 1), 1) }} per day
                        </p>
                    </div>
                    <flux:icon name="cube" class="size-8 text-purple-200" />
                </div>
            </div>

            {{-- Stock Status --}}
            <div class="bg-gradient-to-br from-orange-500 to-orange-600 rounded-xl shadow-sm p-6 text-white">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-orange-100 text-sm font-medium">Current Stock</p>
                        <p class="text-3xl font-bold">{{ number_format($this->stockInfo['current_stock']) }}</p>
                        <p class="text-sm text-orange-100 mt-1">
                            @if($this->stockInfo['stock_status'] === 'out_of_stock')
                                Out of stock
                            @elseif($this->stockInfo['stock_status'] === 'low_stock')
                                Low stock
                            @else
                                In stock
                            @endif
                        </p>
                    </div>
                    <flux:icon name="archive-box" class="size-8 text-orange-200" />
                </div>
            </div>
        </div>

        {{-- Sales Trend Chart --}}
        <div class="bg-white dark:bg-zinc-800 rounded-xl shadow-sm border border-zinc-200 dark:border-zinc-700 p-6">
            <div class="flex items-center justify-between mb-6">
                <flux:heading size="lg" class="text-zinc-900 dark:text-zinc-100">
                    Sales Trend
                </flux:heading>
                <flux:badge color="blue" size="sm">
                    {{ $this->period }} days
                </flux:badge>
            </div>
            
            <div class="h-64">
                <livewire:charts.area-chart 
                    :data="[
                        'labels' => $this->salesTrend->pluck('date')->toArray(),
                        'datasets' => [
                            [
                                'label' => 'Units Sold',
                                'data' => $this->salesTrend->pluck('quantity')->toArray(),
                                'borderColor' => 'rgb(59, 130, 246)',
                                'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                                'fill' => true,
                            ]
                        ]
                    ]"
                    :options="[
                        'responsive' => true,
                        'maintainAspectRatio' => false,
                        'plugins' => [
                            'legend' => ['display' => false],
                            'tooltip' => [
                                'callbacks' => [
                                    'afterBody' => 'function(context) { 
                                        var index = context[0].dataIndex; 
                                        var revenue = ' . json_encode($this->salesTrend->pluck('revenue')->toArray()) . '[index];
                                        return "Revenue: £" + revenue.toFixed(2);
                                    }'
                                ]
                            ]
                        ],
                        'scales' => [
                            'y' => [
                                'beginAtZero' => true,
                                'ticks' => ['precision' => 0]
                            ]
                        ]
                    ]"
                />
            </div>
        </div>

        {{-- Two Column Layout --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            {{-- Channel Performance --}}
            <div class="bg-white dark:bg-zinc-800 rounded-xl shadow-sm border border-zinc-200 dark:border-zinc-700 p-6">
                <flux:heading size="lg" class="text-zinc-900 dark:text-zinc-100 mb-6">
                    Channel Performance
                </flux:heading>
                
                @if($this->channelPerformance->isNotEmpty())
                    <div class="space-y-4">
                        @foreach($this->channelPerformance as $channel)
                            <div class="flex items-center justify-between p-4 rounded-lg bg-zinc-50 dark:bg-zinc-700 border border-zinc-200 dark:border-zinc-600">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-full bg-blue-100 dark:bg-blue-900 flex items-center justify-center">
                                        <flux:icon name="shopping-cart" class="size-5 text-blue-600 dark:text-blue-400" />
                                    </div>
                                    <div>
                                        <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $channel['channel'] }}</div>
                                        <div class="text-sm text-zinc-600 dark:text-zinc-400">{{ $channel['order_count'] }} orders</div>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <div class="font-bold text-zinc-900 dark:text-zinc-100">£{{ number_format($channel['revenue'], 2) }}</div>
                                    <div class="text-sm text-zinc-600 dark:text-zinc-400">{{ $channel['quantity_sold'] }} units</div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="text-center py-8 text-zinc-500 dark:text-zinc-400">
                        <flux:icon name="chart-bar" class="size-12 mx-auto mb-4 text-zinc-300 dark:text-zinc-600" />
                        <p>No channel data available</p>
                    </div>
                @endif
            </div>

            {{-- Recent Orders --}}
            <div class="bg-white dark:bg-zinc-800 rounded-xl shadow-sm border border-zinc-200 dark:border-zinc-700 p-6">
                <flux:heading size="lg" class="text-zinc-900 dark:text-zinc-100 mb-6">
                    Recent Orders
                </flux:heading>
                
                @if($this->recentOrders->isNotEmpty())
                    <div class="space-y-3">
                        @foreach($this->recentOrders as $order)
                            <div class="flex items-center justify-between p-3 rounded-lg bg-zinc-50 dark:bg-zinc-700 border border-zinc-200 dark:border-zinc-600">
                                <div>
                                    <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $order['order_number'] }}</div>
                                    <div class="text-sm text-zinc-600 dark:text-zinc-400">{{ $order['date'] }} • {{ $order['channel'] }}</div>
                                </div>
                                <div class="text-right">
                                    <div class="font-bold text-zinc-900 dark:text-zinc-100">£{{ number_format($order['revenue'], 2) }}</div>
                                    <div class="text-sm text-zinc-600 dark:text-zinc-400">{{ $order['quantity'] }} × £{{ number_format($order['price_per_unit'], 2) }}</div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="text-center py-8 text-zinc-500 dark:text-zinc-400">
                        <flux:icon name="shopping-bag" class="size-12 mx-auto mb-4 text-zinc-300 dark:text-zinc-600" />
                        <p>No recent orders</p>
                    </div>
                @endif
            </div>
        </div>

        {{-- Detailed Analysis --}}
        <div class="bg-white dark:bg-zinc-800 rounded-xl shadow-sm border border-zinc-200 dark:border-zinc-700 p-6">
            <flux:heading size="lg" class="text-zinc-900 dark:text-zinc-100 mb-6">
                Detailed Analysis
            </flux:heading>
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                {{-- Profit Analysis --}}
                <div class="p-4 rounded-lg bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800">
                    <div class="flex items-center gap-3 mb-3">
                        <flux:icon name="currency-pound" class="size-6 text-green-600 dark:text-green-400" />
                        <h3 class="font-medium text-green-900 dark:text-green-100">Profit Analysis</h3>
                    </div>
                    <div class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <span class="text-green-700 dark:text-green-300">Revenue:</span>
                            <span class="font-medium text-green-900 dark:text-green-100">£{{ number_format($this->profitAnalysis['total_revenue'], 2) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-green-700 dark:text-green-300">Cost:</span>
                            <span class="font-medium text-green-900 dark:text-green-100">£{{ number_format($this->profitAnalysis['total_cost'], 2) }}</span>
                        </div>
                        <div class="flex justify-between border-t border-green-200 dark:border-green-800 pt-2">
                            <span class="text-green-700 dark:text-green-300">Profit:</span>
                            <span class="font-bold text-green-900 dark:text-green-100">£{{ number_format($this->profitAnalysis['total_profit'], 2) }}</span>
                        </div>
                    </div>
                </div>

                {{-- Pricing Analysis --}}
                <div class="p-4 rounded-lg bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800">
                    <div class="flex items-center gap-3 mb-3">
                        <flux:icon name="tag" class="size-6 text-blue-600 dark:text-blue-400" />
                        <h3 class="font-medium text-blue-900 dark:text-blue-100">Pricing Analysis</h3>
                    </div>
                    <div class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <span class="text-blue-700 dark:text-blue-300">Avg Selling Price:</span>
                            <span class="font-medium text-blue-900 dark:text-blue-100">£{{ number_format($this->profitAnalysis['avg_selling_price'], 2) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-blue-700 dark:text-blue-300">Avg Unit Cost:</span>
                            <span class="font-medium text-blue-900 dark:text-blue-100">£{{ number_format($this->profitAnalysis['avg_unit_cost'], 2) }}</span>
                        </div>
                        <div class="flex justify-between border-t border-blue-200 dark:border-blue-800 pt-2">
                            <span class="text-blue-700 dark:text-blue-300">Markup:</span>
                            <span class="font-bold text-blue-900 dark:text-blue-100">
                                {{ $this->profitAnalysis['avg_unit_cost'] > 0 ? number_format((($this->profitAnalysis['avg_selling_price'] - $this->profitAnalysis['avg_unit_cost']) / $this->profitAnalysis['avg_unit_cost']) * 100, 1) : 0 }}%
                            </span>
                        </div>
                    </div>
                </div>

                {{-- Stock Analysis --}}
                <div class="p-4 rounded-lg bg-orange-50 dark:bg-orange-900/20 border border-orange-200 dark:border-orange-800">
                    <div class="flex items-center gap-3 mb-3">
                        <flux:icon name="archive-box" class="size-6 text-orange-600 dark:text-orange-400" />
                        <h3 class="font-medium text-orange-900 dark:text-orange-100">Stock Analysis</h3>
                    </div>
                    <div class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <span class="text-orange-700 dark:text-orange-300">Current Stock:</span>
                            <span class="font-medium text-orange-900 dark:text-orange-100">{{ number_format($this->stockInfo['current_stock']) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-orange-700 dark:text-orange-300">Minimum Stock:</span>
                            <span class="font-medium text-orange-900 dark:text-orange-100">{{ number_format($this->stockInfo['minimum_stock']) }}</span>
                        </div>
                        <div class="flex justify-between border-t border-orange-200 dark:border-orange-800 pt-2">
                            <span class="text-orange-700 dark:text-orange-300">Status:</span>
                            <flux:badge 
                                color="{{ $this->stockInfo['stock_status'] === 'out_of_stock' ? 'red' : ($this->stockInfo['stock_status'] === 'low_stock' ? 'yellow' : 'green') }}" 
                                size="sm"
                            >
                                {{ ucfirst(str_replace('_', ' ', $this->stockInfo['stock_status'])) }}
                            </flux:badge>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Footer --}}
        <div class="text-center text-sm text-zinc-600 dark:text-zinc-400">
            Last updated: {{ now()->format('M j, Y g:i A') }}
        </div>
    </div>
</div>