<div class="min-h-screen">
    <div class="space-y-3 p-3 lg:p-4">
        {{-- Header with Product Info --}}
        <div class="bg-white dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700 p-3">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3">
                <div class="flex items-center gap-4">
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
                            <div class="flex flex-wrap gap-2 mt-2">
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
                        @foreach(\App\Enums\Period::all() as $periodOption)
                            @if($periodOption->value !== 'custom')
                                <flux:select.option value="{{ $periodOption->value }}">
                                    {{ $periodOption->label() }}
                                </flux:select.option>
                            @endif
                        @endforeach
                    </flux:select>

                    <flux:button variant="ghost" size="sm" wire:click="$refresh" icon="arrow-path">
                        Refresh
                    </flux:button>
                </div>
            </div>
        </div>

        {{-- Key Metrics Grid - Expandable Cards --}}
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-3">
            {{-- Total Revenue - Expandable --}}
            <div
                x-data="{ expanded: false }"
                @click="expanded = !expanded"
                class="bg-white dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700 p-3 cursor-pointer transition-all duration-200 hover:border-zinc-300 dark:hover:border-zinc-600"
            >
                <div class="flex items-center justify-between">
                    <div>
                        <div class="flex items-center gap-2">
                            <p class="text-zinc-600 dark:text-zinc-400 text-sm font-medium">Total Revenue</p>
                            <flux:icon name="chevron-down" class="size-3 text-zinc-400 transition-transform duration-300" ::class="expanded && 'rotate-180'" />
                        </div>
                        <p class="text-3xl font-bold text-emerald-600 dark:text-emerald-400">£{{ number_format($this->profitAnalysis['total_revenue'], 2) }}</p>
                        <p class="text-sm text-zinc-500 dark:text-zinc-400 mt-1">
                            £{{ number_format($this->profitAnalysis['avg_selling_price'], 2) }} avg price
                        </p>
                    </div>
                    <flux:icon name="currency-pound" class="size-8 text-blue-500" />
                </div>

                {{-- Expanded Details --}}
                <div
                    x-show="expanded"
                    x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="opacity-0 -translate-y-2"
                    x-transition:enter-end="opacity-100 translate-y-0"
                    x-transition:leave="transition ease-in duration-150"
                    x-transition:leave-start="opacity-100 translate-y-0"
                    x-transition:leave-end="opacity-0 -translate-y-2"
                    class="mt-3 pt-3 border-t border-zinc-200 dark:border-zinc-700 space-y-2"
                    @click.stop
                >
                    <div class="flex justify-between text-sm">
                        <span class="text-zinc-500 dark:text-zinc-400">Avg Selling Price:</span>
                        <span class="font-medium text-zinc-900 dark:text-zinc-100">£{{ number_format($this->profitAnalysis['avg_selling_price'], 2) }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-zinc-500 dark:text-zinc-400">Avg Unit Cost:</span>
                        <span class="font-medium text-zinc-900 dark:text-zinc-100">£{{ number_format($this->profitAnalysis['avg_unit_cost'], 2) }}</span>
                    </div>
                    <div class="flex justify-between text-sm border-t border-zinc-200 dark:border-zinc-700 pt-2">
                        <span class="text-zinc-500 dark:text-zinc-400">Markup:</span>
                        <span class="font-bold text-zinc-900 dark:text-zinc-100">
                            {{ $this->profitAnalysis['avg_unit_cost'] > 0 ? number_format((($this->profitAnalysis['avg_selling_price'] - $this->profitAnalysis['avg_unit_cost']) / $this->profitAnalysis['avg_unit_cost']) * 100, 1) : 0 }}%
                        </span>
                    </div>
                </div>
            </div>

            {{-- Total Profit - Expandable --}}
            <div
                x-data="{ expanded: false }"
                @click="expanded = !expanded"
                class="bg-white dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700 p-3 cursor-pointer transition-all duration-200 hover:border-zinc-300 dark:hover:border-zinc-600"
            >
                <div class="flex items-center justify-between">
                    <div>
                        <div class="flex items-center gap-2">
                            <p class="text-zinc-600 dark:text-zinc-400 text-sm font-medium">Total Profit</p>
                            <flux:icon name="chevron-down" class="size-3 text-zinc-400 transition-transform duration-300" ::class="expanded && 'rotate-180'" />
                        </div>
                        <p class="text-3xl font-bold {{ $this->profitAnalysis['total_profit'] >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400' }}">£{{ number_format($this->profitAnalysis['total_profit'], 2) }}</p>
                        <p class="text-sm text-zinc-500 dark:text-zinc-400 mt-1">
                            {{ number_format($this->profitAnalysis['profit_margin'], 1) }}% margin
                        </p>
                    </div>
                    <flux:icon name="chart-bar" class="size-8 text-emerald-500" />
                </div>

                {{-- Expanded Details --}}
                <div
                    x-show="expanded"
                    x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="opacity-0 -translate-y-2"
                    x-transition:enter-end="opacity-100 translate-y-0"
                    x-transition:leave="transition ease-in duration-150"
                    x-transition:leave-start="opacity-100 translate-y-0"
                    x-transition:leave-end="opacity-0 -translate-y-2"
                    class="mt-3 pt-3 border-t border-zinc-200 dark:border-zinc-700 space-y-2"
                    @click.stop
                >
                    <div class="flex justify-between text-sm">
                        <span class="text-zinc-500 dark:text-zinc-400">Revenue:</span>
                        <span class="font-medium text-zinc-900 dark:text-zinc-100">£{{ number_format($this->profitAnalysis['total_revenue'], 2) }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-zinc-500 dark:text-zinc-400">Cost:</span>
                        <span class="font-medium text-zinc-900 dark:text-zinc-100">£{{ number_format($this->profitAnalysis['total_cost'], 2) }}</span>
                    </div>
                    <div class="flex justify-between text-sm border-t border-zinc-200 dark:border-zinc-700 pt-2">
                        <span class="text-zinc-500 dark:text-zinc-400">Profit:</span>
                        <span class="font-bold {{ $this->profitAnalysis['total_profit'] >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400' }}">£{{ number_format($this->profitAnalysis['total_profit'], 2) }}</span>
                    </div>
                </div>
            </div>

            {{-- Units Sold - Expandable --}}
            <div
                x-data="{ expanded: false }"
                @click="expanded = !expanded"
                class="bg-white dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700 p-3 cursor-pointer transition-all duration-200 hover:border-zinc-300 dark:hover:border-zinc-600"
            >
                <div class="flex items-center justify-between">
                    <div>
                        <div class="flex items-center gap-2">
                            <p class="text-zinc-600 dark:text-zinc-400 text-sm font-medium">Units Sold</p>
                            <flux:icon name="chevron-down" class="size-3 text-zinc-400 transition-transform duration-300" ::class="expanded && 'rotate-180'" />
                        </div>
                        <p class="text-3xl font-bold text-zinc-900 dark:text-zinc-100">{{ number_format($this->profitAnalysis['total_sold']) }}</p>
                        <p class="text-sm text-zinc-500 dark:text-zinc-400 mt-1">
                            {{ number_format($this->profitAnalysis['total_sold'] / max($this->period, 1), 1) }} per day
                        </p>
                    </div>
                    <flux:icon name="cube" class="size-8 text-purple-500" />
                </div>

                {{-- Expanded Details --}}
                <div
                    x-show="expanded"
                    x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="opacity-0 -translate-y-2"
                    x-transition:enter-end="opacity-100 translate-y-0"
                    x-transition:leave="transition ease-in duration-150"
                    x-transition:leave-start="opacity-100 translate-y-0"
                    x-transition:leave-end="opacity-0 -translate-y-2"
                    class="mt-3 pt-3 border-t border-zinc-200 dark:border-zinc-700 space-y-2"
                    @click.stop
                >
                    <div class="flex justify-between text-sm">
                        <span class="text-zinc-500 dark:text-zinc-400">Total Units:</span>
                        <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ number_format($this->profitAnalysis['total_sold']) }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-zinc-500 dark:text-zinc-400">Daily Average:</span>
                        <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ number_format($this->profitAnalysis['total_sold'] / max($this->period, 1), 1) }}</span>
                    </div>
                    <div class="flex justify-between text-sm border-t border-zinc-200 dark:border-zinc-700 pt-2">
                        <span class="text-zinc-500 dark:text-zinc-400">Total Orders:</span>
                        <span class="font-bold text-zinc-900 dark:text-zinc-100">{{ number_format($this->profitAnalysis['order_count'] ?? 0) }}</span>
                    </div>
                </div>
            </div>

            {{-- Stock Status - Expandable --}}
            <div
                x-data="{ expanded: false }"
                @click="expanded = !expanded"
                class="bg-white dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700 p-3 cursor-pointer transition-all duration-200 hover:border-zinc-300 dark:hover:border-zinc-600"
            >
                <div class="flex items-center justify-between">
                    <div>
                        <div class="flex items-center gap-2">
                            <p class="text-zinc-600 dark:text-zinc-400 text-sm font-medium">Current Stock</p>
                            <flux:icon name="chevron-down" class="size-3 text-zinc-400 transition-transform duration-300" ::class="expanded && 'rotate-180'" />
                        </div>
                        <p class="text-3xl font-bold text-zinc-900 dark:text-zinc-100">{{ number_format($this->stockInfo['current_stock']) }}</p>
                        <p class="text-sm mt-1 {{ $this->stockInfo['stock_status'] === 'out_of_stock' ? 'text-red-500' : ($this->stockInfo['stock_status'] === 'low_stock' ? 'text-amber-500' : 'text-zinc-500 dark:text-zinc-400') }}">
                            @if($this->stockInfo['stock_status'] === 'out_of_stock')
                                Out of stock
                            @elseif($this->stockInfo['stock_status'] === 'low_stock')
                                Low stock
                            @else
                                In stock
                            @endif
                        </p>
                    </div>
                    <flux:icon name="archive-box" class="size-8 text-amber-500" />
                </div>

                {{-- Expanded Details --}}
                <div
                    x-show="expanded"
                    x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="opacity-0 -translate-y-2"
                    x-transition:enter-end="opacity-100 translate-y-0"
                    x-transition:leave="transition ease-in duration-150"
                    x-transition:leave-start="opacity-100 translate-y-0"
                    x-transition:leave-end="opacity-0 -translate-y-2"
                    class="mt-3 pt-3 border-t border-zinc-200 dark:border-zinc-700 space-y-2"
                    @click.stop
                >
                    <div class="flex justify-between text-sm">
                        <span class="text-zinc-500 dark:text-zinc-400">Current Stock:</span>
                        <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ number_format($this->stockInfo['current_stock']) }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-zinc-500 dark:text-zinc-400">Minimum Stock:</span>
                        <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ number_format($this->stockInfo['minimum_stock']) }}</span>
                    </div>
                    <div class="flex justify-between text-sm border-t border-zinc-200 dark:border-zinc-700 pt-2">
                        <span class="text-zinc-500 dark:text-zinc-400">Days of Stock:</span>
                        <span class="font-bold text-zinc-900 dark:text-zinc-100">
                            @php
                                $dailySales = $this->profitAnalysis['total_sold'] / max($this->period, 1);
                                $daysOfStock = $dailySales > 0 ? round($this->stockInfo['current_stock'] / $dailySales) : '∞';
                            @endphp
                            {{ $daysOfStock }}
                        </span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Sales Trend Chart --}}
        <div class="bg-white dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700 p-3">
            <div class="flex items-center justify-between mb-3">
                <flux:heading size="lg" class="text-zinc-900 dark:text-zinc-100">
                    Sales Trend
                </flux:heading>
                <flux:badge color="blue" size="sm">
                    {{ $this->period }} days
                </flux:badge>
            </div>

            @if($this->salesTrend->isEmpty())
                <div class="h-64 flex items-center justify-center text-zinc-500 dark:text-zinc-400">
                    <div class="text-center">
                        <flux:icon name="chart-bar" class="size-12 mx-auto mb-4 text-zinc-300 dark:text-zinc-600" />
                        <p>No sales data available for this period</p>
                    </div>
                </div>
            @else
                {{-- wire:key forces full replacement when data changes, avoiding Chart.js conflicts --}}
                <div
                    wire:key="sales-trend-chart-{{ md5(json_encode($this->salesTrend->toArray())) }}"
                    x-data="{
                        chart: null,
                        init() {
                            const revenueData = @js($this->salesTrend->pluck('revenue')->toArray());
                            this.chart = new Chart(this.$refs.canvas, {
                                type: 'line',
                                data: {
                                    labels: @js($this->salesTrend->pluck('date')->toArray()),
                                    datasets: [{
                                        label: 'Units Sold',
                                        data: @js($this->salesTrend->pluck('quantity')->toArray()),
                                        borderColor: 'rgb(59, 130, 246)',
                                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                                        fill: true,
                                        tension: 0.4
                                    }]
                                },
                                options: {
                                    responsive: true,
                                    maintainAspectRatio: false,
                                    plugins: {
                                        legend: { display: false },
                                        tooltip: {
                                            enabled: true,
                                            mode: 'index',
                                            intersect: false,
                                            callbacks: {
                                                afterBody: function(context) {
                                                    const index = context[0].dataIndex;
                                                    const revenue = revenueData[index];
                                                    return 'Revenue: £' + revenue.toFixed(2);
                                                }
                                            }
                                        }
                                    },
                                    scales: {
                                        y: {
                                            beginAtZero: true,
                                            ticks: { precision: 0 },
                                            grid: { color: 'rgba(0,0,0,0.05)' }
                                        },
                                        x: { grid: { display: false } }
                                    }
                                }
                            });
                        },
                        destroy() {
                            if (this.chart) {
                                this.chart.destroy();
                                this.chart = null;
                            }
                        }
                    }"
                    class="h-64"
                >
                    <canvas x-ref="canvas"></canvas>
                </div>
            @endif
        </div>

        {{-- Two Column Layout --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-3">
            {{-- Channel Performance --}}
            <div class="bg-white dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700 p-3">
                <flux:heading size="lg" class="text-zinc-900 dark:text-zinc-100 mb-3">
                    Channel Performance
                </flux:heading>

                @if($this->channelPerformance->isNotEmpty())
                    <div class="space-y-2">
                        @foreach($this->channelPerformance as $channel)
                            <div class="flex items-center justify-between p-3 rounded-lg bg-zinc-50 dark:bg-zinc-700 border border-zinc-200 dark:border-zinc-600">
                                <div class="flex items-center gap-3">
                                    <div class="w-9 h-9 rounded-full bg-blue-100 dark:bg-blue-900 flex items-center justify-center">
                                        <flux:icon name="shopping-cart" class="size-4 text-blue-600 dark:text-blue-400" />
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
            <div class="bg-white dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700 p-3">
                <flux:heading size="lg" class="text-zinc-900 dark:text-zinc-100 mb-3">
                    Recent Orders
                </flux:heading>

                @if($this->recentOrders->isNotEmpty())
                    <div class="space-y-2">
                        @foreach($this->recentOrders as $order)
                            <div class="flex items-center justify-between p-2 rounded-lg bg-zinc-50 dark:bg-zinc-700 border border-zinc-200 dark:border-zinc-600">
                                <div>
                                    <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $order['number'] }}</div>
                                    <div class="text-sm text-zinc-600 dark:text-zinc-400">{{ $order['date'] }} • {{ $order['channel'] }}</div>
                                </div>
                                <div class="text-right">
                                    <div class="font-bold text-zinc-900 dark:text-zinc-100">£{{ number_format($order['revenue'], 2) }}</div>
                                    <div class="text-sm text-zinc-600 dark:text-zinc-400">{{ $order['quantity'] }} units</div>
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

        {{-- Footer --}}
        <div class="text-center text-sm text-zinc-600 dark:text-zinc-400">
            Last updated: {{ now()->format('M j, Y g:i A') }}
        </div>
    </div>
</div>
