<div class="min-h-screen bg-zinc-50 dark:bg-zinc-900">
    <div class="space-y-6 p-6">
        {{-- Condensed Header with Controls --}}
        <div class="bg-white dark:bg-zinc-800 rounded-xl shadow-sm border border-zinc-200 dark:border-zinc-700 p-4">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                <div class="flex items-center gap-6">
                    <div>
                        <flux:heading size="lg" class="text-zinc-900 dark:text-zinc-100">Sales Dashboard</flux:heading>
                        <div class="flex items-center gap-2 text-sm text-zinc-600 dark:text-zinc-400 mt-1">
                            <span>{{ $this->periodSummary->get('period_label') }}</span>
                            <span class="text-zinc-400">•</span>
                            <span class="font-medium text-zinc-900 dark:text-zinc-100">
                                {{ number_format($this->metrics->get('total_orders')) }} orders
                            </span>
                        </div>
                    </div>
                    
                    {{-- Sync Status Inline --}}
                    <div class="hidden lg:flex items-center gap-3 text-sm">
                        <flux:icon name="arrow-path" class="size-4 text-zinc-500" />
                        <span class="text-zinc-600 dark:text-zinc-400">{{ $this->lastSyncInfo->get('time_human') }}</span>
                        @if($this->lastSyncInfo->get('status') === 'success')
                            <flux:badge color="green" size="sm">
                                {{ number_format($this->lastSyncInfo->get('success_rate'), 1) }}% success
                            </flux:badge>
                        @endif
                    </div>
                </div>
                
                {{-- Controls --}}
                <div class="flex flex-wrap gap-2">
                    <flux:input 
                        wire:model.live="searchTerm" 
                        placeholder="Search orders..." 
                        class="min-w-48"
                        size="sm"
                    />
                    
                    <flux:select wire:model.live="period" size="sm" class="min-w-32">
                        <flux:select.option value="1">24h</flux:select.option>
                        <flux:select.option value="7">7 days</flux:select.option>
                        <flux:select.option value="30">30 days</flux:select.option>
                        <flux:select.option value="90">90 days</flux:select.option>
                    </flux:select>
                    
                    <flux:select wire:model.live="channel" size="sm" class="min-w-32">
                        <flux:select.option value="all">All Channels</flux:select.option>
                        @foreach($this->availableChannels as $channelOption)
                            <flux:select.option value="{{ $channelOption->get('name') }}">
                                {{ $channelOption->get('label') }}
                            </flux:select.option>
                        @endforeach
                    </flux:select>
                    
                    <flux:button variant="primary" size="sm" wire:click="syncOrders" icon="cloud-arrow-down">
                        Sync
                    </flux:button>
                    
                    <flux:button variant="ghost" size="sm" wire:click="$refresh" icon="arrow-path">
                        Refresh
                    </flux:button>
                </div>
            </div>
        </div>

        {{-- Key Metrics Grid --}}
        @if($showMetrics)
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                {{-- Total Revenue --}}
                <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl shadow-sm p-6 text-white">
                    <div class="flex items-center justify-between">
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
                <div class="bg-gradient-to-br from-emerald-500 to-emerald-600 rounded-xl shadow-sm p-6 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-emerald-100 text-sm font-medium">Total Orders</p>
                            <p class="text-3xl font-bold">{{ number_format($this->metrics->get('total_orders')) }}</p>
                            <p class="text-sm text-emerald-100 mt-1">
                                {{ number_format($this->periodSummary->get('orders_per_day'), 1) }} per day
                            </p>
                        </div>
                        <flux:icon name="shopping-bag" class="size-8 text-emerald-200" />
                    </div>
                </div>

                {{-- Average Order Value --}}
                <div class="bg-gradient-to-br from-purple-500 to-purple-600 rounded-xl shadow-sm p-6 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-purple-100 text-sm font-medium">Average Order</p>
                            <p class="text-3xl font-bold">£{{ number_format($this->metrics->get('average_order_value'), 0) }}</p>
                            <p class="text-sm text-purple-100 mt-1">Per order value</p>
                        </div>
                        <flux:icon name="calculator" class="size-8 text-purple-200" />
                    </div>
                </div>

                {{-- Total Items --}}
                <div class="bg-gradient-to-br from-orange-500 to-orange-600 rounded-xl shadow-sm p-6 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-orange-100 text-sm font-medium">Items Sold</p>
                            <p class="text-3xl font-bold">{{ number_format($this->metrics->get('total_items')) }}</p>
                            <p class="text-sm text-orange-100 mt-1">Total units</p>
                        </div>
                        <flux:icon name="cube" class="size-8 text-orange-200" />
                    </div>
                </div>
            </div>
        @endif

        {{-- Charts Section --}}
        @if($showCharts)
            <div class="bg-white dark:bg-zinc-800 rounded-xl shadow-sm border border-zinc-200 dark:border-zinc-700 p-6">
                <div class="flex items-center justify-between mb-6">
                    <flux:heading size="lg" class="text-zinc-900 dark:text-zinc-100">Daily Sales</flux:heading>
                    <flux:button variant="ghost" size="sm" wire:click="toggleCharts" icon="eye-slash">
                        Hide Charts
                    </flux:button>
                </div>
                <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-7 gap-4">
                    @foreach($this->dailySalesChart as $day)
                        <div class="text-center p-3 rounded-lg bg-zinc-50 dark:bg-zinc-700 border border-zinc-200 dark:border-zinc-600">
                            <div class="text-xs text-zinc-600 dark:text-zinc-400 font-medium">{{ $day->get('day') }}</div>
                            <div class="text-sm text-zinc-900 dark:text-zinc-100 font-medium">{{ $day->get('date') }}</div>
                            <div class="text-lg font-bold text-zinc-900 dark:text-zinc-100 mt-1">{{ $day->get('orders') }}</div>
                            <div class="text-xs text-zinc-600 dark:text-zinc-400">£{{ number_format($day->get('revenue'), 0) }}</div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Analytics Grid --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            {{-- Top Products --}}
            <div class="bg-white dark:bg-zinc-800 rounded-xl shadow-sm border border-zinc-200 dark:border-zinc-700 p-6">
                <flux:heading size="lg" class="text-zinc-900 dark:text-zinc-100 mb-6">Top Products</flux:heading>
                <div class="space-y-4">
                    @forelse($this->topProducts as $index => $product)
                        <div class="flex items-center justify-between p-3 rounded-lg bg-zinc-50 dark:bg-zinc-700 border border-zinc-200 dark:border-zinc-600">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-full bg-blue-100 dark:bg-blue-900 flex items-center justify-center text-blue-600 dark:text-blue-400 text-sm font-bold">
                                    {{ $index + 1 }}
                                </div>
                                <div>
                                    <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $product->get('title') }}</div>
                                    <div class="text-sm text-zinc-600 dark:text-zinc-400">{{ $product->get('sku') }}</div>
                                </div>
                            </div>
                            <div class="text-right">
                                <div class="font-bold text-zinc-900 dark:text-zinc-100">£{{ number_format($product->get('revenue'), 0) }}</div>
                                <div class="text-sm text-zinc-600 dark:text-zinc-400">{{ $product->get('quantity') }} sold</div>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-8 text-zinc-500 dark:text-zinc-400">
                            <flux:icon name="cube" class="size-12 mx-auto mb-2 text-zinc-300 dark:text-zinc-600" />
                            <p>No products found</p>
                        </div>
                    @endforelse
                </div>
            </div>
            
            {{-- Top Channels --}}
            <div class="bg-white dark:bg-zinc-800 rounded-xl shadow-sm border border-zinc-200 dark:border-zinc-700 p-6">
                <flux:heading size="lg" class="text-zinc-900 dark:text-zinc-100 mb-6">Top Channels</flux:heading>
                <div class="space-y-4">
                    @forelse($this->topChannels as $index => $channel)
                        <div class="flex items-center justify-between p-3 rounded-lg bg-zinc-50 dark:bg-zinc-700 border border-zinc-200 dark:border-zinc-600">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-full bg-emerald-100 dark:bg-emerald-900 flex items-center justify-center text-emerald-600 dark:text-emerald-400 text-sm font-bold">
                                    {{ $index + 1 }}
                                </div>
                                <div>
                                    <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $channel->get('name') }}</div>
                                    <div class="text-sm text-zinc-600 dark:text-zinc-400">£{{ number_format($channel->get('avg_order_value'), 0) }} avg</div>
                                </div>
                            </div>
                            <div class="text-right">
                                <div class="font-bold text-zinc-900 dark:text-zinc-100">£{{ number_format($channel->get('revenue'), 0) }}</div>
                                <div class="text-sm text-zinc-600 dark:text-zinc-400">{{ $channel->get('orders') }} orders</div>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-8 text-zinc-500 dark:text-zinc-400">
                            <flux:icon name="chart-bar" class="size-12 mx-auto mb-2 text-zinc-300 dark:text-zinc-600" />
                            <p>No channels found</p>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Recent Orders Table --}}
        <div class="bg-white dark:bg-zinc-800 rounded-xl shadow-sm border border-zinc-200 dark:border-zinc-700">
            <div class="p-6 border-b border-zinc-200 dark:border-zinc-700">
                <div class="flex items-center justify-between">
                    <div>
                        <flux:heading size="lg" class="text-zinc-900 dark:text-zinc-100">Recent Orders</flux:heading>
                        <p class="text-sm text-zinc-600 dark:text-zinc-400 mt-1">
                            Showing {{ $this->recentOrders->count() }} of {{ number_format($this->metrics->get('total_orders')) }} orders
                        </p>
                    </div>
                    <div class="flex gap-2">
                        <flux:button variant="ghost" size="sm" wire:click="toggleMetrics" icon="{{ $showMetrics ? 'eye-slash' : 'eye' }}">
                            {{ $showMetrics ? 'Hide' : 'Show' }} Metrics
                        </flux:button>
                        @if(!$showCharts)
                            <flux:button variant="ghost" size="sm" wire:click="toggleCharts" icon="chart-bar">
                                Show Charts
                            </flux:button>
                        @endif
                    </div>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-zinc-50 dark:bg-zinc-700 border-b border-zinc-200 dark:border-zinc-600">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">Order</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">Channel</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">Value</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                        @forelse($this->recentOrders as $order)
                            <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-700 transition-colors">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100">#{{ $order->order_number }}</div>
                                    <div class="text-sm text-zinc-600 dark:text-zinc-400">{{ Str::limit($order->linnworks_order_id, 8) }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-zinc-900 dark:text-zinc-100">{{ $order->received_date?->format('M j, Y') }}</div>
                                    <div class="text-sm text-zinc-600 dark:text-zinc-400">{{ $order->received_date?->format('g:i A') }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-zinc-100 dark:bg-zinc-600 text-zinc-800 dark:text-zinc-200">
                                        {{ $order->channel_name }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100">£{{ number_format($order->total_charge, 2) }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <flux:badge color="{{ $order->is_open ? 'blue' : 'zinc' }}" size="sm">
                                        {{ $order->is_open ? 'Open' : 'Closed' }}
                                    </flux:badge>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-12 text-center">
                                    <div class="text-zinc-500 dark:text-zinc-400">
                                        <flux:icon name="shopping-bag" class="size-12 mx-auto mb-4 text-zinc-300 dark:text-zinc-600" />
                                        <p class="text-lg font-medium">No orders found</p>
                                        <p class="text-sm">Try adjusting your filters or sync some orders</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>