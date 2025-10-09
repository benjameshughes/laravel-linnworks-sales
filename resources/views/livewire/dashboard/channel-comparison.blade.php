<div>
    <div class="space-y-6">
        {{-- Header Section --}}
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
            <div>
                <flux:heading size="xl" class="text-gray-900 dark:text-white">Channel Comparison</flux:heading>
                <flux:subheading class="text-gray-600 dark:text-gray-400">
                    Compare performance across different sales channels
                </flux:subheading>
            </div>
            
            {{-- Controls --}}
            <div class="flex flex-col sm:flex-row gap-3">
                <flux:select wire:model.live="period" placeholder="Select period" class="min-w-36">
                    <flux:select.option value="7">Last 7 days</flux:select.option>
                    <flux:select.option value="30">Last 30 days</flux:select.option>
                    <flux:select.option value="90">Last 90 days</flux:select.option>
                    <flux:select.option value="365">Last year</flux:select.option>
                </flux:select>
                
                <flux:select wire:model.live="metric" placeholder="Sort by" class="min-w-32">
                    <flux:select.option value="revenue">Revenue</flux:select.option>
                    <flux:select.option value="total_orders">Orders</flux:select.option>
                    <flux:select.option value="avg_order_value">AOV</flux:select.option>
                    <flux:select.option value="profit_margin">Margin</flux:select.option>
                </flux:select>
                
                <flux:button variant="outline" size="sm" wire:click="toggleSubsources" class="{{ $showSubsources ? 'bg-blue-50 dark:bg-blue-900/20' : '' }}">
                    <flux:icon name="adjustments-horizontal" class="size-4" />
                    {{ $showSubsources ? 'Hide' : 'Show' }} Subsources
                </flux:button>
                
                @if($selectedChannel)
                    <flux:button variant="outline" size="sm" wire:click="clearSelection">
                        <flux:icon name="x-mark" class="size-4" />
                        Clear Selection
                    </flux:button>
                @endif
            </div>
        </div>

        {{-- Summary Cards --}}
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            {{-- Total Channels --}}
            <x-card>
                <div class="flex items-center justify-between">
                    <div>
                        <flux:subheading class="text-gray-600 dark:text-gray-400 text-sm font-medium">
                            Active Channels
                        </flux:subheading>
                        <flux:heading size="lg" class="text-2xl font-bold text-gray-900 dark:text-white mt-1">
                            {{ $this->channelComparison->count() }}
                        </flux:heading>
                        <div class="text-gray-500 text-sm mt-2">
                            {{ $showSubsources ? 'Including subsources' : 'Main channels only' }}
                        </div>
                    </div>
                    <div class="p-3 bg-blue-100 dark:bg-blue-900/20 rounded-lg">
                        <flux:icon name="globe-alt" class="size-6 text-blue-600 dark:text-blue-400" />
                    </div>
                </div>
            </x-card>

            {{-- Top Channel --}}
            @php $topChannel = $this->channelComparison->first(); @endphp
            @if($topChannel)
                <x-card>
                    <div class="flex items-center justify-between">
                        <div>
                            <flux:subheading class="text-gray-600 dark:text-gray-400 text-sm font-medium">
                                Top Channel
                            </flux:subheading>
                            <flux:heading size="lg" class="text-xl font-bold text-gray-900 dark:text-white mt-1">
                                {{ Str::limit($topChannel['channel'], 20) }}
                            </flux:heading>
                            <div class="text-gray-500 text-sm mt-2">
                                £{{ number_format($topChannel['total_revenue'], 0) }} revenue
                            </div>
                        </div>
                        <div class="p-3 bg-green-100 dark:bg-green-900/20 rounded-lg">
                            <flux:icon name="trophy" class="size-6 text-green-600 dark:text-green-400" />
                        </div>
                    </div>
                </x-card>
            @endif

            {{-- Total Revenue --}}
            <x-card>
                <div class="flex items-center justify-between">
                    <div>
                        <flux:subheading class="text-gray-600 dark:text-gray-400 text-sm font-medium">
                            Total Revenue
                        </flux:subheading>
                        <flux:heading size="lg" class="text-2xl font-bold text-gray-900 dark:text-white mt-1">
                            £{{ number_format($this->channelComparison->sum('total_revenue'), 2) }}
                        </flux:heading>
                        <div class="text-gray-500 text-sm mt-2">
                            All channels
                        </div>
                    </div>
                    <div class="p-3 bg-purple-100 dark:bg-purple-900/20 rounded-lg">
                        <flux:icon name="currency-pound" class="size-6 text-purple-600 dark:text-purple-400" />
                    </div>
                </div>
            </x-card>

            {{-- Avg Profit Margin --}}
            <x-card>
                <div class="flex items-center justify-between">
                    <div>
                        <flux:subheading class="text-gray-600 dark:text-gray-400 text-sm font-medium">
                            Avg Profit Margin
                        </flux:subheading>
                        <flux:heading size="lg" class="text-2xl font-bold text-gray-900 dark:text-white mt-1">
                            {{ number_format($this->channelComparison->avg('profit_margin') ?? 0, 1) }}%
                        </flux:heading>
                        <div class="text-gray-500 text-sm mt-2">
                            Across channels
                        </div>
                    </div>
                    <div class="p-3 bg-orange-100 dark:bg-orange-900/20 rounded-lg">
                        <flux:icon name="chart-bar" class="size-6 text-orange-600 dark:text-orange-400" />
                    </div>
                </div>
            </x-card>
        </div>

        {{-- Charts Section --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            {{-- Channel Performance Chart --}}
            <x-card>
                <div class="flex items-center justify-between mb-6">
                    <flux:heading size="lg">Channel Performance</flux:heading>
                    <flux:badge color="blue" size="sm">
                        {{ ucfirst(str_replace('_', ' ', $metric)) }}
                    </flux:badge>
                </div>
                
                <div class="h-80" wire:ignore
                    x-data="channelChart(@js($this->chartData))"
                    x-init="createChart()"
                    @channel-data-updated.window="setTimeout(() => createChart(), 100)"
                >
                    <canvas width="400" height="300"></canvas>
                </div>
            </x-card>

            {{-- Market Share Pie Chart --}}
            <x-card>
                <div class="flex items-center justify-between mb-6">
                    <flux:heading size="lg">Market Share</flux:heading>
                    <flux:badge color="green" size="sm">Revenue %</flux:badge>
                </div>
                
                <div class="h-80" wire:ignore
                    x-data="marketShareChart(@js($this->chartData))"
                    x-init="createChart()"
                    @channel-data-updated.window="setTimeout(() => createChart(), 100)"
                >
                    <canvas width="400" height="300"></canvas>
                </div>
            </x-card>
        </div>

        {{-- Channel List --}}
        <x-card>
            <div class="flex items-center justify-between mb-6">
                <flux:heading size="lg">Channel Performance Details</flux:heading>
                <div class="text-sm text-gray-500">
                    Sorted by {{ ucfirst(str_replace('_', ' ', $metric)) }}
                </div>
            </div>
            
            <div class="overflow-hidden">
                <x-table>
                    <x-table.header>
                        <x-table.row>
                            <x-table.header-cell>Channel</x-table.header-cell>
                            <x-table.header-cell>Revenue</x-table.header-cell>
                            <x-table.header-cell>Orders</x-table.header-cell>
                            <x-table.header-cell>AOV</x-table.header-cell>
                            <x-table.header-cell>Profit</x-table.header-cell>
                            <x-table.header-cell>Margin</x-table.header-cell>
                            <x-table.header-cell>Share</x-table.header-cell>
                            <x-table.header-cell>Actions</x-table.header-cell>
                        </x-table.row>
                    </x-table.header>
                    
                    <x-table.body>
                        @php $totalRevenue = $this->channelComparison->sum('total_revenue'); @endphp
                        @forelse($this->channelComparison as $channel)
                            <x-table.row class="{{ $selectedChannel === $channel['channel'] ? 'bg-blue-50 dark:bg-blue-900/20' : '' }}">
                                <x-table.cell>
                                    <div class="font-medium text-gray-900 dark:text-white">
                                        {{ $channel['channel'] }}
                                    </div>
                                    @if($channel['growth_rate'] != 0)
                                        <div class="flex items-center mt-1">
                                            @if($channel['growth_rate'] >= 0)
                                                <flux:icon name="trending-up" class="size-3 text-green-500 mr-1" />
                                                <span class="text-green-600 text-xs">
                                                    +{{ number_format($channel['growth_rate'], 1) }}%
                                                </span>
                                            @else
                                                <flux:icon name="trending-down" class="size-3 text-red-500 mr-1" />
                                                <span class="text-red-600 text-xs">
                                                    {{ number_format($channel['growth_rate'], 1) }}%
                                                </span>
                                            @endif
                                        </div>
                                    @endif
                                </x-table.cell>
                                <x-table.cell>
                                    <div class="font-semibold text-gray-900 dark:text-white">
                                        £{{ number_format($channel['total_revenue'], 2) }}
                                    </div>
                                </x-table.cell>
                                <x-table.cell>
                                    <flux:badge color="blue" size="sm">
                                        {{ number_format($channel['total_orders']) }}
                                    </flux:badge>
                                </x-table.cell>
                                <x-table.cell>
                                    <div class="font-medium text-gray-900 dark:text-white">
                                        £{{ number_format($channel['avg_order_value'], 2) }}
                                    </div>
                                </x-table.cell>
                                <x-table.cell>
                                    <div class="font-semibold {{ $channel['total_profit'] >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                        £{{ number_format($channel['total_profit'], 2) }}
                                    </div>
                                </x-table.cell>
                                <x-table.cell>
                                    <flux:badge color="{{ $channel['profit_margin'] >= 20 ? 'green' : ($channel['profit_margin'] >= 10 ? 'yellow' : 'red') }}" size="sm">
                                        {{ number_format($channel['profit_margin'], 1) }}%
                                    </flux:badge>
                                </x-table.cell>
                                <x-table.cell>
                                    @php $share = $totalRevenue > 0 ? ($channel['total_revenue'] / $totalRevenue) * 100 : 0; @endphp
                                    <div class="font-medium text-gray-900 dark:text-white">
                                        {{ number_format($share, 1) }}%
                                    </div>
                                </x-table.cell>
                                <x-table.cell>
                                    <flux:button variant="ghost" size="sm" wire:click="selectChannel('{{ $channel['channel'] }}')">
                                        View Details
                                    </flux:button>
                                </x-table.cell>
                            </x-table.row>
                        @empty
                            <x-table.row>
                                <x-table.cell colspan="8" class="text-center py-8 text-gray-500">
                                    No channel data available
                                </x-table.cell>
                            </x-table.row>
                        @endforelse
                    </x-table.body>
                </x-table>
            </div>
        </x-card>

        {{-- Channel Details Modal/Section --}}
        @if($this->channelDetails)
            <x-card>
                <div class="flex items-center justify-between mb-6">
                    <flux:heading size="lg">{{ $this->channelDetails['channel'] }} - Detailed Analysis</flux:heading>
                    <flux:button variant="ghost" size="sm" wire:click="clearSelection">
                        <flux:icon name="x-mark" class="size-4" />
                        Close
                    </flux:button>
                </div>
                
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    {{-- Daily Performance Chart --}}
                    <div class="lg:col-span-2">
                        <div class="mb-4">
                            <flux:heading size="md">Daily Performance Trend</flux:heading>
                        </div>
                        
                        <div class="h-64" wire:ignore
                            x-data="channelDetailChart(@js($this->channelDetails))"
                            x-init="createChart()"
                            @channel-selected.window="setTimeout(() => createChart(), 100)"
                        >
                            <canvas width="400" height="200"></canvas>
                        </div>
                    </div>
                    
                    {{-- Top Products for this Channel --}}
                    <div>
                        <div class="mb-4">
                            <flux:heading size="md">Top Products</flux:heading>
                        </div>
                        
                        <div class="space-y-3">
                            @forelse($this->channelDetails['top_products'] ?? [] as $product)
                                <div class="p-3 bg-gray-50 dark:bg-gray-800/50 rounded-lg">
                                    <div class="font-medium text-gray-900 dark:text-white">
                                        {{ Str::limit($product['item_title'], 25) }}
                                    </div>
                                    <div class="text-sm text-gray-500">
                                        {{ $product['sku'] }}
                                    </div>
                                    <div class="flex justify-between mt-2">
                                        <span class="text-sm text-gray-600 dark:text-gray-400">
                                            {{ $product['total_quantity'] }} sold
                                        </span>
                                        <span class="font-semibold text-gray-900 dark:text-white">
                                            £{{ number_format($product['total_revenue'], 2) }}
                                        </span>
                                    </div>
                                </div>
                            @empty
                                <div class="text-center py-4 text-gray-500">
                                    No product data available
                                </div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </x-card>
        @endif
    </div>
</div>

@assets
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
@endassets