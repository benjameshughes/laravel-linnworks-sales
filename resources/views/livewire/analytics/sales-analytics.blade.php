<div>
    <flux:heading size="xl" class="mb-6">Sales Analytics</flux:heading>

    <!-- Filters Section -->
    <div class="mb-8 bg-white dark:bg-zinc-900 rounded-lg shadow-sm border border-gray-200 dark:border-zinc-800">
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <!-- Date Preset -->
                <div>
                    <flux:field>
                        <flux:label>Date Range</flux:label>
                        <flux:select wire:model.live="datePreset">
                            <flux:select.option value="last_7_days">Last 7 Days</flux:select.option>
                            <flux:select.option value="last_30_days">Last 30 Days</flux:select.option>
                            <flux:select.option value="last_90_days">Last 90 Days</flux:select.option>
                            <flux:select.option value="last_6_months">Last 6 Months</flux:select.option>
                            <flux:select.option value="last_year">Last Year</flux:select.option>
                            <flux:select.option value="year_to_date">Year to Date</flux:select.option>
                            <flux:select.option value="custom">Custom Range</flux:select.option>
                        </flux:select>
                    </flux:field>
                </div>

                <!-- Custom Date Range -->
                @if($datePreset === 'custom')
                    <div>
                        <flux:field>
                            <flux:label>Start Date</flux:label>
                            <flux:input 
                                type="date" 
                                wire:model.live="startDate" 
                                max="{{ now()->format('Y-m-d') }}"
                            />
                        </flux:field>
                    </div>
                    <div>
                        <flux:field>
                            <flux:label>End Date</flux:label>
                            <flux:input 
                                type="date" 
                                wire:model.live="endDate" 
                                max="{{ now()->format('Y-m-d') }}"
                            />
                        </flux:field>
                    </div>
                @endif

                <!-- Channel Filter -->
                <div>
                    <livewire:components.multi-select
                        :options="$this->availableChannels->toArray()"
                        wire:model.live="selectedChannels"
                        label="Channels ({{ count($selectedChannels) }} selected)"
                        placeholder="Select channels..."
                        :searchable="true"
                    />
                </div>

                <!-- Actions -->
                <div class="flex items-end gap-2">
                    <flux:button wire:click="resetFilters" variant="outline" size="sm">
                        Reset Filters
                    </flux:button>
                </div>
            </div>

            <!-- Date Range Display -->
            <div class="mt-4 text-sm text-gray-600 dark:text-gray-400">
                Showing data from {{ Carbon\Carbon::parse($startDate)->format('M j, Y') }} to {{ Carbon\Carbon::parse($endDate)->format('M j, Y') }}
                ({{ $this->orders->count() }} orders)
            </div>
        </div>
    </div>

    <!-- Tab Navigation -->
    <div class="mb-6">
        <nav class="flex space-x-4 border-b border-gray-200 dark:border-zinc-800">
            <button 
                wire:click="setTab('overview')"
                class="px-4 py-2 text-sm font-medium border-b-2 {{ $activeTab === 'overview' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}"
            >
                Overview
            </button>
            <button 
                wire:click="setTab('trends')"
                class="px-4 py-2 text-sm font-medium border-b-2 {{ $activeTab === 'trends' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}"
            >
                Trends
            </button>
            <button 
                wire:click="setTab('orders')"
                class="px-4 py-2 text-sm font-medium border-b-2 {{ $activeTab === 'orders' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}"
            >
                Orders
            </button>
            <button 
                wire:click="setTab('products')"
                class="px-4 py-2 text-sm font-medium border-b-2 {{ $activeTab === 'products' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}"
            >
                Products
            </button>
        </nav>
    </div>

    <!-- Content Based on Active Tab -->
    @if($activeTab === 'overview')
        <!-- Overview Tab -->
        <div class="space-y-6">
            <!-- Key Metrics -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <div class="bg-white dark:bg-zinc-900 rounded-lg shadow-sm border border-gray-200 dark:border-zinc-800 p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Total Revenue</p>
                            <p class="text-2xl font-bold text-gray-900 dark:text-white">
                                £{{ number_format($this->salesMetrics->totalRevenue(), 2) }}
                            </p>
                        </div>
                        <div class="p-3 bg-blue-100 dark:bg-blue-900 rounded-full">
                            <svg class="w-6 h-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
                            </svg>
                        </div>
                    </div>
                </div>

                <div class="bg-white dark:bg-zinc-900 rounded-lg shadow-sm border border-gray-200 dark:border-zinc-800 p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Total Orders</p>
                            <p class="text-2xl font-bold text-gray-900 dark:text-white">
                                {{ number_format($this->salesMetrics->totalOrders()) }}
                            </p>
                        </div>
                        <div class="p-3 bg-green-100 dark:bg-green-900 rounded-full">
                            <svg class="w-6 h-6 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                        </div>
                    </div>
                </div>

                <div class="bg-white dark:bg-zinc-900 rounded-lg shadow-sm border border-gray-200 dark:border-zinc-800 p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Processed Orders</p>
                            <p class="text-2xl font-bold text-gray-900 dark:text-white">
                                {{ number_format($this->salesMetrics->totalProcessedOrders()) }}
                            </p>
                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                £{{ number_format($this->salesMetrics->processedOrdersRevenue(), 2) }}
                            </p>
                        </div>
                        <div class="p-3 bg-green-100 dark:bg-green-900 rounded-full">
                            <svg class="w-6 h-6 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                        </div>
                    </div>
                </div>

                <div class="bg-white dark:bg-zinc-900 rounded-lg shadow-sm border border-gray-200 dark:border-zinc-800 p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Open Orders</p>
                            <p class="text-2xl font-bold text-gray-900 dark:text-white">
                                {{ number_format($this->salesMetrics->totalOpenOrders()) }}
                            </p>
                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                £{{ number_format($this->salesMetrics->openOrdersRevenue(), 2) }}
                            </p>
                        </div>
                        <div class="p-3 bg-orange-100 dark:bg-orange-900 rounded-full">
                            <svg class="w-6 h-6 text-orange-600 dark:text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts Row -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Revenue Chart -->
                <div class="bg-white dark:bg-zinc-900 rounded-lg shadow-sm border border-gray-200 dark:border-zinc-800 p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Revenue Trends</h3>
                        <flux:select wire:model.live="chartType" class="w-32">
                            <flux:select.option value="line">Line</flux:select.option>
                            <flux:select.option value="bar">Bar</flux:select.option>
                            <flux:select.option value="order_count">Order Count</flux:select.option>
                        </flux:select>
                    </div>
                    <div class="h-80">
                        @if($chartType === 'line')
                            <livewire:charts.line-chart :data="$this->chartData" wire:key="chart-{{ $chartType }}" />
                        @elseif($chartType === 'bar')
                            <livewire:charts.bar-chart :data="$this->chartData" wire:key="chart-{{ $chartType }}" />
                        @elseif($chartType === 'order_count')
                            <livewire:charts.bar-chart :data="$this->chartData" wire:key="chart-{{ $chartType }}" />
                        @endif
                    </div>
                </div>

                <!-- Channel Distribution -->
                <div class="bg-white dark:bg-zinc-900 rounded-lg shadow-sm border border-gray-200 dark:border-zinc-800 p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Channel Distribution</h3>
                    <div class="h-80">
                        <livewire:charts.doughnut-chart :data="$this->salesMetrics->getDoughnutChartData()" />
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if($activeTab === 'trends')
        <!-- Trends Tab -->
        <div class="space-y-6">
            <div class="bg-white dark:bg-zinc-900 rounded-lg shadow-sm border border-gray-200 dark:border-zinc-800 p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Detailed Trends Analysis</h3>
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Revenue vs Orders -->
                    <div class="h-80">
                        <livewire:charts.line-chart :data="$this->salesMetrics->getLineChartData($this->getChartPeriod())" />
                    </div>
                    <!-- Order Status Distribution -->
                    <div class="h-80">
                        <livewire:charts.bar-chart :data="$this->salesMetrics->getOrderCountChartData($this->getChartPeriod())" />
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if($activeTab === 'orders')
        <!-- Orders Tab -->
        <div class="bg-white dark:bg-zinc-900 rounded-lg shadow-sm border border-gray-200 dark:border-zinc-800">
            <div class="p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Orders</h3>
                    <div class="flex items-center gap-4">
                        <flux:select wire:model.live="perPage" class="w-20">
                            <flux:select.option value="25">25</flux:select.option>
                            <flux:select.option value="50">50</flux:select.option>
                            <flux:select.option value="100">100</flux:select.option>
                        </flux:select>
                    </div>
                </div>

                <!-- Orders Table -->
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-zinc-800">
                        <thead class="bg-gray-50 dark:bg-zinc-800">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider cursor-pointer" 
                                    wire:click="setSortBy('order_number')">
                                    Order #
                                    @if($sortBy === 'order_number')
                                        <span class="ml-1">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                    @endif
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider cursor-pointer" 
                                    wire:click="setSortBy('received_date')">
                                    Date
                                    @if($sortBy === 'received_date')
                                        <span class="ml-1">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                    @endif
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider cursor-pointer" 
                                    wire:click="setSortBy('channel_name')">
                                    Channel
                                    @if($sortBy === 'channel_name')
                                        <span class="ml-1">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                    @endif
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider cursor-pointer" 
                                    wire:click="setSortBy('total_charge')">
                                    Total
                                    @if($sortBy === 'total_charge')
                                        <span class="ml-1">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                    @endif
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider cursor-pointer" 
                                    wire:click="setSortBy('is_processed')">
                                    Status
                                    @if($sortBy === 'is_processed')
                                        <span class="ml-1">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                    @endif
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-zinc-900 divide-y divide-gray-200 dark:divide-zinc-800">
                            @foreach($this->paginatedOrders as $order)
                                <tr class="hover:bg-gray-50 dark:hover:bg-zinc-800">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                                        {{ $order->order_number }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                        {{ $order->received_date?->format('M j, Y') }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                        {{ $order->channel_name }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                        £{{ number_format($order->total_charge, 2) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        @if($order->is_processed)
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                                Processed
                                            </span>
                                        @else
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200">
                                                Open
                                            </span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <!-- Pagination placeholder -->
                <div class="mt-6 flex justify-center">
                    <span class="text-sm text-gray-500 dark:text-gray-400">
                        Showing {{ $this->paginatedOrders->count() }} of {{ $this->orders->count() }} orders
                    </span>
                </div>
            </div>
        </div>
    @endif

    @if($activeTab === 'products')
        <!-- Products Tab -->
        <div class="bg-white dark:bg-zinc-900 rounded-lg shadow-sm border border-gray-200 dark:border-zinc-800 p-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Top Products</h3>
            
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-zinc-800">
                    <thead class="bg-gray-50 dark:bg-zinc-800">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                Product
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                Revenue
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                Quantity
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                Orders
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-zinc-900 divide-y divide-gray-200 dark:divide-zinc-800">
                        @foreach($this->salesMetrics->topProducts(20) as $product)
                            <tr class="hover:bg-gray-50 dark:hover:bg-zinc-800">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div>
                                        <div class="text-sm font-medium text-gray-900 dark:text-white">
                                            {{ $product['title'] }}
                                        </div>
                                        <div class="text-sm text-gray-500 dark:text-gray-400">
                                            SKU: {{ $product['sku'] }}
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                    £{{ number_format($product['revenue'], 2) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                    {{ $product['quantity'] }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                    {{ $product['orders'] }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>