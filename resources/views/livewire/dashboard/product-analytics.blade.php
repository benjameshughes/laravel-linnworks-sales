<div>
    <div class="space-y-6">
        {{-- Header Section --}}
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
            <div>
                <flux:heading size="xl" class="text-gray-900 dark:text-white">Product Analytics</flux:heading>
                <flux:subheading class="text-gray-600 dark:text-gray-400">
                    Analyze product performance and profitability
                </flux:subheading>
            </div>
            
            {{-- Quick Filters --}}
            <div class="flex flex-col sm:flex-row gap-3">
                <flux:select wire:model.live="period" placeholder="Select period" class="min-w-36">
                    <flux:select.option value="7">Last 7 days</flux:select.option>
                    <flux:select.option value="30">Last 30 days</flux:select.option>
                    <flux:select.option value="90">Last 90 days</flux:select.option>
                    <flux:select.option value="365">Last year</flux:select.option>
                </flux:select>
                
                <flux:input wire:model.live.debounce.300ms="search" placeholder="Search products..." class="min-w-48" />
                
                @if($selectedProduct)
                    <flux:button variant="outline" size="sm" wire:click="clearSelection">
                        <flux:icon name="x-mark" class="size-4" />
                        Clear Selection
                    </flux:button>
                @endif
            </div>
        </div>

        {{-- Summary Cards --}}
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            {{-- Total Products --}}
            <x-card>
                <div class="flex items-center justify-between">
                    <div>
                        <flux:subheading class="text-gray-600 dark:text-gray-400 text-sm font-medium">
                            Total Products
                        </flux:subheading>
                        <flux:heading size="lg" class="text-2xl font-bold text-gray-900 dark:text-white mt-1">
                            {{ number_format($this->products->count()) }}
                        </flux:heading>
                        <div class="text-gray-500 text-sm mt-2">
                            {{ $this->period }} day{{ $this->period != 1 ? 's' : '' }}
                        </div>
                    </div>
                    <div class="p-3 bg-blue-100 dark:bg-blue-900/20 rounded-lg">
                        <flux:icon name="cube" class="size-6 text-blue-600 dark:text-blue-400" />
                    </div>
                </div>
            </x-card>

            {{-- Total Units Sold --}}
            <x-card>
                <div class="flex items-center justify-between">
                    <div>
                        <flux:subheading class="text-gray-600 dark:text-gray-400 text-sm font-medium">
                            Units Sold
                        </flux:subheading>
                        <flux:heading size="lg" class="text-2xl font-bold text-gray-900 dark:text-white mt-1">
                            {{ number_format($this->products->sum('total_quantity')) }}
                        </flux:heading>
                        <div class="text-gray-500 text-sm mt-2">
                            Total units
                        </div>
                    </div>
                    <div class="p-3 bg-green-100 dark:bg-green-900/20 rounded-lg">
                        <flux:icon name="shopping-cart" class="size-6 text-green-600 dark:text-green-400" />
                    </div>
                </div>
            </x-card>

            {{-- Total Revenue --}}
            <x-card>
                <div class="flex items-center justify-between">
                    <div>
                        <flux:subheading class="text-gray-600 dark:text-gray-400 text-sm font-medium">
                            Product Revenue
                        </flux:subheading>
                        <flux:heading size="lg" class="text-2xl font-bold text-gray-900 dark:text-white mt-1">
                            £{{ number_format($this->products->sum('total_revenue'), 2) }}
                        </flux:heading>
                        <div class="text-gray-500 text-sm mt-2">
                            Total sales
                        </div>
                    </div>
                    <div class="p-3 bg-purple-100 dark:bg-purple-900/20 rounded-lg">
                        <flux:icon name="currency-pound" class="size-6 text-purple-600 dark:text-purple-400" />
                    </div>
                </div>
            </x-card>

            {{-- Average Profit Margin --}}
            <x-card>
                <div class="flex items-center justify-between">
                    <div>
                        <flux:subheading class="text-gray-600 dark:text-gray-400 text-sm font-medium">
                            Avg Profit Margin
                        </flux:subheading>
                        <flux:heading size="lg" class="text-2xl font-bold text-gray-900 dark:text-white mt-1">
                            {{ number_format($this->products->where('total_revenue', '>', 0)->avg('profit_margin') ?? 0, 1) }}%
                        </flux:heading>
                        <div class="text-gray-500 text-sm mt-2">
                            Across all products
                        </div>
                    </div>
                    <div class="p-3 bg-orange-100 dark:bg-orange-900/20 rounded-lg">
                        <flux:icon name="chart-bar" class="size-6 text-orange-600 dark:text-orange-400" />
                    </div>
                </div>
            </x-card>
        </div>

        {{-- Main Content --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            {{-- Product List --}}
            <div class="lg:col-span-2">
                <x-card>
                    <div class="flex items-center justify-between mb-6">
                        <flux:heading size="lg">Product Performance</flux:heading>
                        <div class="flex gap-2">
                            <flux:button variant="outline" size="sm" wire:click="sortBy('revenue')" class="{{ $sortBy === 'revenue' ? 'bg-blue-50 dark:bg-blue-900/20' : '' }}">
                                Revenue
                                @if($sortBy === 'revenue')
                                    <flux:icon name="{{ $sortDirection === 'asc' ? 'arrow-up' : 'arrow-down' }}" class="size-3 ml-1" />
                                @endif
                            </flux:button>
                            <flux:button variant="outline" size="sm" wire:click="sortBy('profit')" class="{{ $sortBy === 'profit' ? 'bg-blue-50 dark:bg-blue-900/20' : '' }}">
                                Profit
                                @if($sortBy === 'profit')
                                    <flux:icon name="{{ $sortDirection === 'asc' ? 'arrow-up' : 'arrow-down' }}" class="size-3 ml-1" />
                                @endif
                            </flux:button>
                            <flux:button variant="outline" size="sm" wire:click="sortBy('margin')" class="{{ $sortBy === 'margin' ? 'bg-blue-50 dark:bg-blue-900/20' : '' }}">
                                Margin
                                @if($sortBy === 'margin')
                                    <flux:icon name="{{ $sortDirection === 'asc' ? 'arrow-up' : 'arrow-down' }}" class="size-3 ml-1" />
                                @endif
                            </flux:button>
                        </div>
                    </div>
                    
                    <div class="overflow-hidden">
                        <x-table>
                            <x-table.header>
                                <x-table.row>
                                    <x-table.header-cell>Product</x-table.header-cell>
                                    <x-table.header-cell>Sold</x-table.header-cell>
                                    <x-table.header-cell>Revenue</x-table.header-cell>
                                    <x-table.header-cell>Profit</x-table.header-cell>
                                    <x-table.header-cell>Margin</x-table.header-cell>
                                    <x-table.header-cell>Actions</x-table.header-cell>
                                </x-table.row>
                            </x-table.header>
                            
                            <x-table.body>
                                @forelse($this->products->take(15) as $product)
                                    <x-table.row class="{{ $selectedProduct === $product['sku'] ? 'bg-blue-50 dark:bg-blue-900/20' : '' }}">
                                        <x-table.cell>
                                            <div>
                                                <div class="font-medium text-gray-900 dark:text-white">
                                                    {{ Str::limit($product['item_title'], 25) }}
                                                </div>
                                                <div class="text-sm text-gray-500">
                                                    {{ $product['sku'] }}
                                                </div>
                                                <div class="text-xs text-gray-400">
                                                    {{ $product['category_name'] }}
                                                </div>
                                            </div>
                                        </x-table.cell>
                                        <x-table.cell>
                                            <flux:badge color="blue" size="sm">
                                                {{ number_format($product['total_quantity']) }}
                                            </flux:badge>
                                        </x-table.cell>
                                        <x-table.cell>
                                            <div class="font-semibold text-gray-900 dark:text-white">
                                                £{{ number_format($product['total_revenue'], 2) }}
                                            </div>
                                        </x-table.cell>
                                        <x-table.cell>
                                            <div class="font-semibold {{ $product['profit'] >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                                £{{ number_format($product['profit'], 2) }}
                                            </div>
                                        </x-table.cell>
                                        <x-table.cell>
                                            <flux:badge color="{{ $product['profit_margin'] >= 20 ? 'green' : ($product['profit_margin'] >= 10 ? 'yellow' : 'red') }}" size="sm">
                                                {{ number_format($product['profit_margin'], 1) }}%
                                            </flux:badge>
                                        </x-table.cell>
                                        <x-table.cell>
                                            <flux:button variant="ghost" size="sm" wire:click="selectProduct('{{ $product['sku'] }}')">
                                                View
                                            </flux:button>
                                        </x-table.cell>
                                    </x-table.row>
                                @empty
                                    <x-table.row>
                                        <x-table.cell colspan="6" class="text-center py-8 text-gray-500">
                                            No products found
                                        </x-table.cell>
                                    </x-table.row>
                                @endforelse
                            </x-table.body>
                        </x-table>
                    </div>
                </x-card>
            </div>

            {{-- Sidebar --}}
            <div class="space-y-6">
                {{-- Categories --}}
                <x-card>
                    <div class="flex items-center justify-between mb-4">
                        <flux:heading size="md">Top Categories</flux:heading>
                        @if($selectedCategory)
                            <flux:button variant="ghost" size="sm" wire:click="clearCategoryFilter">
                                <flux:icon name="x-mark" class="size-4" />
                            </flux:button>
                        @endif
                    </div>
                    
                    <div class="space-y-3">
                        @forelse($this->topCategories as $category)
                            <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-800/50 rounded-lg cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-700/50 {{ $selectedCategory === $category['category'] ? 'ring-2 ring-blue-500' : '' }}" 
                                 wire:click="selectCategory('{{ $category['category'] }}')">
                                <div>
                                    <div class="font-medium text-gray-900 dark:text-white">
                                        {{ $category['category'] }}
                                    </div>
                                    <div class="text-sm text-gray-500">
                                        {{ $category['product_count'] }} products
                                    </div>
                                </div>
                                <div class="text-right">
                                    <div class="font-semibold text-gray-900 dark:text-white">
                                        £{{ number_format($category['total_revenue'], 0) }}
                                    </div>
                                    <div class="text-sm text-gray-500">
                                        {{ number_format($category['total_quantity']) }} units
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="text-center py-4 text-gray-500">
                                No categories found
                            </div>
                        @endforelse
                    </div>
                </x-card>

                {{-- Selected Product Details --}}
                @if($this->productDetails)
                    <x-card>
                        <div class="flex items-center justify-between mb-4">
                            <flux:heading size="md">Product Details</flux:heading>
                            <flux:button variant="ghost" size="sm" wire:click="clearSelection">
                                <flux:icon name="x-mark" class="size-4" />
                            </flux:button>
                        </div>
                        
                        <div class="space-y-4">
                            <div>
                                <div class="font-medium text-gray-900 dark:text-white">
                                    {{ $this->productDetails['item_title'] }}
                                </div>
                                <div class="text-sm text-gray-500">
                                    {{ $this->productDetails['sku'] }}
                                </div>
                                <div class="text-xs text-gray-400">
                                    {{ $this->productDetails['category_name'] }}
                                </div>
                            </div>
                            
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <div class="text-sm text-gray-500">Units Sold</div>
                                    <div class="font-semibold text-gray-900 dark:text-white">
                                        {{ number_format($this->productDetails['total_quantity']) }}
                                    </div>
                                </div>
                                <div>
                                    <div class="text-sm text-gray-500">Revenue</div>
                                    <div class="font-semibold text-gray-900 dark:text-white">
                                        £{{ number_format($this->productDetails['total_revenue'], 2) }}
                                    </div>
                                </div>
                                <div>
                                    <div class="text-sm text-gray-500">Profit</div>
                                    <div class="font-semibold {{ $this->productDetails['profit'] >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                        £{{ number_format($this->productDetails['profit'], 2) }}
                                    </div>
                                </div>
                                <div>
                                    <div class="text-sm text-gray-500">Margin</div>
                                    <div class="font-semibold text-gray-900 dark:text-white">
                                        {{ number_format($this->productDetails['profit_margin'], 1) }}%
                                    </div>
                                </div>
                            </div>
                            
                            {{-- Channel Breakdown --}}
                            @if(isset($this->productDetails['channel_breakdown']) && count($this->productDetails['channel_breakdown']) > 0)
                                <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                                    <div class="text-sm font-medium text-gray-900 dark:text-white mb-2">Channel Breakdown</div>
                                    <div class="space-y-2">
                                        @foreach($this->productDetails['channel_breakdown'] as $channel)
                                            <div class="flex justify-between text-sm">
                                                <span class="text-gray-600 dark:text-gray-400">{{ $channel['channel'] }}</span>
                                                <span class="font-medium text-gray-900 dark:text-white">
                                                    {{ $channel['total_quantity'] }} units
                                                </span>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        </div>
                    </x-card>
                @endif
            </div>
        </div>

        {{-- Product Sales Chart --}}
        @if($selectedProduct && $this->productSalesChart)
            <x-card>
                <div class="flex items-center justify-between mb-6">
                    <flux:heading size="lg">Sales Trend: {{ $this->productDetails['item_title'] ?? 'Selected Product' }}</flux:heading>
                    <flux:badge color="blue" size="sm">Daily Performance</flux:badge>
                </div>
                
                <div class="h-64" wire:ignore>
                    <canvas id="productSalesChart" width="400" height="200"></canvas>
                </div>
                
                @script
                <script>
                    let productChart = null;
                    
                    function createProductChart() {
                        const ctx = document.getElementById('productSalesChart');
                        if (!ctx) return;
                        
                        const salesData = @json($this->productSalesChart);
                        
                        if (productChart) {
                            productChart.destroy();
                        }
                        
                        productChart = new Chart(ctx, {
                            type: 'bar',
                            data: {
                                labels: salesData.map(item => item.date),
                                datasets: [{
                                    label: 'Quantity Sold',
                                    data: salesData.map(item => item.quantity),
                                    backgroundColor: 'rgba(59, 130, 246, 0.8)',
                                    borderColor: 'rgb(59, 130, 246)',
                                    borderWidth: 1,
                                    yAxisID: 'y'
                                }, {
                                    label: 'Revenue (£)',
                                    data: salesData.map(item => item.revenue),
                                    type: 'line',
                                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                                    borderColor: 'rgb(16, 185, 129)',
                                    borderWidth: 2,
                                    tension: 0.4,
                                    yAxisID: 'y1'
                                }]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                plugins: {
                                    legend: {
                                        position: 'top',
                                    }
                                },
                                scales: {
                                    y: {
                                        beginAtZero: true,
                                        position: 'left',
                                        title: {
                                            display: true,
                                            text: 'Quantity'
                                        }
                                    },
                                    y1: {
                                        type: 'linear',
                                        display: true,
                                        position: 'right',
                                        title: {
                                            display: true,
                                            text: 'Revenue (£)'
                                        },
                                        grid: {
                                            drawOnChartArea: false,
                                        },
                                        ticks: {
                                            callback: function(value) {
                                                return '£' + value.toLocaleString();
                                            }
                                        }
                                    }
                                }
                            }
                        });
                    }
                    
                    createProductChart();
                    
                    Livewire.on('productSelected', () => {
                        setTimeout(() => createProductChart(), 100);
                    });
                </script>
                @endscript
            </x-card>
        @endif
    </div>
</div>

@script
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
@endscript