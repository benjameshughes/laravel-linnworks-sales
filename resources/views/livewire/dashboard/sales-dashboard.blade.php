<div>
    <div class="space-y-6">
        {{-- Header Section --}}
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
            <div>
                <flux:heading size="xl" class="text-gray-900 dark:text-white">Sales Dashboard</flux:heading>
                <flux:subheading class="text-gray-600 dark:text-gray-400">
                    Monitor your sales performance and key metrics
                </flux:subheading>
            </div>
            
            {{-- Filters --}}
            <div class="flex flex-col sm:flex-row gap-3">
                <flux:select wire:model.live="period" placeholder="Select period" class="min-w-36">
                    <flux:select.option value="7">Last 7 days</flux:select.option>
                    <flux:select.option value="30">Last 30 days</flux:select.option>
                    <flux:select.option value="90">Last 90 days</flux:select.option>
                    <flux:select.option value="365">Last year</flux:select.option>
                </flux:select>
                
                <flux:select wire:model.live="channel" placeholder="All channels" class="min-w-36">
                    <flux:select.option value="all">All Channels</flux:select.option>
                    @foreach($this->availableChannels as $channelOption)
                        <flux:select.option value="{{ $channelOption->name }}">{{ $channelOption->display_name }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>
        </div>

        {{-- Metrics Cards --}}
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            {{-- Total Revenue --}}
            <x-card>
                <div class="flex items-center justify-between">
                    <div>
                        <flux:subheading class="text-gray-600 dark:text-gray-400 text-sm font-medium">
                            Total Revenue
                        </flux:subheading>
                        <flux:heading size="lg" class="text-2xl font-bold text-gray-900 dark:text-white mt-1">
                            £{{ number_format($this->totalRevenue, 2) }}
                        </flux:heading>
                        <div class="flex items-center mt-2">
                            @if($this->revenueGrowth >= 0)
                                <flux:icon.trending-up class="size-4 text-green-500" />
                                <span class="text-green-600 text-sm font-medium ml-1">
                                    +{{ number_format($this->revenueGrowth, 1) }}%
                                </span>
                            @else
                                <flux:icon.trending-down class="size-4 text-red-500" />
                                <span class="text-red-600 text-sm font-medium ml-1">
                                    {{ number_format($this->revenueGrowth, 1) }}%
                                </span>
                            @endif
                            <span class="text-gray-500 text-sm ml-1">vs previous period</span>
                        </div>
                    </div>
                    <div class="p-3 bg-blue-100 dark:bg-blue-900/20 rounded-lg">
                        <flux:icon.currency-pound class="size-6 text-blue-600 dark:text-blue-400" />
                    </div>
                </div>
            </x-card>

            {{-- Total Orders --}}
            <x-card>
                <div class="flex items-center justify-between">
                    <div>
                        <flux:subheading class="text-gray-600 dark:text-gray-400 text-sm font-medium">
                            Total Orders
                        </flux:subheading>
                        <flux:heading size="lg" class="text-2xl font-bold text-gray-900 dark:text-white mt-1">
                            {{ number_format($this->totalOrders) }}
                        </flux:heading>
                        <div class="text-gray-500 text-sm mt-2">
                            {{ $this->period }} day{{ $this->period != 1 ? 's' : '' }}
                        </div>
                    </div>
                    <div class="p-3 bg-green-100 dark:bg-green-900/20 rounded-lg">
                        <flux:icon.shopping-bag class="size-6 text-green-600 dark:text-green-400" />
                    </div>
                </div>
            </x-card>

            {{-- Average Order Value --}}
            <x-card>
                <div class="flex items-center justify-between">
                    <div>
                        <flux:subheading class="text-gray-600 dark:text-gray-400 text-sm font-medium">
                            Average Order Value
                        </flux:subheading>
                        <flux:heading size="lg" class="text-2xl font-bold text-gray-900 dark:text-white mt-1">
                            £{{ number_format($this->averageOrderValue, 2) }}
                        </flux:heading>
                        <div class="text-gray-500 text-sm mt-2">
                            Per order
                        </div>
                    </div>
                    <div class="p-3 bg-purple-100 dark:bg-purple-900/20 rounded-lg">
                        <flux:icon.calculator class="size-6 text-purple-600 dark:text-purple-400" />
                    </div>
                </div>
            </x-card>

            {{-- Total Items Sold --}}
            <x-card>
                <div class="flex items-center justify-between">
                    <div>
                        <flux:subheading class="text-gray-600 dark:text-gray-400 text-sm font-medium">
                            Items Sold
                        </flux:subheading>
                        <flux:heading size="lg" class="text-2xl font-bold text-gray-900 dark:text-white mt-1">
                            {{ number_format($this->totalItems) }}
                        </flux:heading>
                        <div class="text-gray-500 text-sm mt-2">
                            Total units
                        </div>
                    </div>
                    <div class="p-3 bg-orange-100 dark:bg-orange-900/20 rounded-lg">
                        <flux:icon.cube class="size-6 text-orange-600 dark:text-orange-400" />
                    </div>
                </div>
            </x-card>
        </div>

        {{-- Charts and Analytics Section --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            {{-- Sales Chart --}}
            <x-card>
                <div class="flex items-center justify-between mb-6">
                    <flux:heading size="lg">Sales Trend</flux:heading>
                    <flux:badge color="blue" size="sm">Daily Revenue</flux:badge>
                </div>
                
                <div class="h-64" wire:ignore>
                    <canvas id="salesChart" width="400" height="200"></canvas>
                </div>
                
                @script
                <script>
                    const ctx = document.getElementById('salesChart').getContext('2d');
                    const salesData = @json($this->dailySales);
                    
                    const chart = new Chart(ctx, {
                        type: 'line',
                        data: {
                            labels: salesData.map(item => item.date),
                            datasets: [{
                                label: 'Revenue (£)',
                                data: salesData.map(item => item.revenue),
                                borderColor: 'rgb(59, 130, 246)',
                                backgroundColor: 'rgba(59, 130, 246, 0.1)',
                                tension: 0.4,
                                fill: true
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    display: false
                                }
                            },
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    ticks: {
                                        callback: function(value) {
                                            return '£' + value.toLocaleString();
                                        }
                                    }
                                }
                            }
                        }
                    });
                    
                    Livewire.on('period-updated', () => {
                        chart.destroy();
                    });
                    
                    Livewire.on('channel-updated', () => {
                        chart.destroy();
                    });
                </script>
                @endscript
            </x-card>

            {{-- Top Channels --}}
            <x-card>
                <div class="flex items-center justify-between mb-6">
                    <flux:heading size="lg">Top Channels</flux:heading>
                    <flux:badge color="green" size="sm">By Revenue</flux:badge>
                </div>
                
                <div class="space-y-4">
                    @forelse($this->topChannels as $channel)
                        <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-800/50 rounded-lg">
                            <div class="flex items-center space-x-3">
                                <div class="p-2 bg-blue-100 dark:bg-blue-900/20 rounded-lg">
                                    <flux:icon.globe-alt class="size-4 text-blue-600 dark:text-blue-400" />
                                </div>
                                <div>
                                    <div class="font-medium text-gray-900 dark:text-white">
                                        {{ $channel->channel_name }}
                                    </div>
                                    <div class="text-sm text-gray-500">
                                        {{ number_format($channel->total_orders) }} orders
                                    </div>
                                </div>
                            </div>
                            <div class="text-right">
                                <div class="font-semibold text-gray-900 dark:text-white">
                                    £{{ number_format($channel->total_revenue, 2) }}
                                </div>
                                <div class="text-sm text-gray-500">
                                    £{{ number_format($channel->avg_order_value, 2) }} avg
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-8 text-gray-500">
                            No channel data available
                        </div>
                    @endforelse
                </div>
            </x-card>
        </div>

        {{-- Tables Section --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            {{-- Top Products --}}
            <x-card>
                <div class="flex items-center justify-between mb-6">
                    <flux:heading size="lg">Top Products</flux:heading>
                    <flux:badge color="purple" size="sm">Best Sellers</flux:badge>
                </div>
                
                <div class="overflow-hidden">
                    <x-table>
                        <x-table.header>
                            <x-table.row>
                                <x-table.header-cell>Product</x-table.header-cell>
                                <x-table.header-cell>Sold</x-table.header-cell>
                                <x-table.header-cell>Revenue</x-table.header-cell>
                            </x-table.row>
                        </x-table.header>
                        
                        <x-table.body>
                            @forelse($this->topProducts as $product)
                                <x-table.row>
                                    <x-table.cell>
                                        <div>
                                            <div class="font-medium text-gray-900 dark:text-white">
                                                {{ Str::limit($product->title, 30) }}
                                            </div>
                                            <div class="text-sm text-gray-500">
                                                {{ $product->sku }}
                                            </div>
                                        </div>
                                    </x-table.cell>
                                    <x-table.cell>
                                        <flux:badge color="blue" size="sm">
                                            {{ number_format($product->total_quantity) }}
                                        </flux:badge>
                                    </x-table.cell>
                                    <x-table.cell>
                                        <div class="font-semibold text-gray-900 dark:text-white">
                                            £{{ number_format($product->total_revenue, 2) }}
                                        </div>
                                    </x-table.cell>
                                </x-table.row>
                            @empty
                                <x-table.row>
                                    <x-table.cell colspan="3" class="text-center py-8 text-gray-500">
                                        No product data available
                                    </x-table.cell>
                                </x-table.row>
                            @endforelse
                        </x-table.body>
                    </x-table>
                </div>
            </x-card>

            {{-- Recent Orders --}}
            <x-card>
                <div class="flex items-center justify-between mb-6">
                    <flux:heading size="lg">Recent Orders</flux:heading>
                    <flux:badge color="orange" size="sm">Latest</flux:badge>
                </div>
                
                <div class="space-y-3">
                    @forelse($this->recentOrders as $order)
                        <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-800/50 rounded-lg">
                            <div class="flex items-center space-x-3">
                                <div class="p-2 bg-gray-100 dark:bg-gray-700 rounded-lg">
                                    <flux:icon.shopping-cart class="size-4 text-gray-600 dark:text-gray-400" />
                                </div>
                                <div>
                                    <div class="font-medium text-gray-900 dark:text-white">
                                        #{{ $order->order_number }}
                                    </div>
                                    <div class="text-sm text-gray-500">
                                        {{ $order->channel_name }} • {{ $order->received_date->format('M j, H:i') }}
                                    </div>
                                </div>
                            </div>
                            <div class="text-right">
                                <div class="font-semibold text-gray-900 dark:text-white">
                                    £{{ number_format($order->total_paid, 2) }}
                                </div>
                                <div class="text-sm text-gray-500">
                                    {{ $order->items->count() }} item{{ $order->items->count() != 1 ? 's' : '' }}
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-8 text-gray-500">
                            No recent orders
                        </div>
                    @endforelse
                </div>
            </x-card>
        </div>
    </div>
</div>

@script
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
@endscript