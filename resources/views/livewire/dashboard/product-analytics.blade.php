<div>
    <div class="space-y-6">
        {{-- Header Section --}}
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
            <div>
                <flux:heading size="xl" class="text-gray-900 dark:text-white">Product Analytics</flux:heading>
                <flux:subheading class="text-gray-600 dark:text-gray-400">
                    Analyze individual product performance and trends
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
                
                <flux:input 
                    wire:model.live.debounce.300ms="search" 
                    placeholder="Search products..." 
                    class="min-w-48"
                    icon="magnifying-glass"
                />
            </div>
        </div>

        @if($selectedProduct && $this->productDetails)
            {{-- Product Detail View --}}
            <x-card>
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <flux:heading size="lg">{{ $this->productDetails->title }}</flux:heading>
                        <flux:subheading class="text-gray-600 dark:text-gray-400">
                            SKU: {{ $this->productDetails->sku }}
                            @if($this->productDetails->category)
                                • Category: {{ $this->productDetails->category }}
                            @endif
                        </flux:subheading>
                    </div>
                    <flux:button wire:click="clearSelection" size="sm" variant="ghost">
                        <flux:icon.x-mark class="size-4" />
                        Close Detail View
                    </flux:button>
                </div>

                {{-- Product Metrics --}}
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                    <div class="bg-blue-50 dark:bg-blue-900/20 p-4 rounded-lg">
                        <div class="text-blue-600 dark:text-blue-400 text-sm font-medium">Total Revenue</div>
                        <div class="text-2xl font-bold text-blue-900 dark:text-blue-100">
                            £{{ number_format($this->productDetails->total_revenue, 2) }}
                        </div>
                    </div>
                    <div class="bg-green-50 dark:bg-green-900/20 p-4 rounded-lg">
                        <div class="text-green-600 dark:text-green-400 text-sm font-medium">Units Sold</div>
                        <div class="text-2xl font-bold text-green-900 dark:text-green-100">
                            {{ number_format($this->productDetails->total_quantity) }}
                        </div>
                    </div>
                    <div class="bg-purple-50 dark:bg-purple-900/20 p-4 rounded-lg">
                        <div class="text-purple-600 dark:text-purple-400 text-sm font-medium">Total Orders</div>
                        <div class="text-2xl font-bold text-purple-900 dark:text-purple-100">
                            {{ number_format($this->productDetails->total_orders) }}
                        </div>
                    </div>
                    <div class="bg-orange-50 dark:bg-orange-900/20 p-4 rounded-lg">
                        <div class="text-orange-600 dark:text-orange-400 text-sm font-medium">Profit</div>
                        <div class="text-2xl font-bold text-orange-900 dark:text-orange-100">
                            £{{ number_format($this->productDetails->profit ?? 0, 2) }}
                        </div>
                    </div>
                </div>

                {{-- Product Sales Chart --}}
                <div class="h-64" wire:ignore wire:key="product-chart-{{ $selectedProduct }}">
                    <canvas id="productSalesChart" width="400" height="200"></canvas>
                </div>

                @script
                <script>
                    const productCtx = document.getElementById('productSalesChart').getContext('2d');
                    const productSalesData = @json($this->productSalesChart);
                    
                    const productChart = new Chart(productCtx, {
                        type: 'bar',
                        data: {
                            labels: productSalesData.map(item => item.date),
                            datasets: [{
                                label: 'Quantity Sold',
                                data: productSalesData.map(item => item.quantity),
                                backgroundColor: 'rgba(59, 130, 246, 0.5)',
                                borderColor: 'rgb(59, 130, 246)',
                                borderWidth: 1,
                                yAxisID: 'y'
                            }, {
                                label: 'Revenue (£)',
                                data: productSalesData.map(item => item.revenue),
                                type: 'line',
                                borderColor: 'rgb(16, 185, 129)',
                                backgroundColor: 'rgba(16, 185, 129, 0.1)',
                                tension: 0.4,
                                yAxisID: 'y1'
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            interaction: {
                                mode: 'index',
                                intersect: false,
                            },
                            scales: {
                                y: {
                                    type: 'linear',
                                    display: true,
                                    position: 'left',
                                    beginAtZero: true,
                                    title: {
                                        display: true,
                                        text: 'Quantity'
                                    }
                                },
                                y1: {
                                    type: 'linear',
                                    display: true,
                                    position: 'right',
                                    beginAtZero: true,
                                    title: {
                                        display: true,
                                        text: 'Revenue (£)'
                                    },
                                    grid: {
                                        drawOnChartArea: false,
                                    },
                                }
                            }
                        }
                    });
                </script>
                @endscript
            </x-card>
        @endif

        {{-- Categories Overview --}}
        @if(!$selectedProduct)
            <x-card>
                <div class="flex items-center justify-between mb-6">
                    <flux:heading size="lg">Top Categories</flux:heading>
                    <flux:badge color="green" size="sm">By Revenue</flux:badge>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    @forelse($this->topCategories as $category)
                        <div class="p-4 bg-gray-50 dark:bg-gray-800/50 rounded-lg">
                            <div class="flex items-center justify-between mb-2">
                                <div class="font-medium text-gray-900 dark:text-white">
                                    {{ $category->category }}
                                </div>
                                <flux:badge color="blue" size="sm">
                                    {{ $category->product_count }} products
                                </flux:badge>
                            </div>
                            <div class="text-sm text-gray-500 mb-1">
                                {{ number_format($category->total_quantity) }} units sold
                            </div>
                            <div class="font-semibold text-gray-900 dark:text-white">
                                £{{ number_format($category->total_revenue, 2) }}
                            </div>
                        </div>
                    @empty
                        <div class="col-span-full text-center py-8 text-gray-500">
                            No category data available
                        </div>
                    @endforelse
                </div>
            </x-card>
        @endif

        {{-- Products Table --}}
        <x-card>
            <div class="flex items-center justify-between mb-6">
                <flux:heading size="lg">Product Performance</flux:heading>
                <div class="text-sm text-gray-500">
                    {{ $this->products->total() }} products found
                </div>
            </div>
            
            <div class="overflow-x-auto">
                <x-table>
                    <x-table.header>
                        <x-table.row>
                            <x-table.header-cell 
                                sortable 
                                :sorted="$sortBy === 'name'" 
                                :direction="$sortDirection"
                                wire:click="sortBy('name')"
                                class="cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-700"
                            >
                                Product
                            </x-table.header-cell>
                            <x-table.header-cell 
                                sortable 
                                :sorted="$sortBy === 'quantity'" 
                                :direction="$sortDirection"
                                wire:click="sortBy('quantity')"
                                class="cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-700"
                            >
                                Sold
                            </x-table.header-cell>
                            <x-table.header-cell 
                                sortable 
                                :sorted="$sortBy === 'revenue'" 
                                :direction="$sortDirection"
                                wire:click="sortBy('revenue')"
                                class="cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-700"
                            >
                                Revenue
                            </x-table.header-cell>
                            <x-table.header-cell 
                                sortable 
                                :sorted="$sortBy === 'orders'" 
                                :direction="$sortDirection"
                                wire:click="sortBy('orders')"
                                class="cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-700"
                            >
                                Orders
                            </x-table.header-cell>
                            <x-table.header-cell 
                                sortable 
                                :sorted="$sortBy === 'profit'" 
                                :direction="$sortDirection"
                                wire:click="sortBy('profit')"
                                class="cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-700"
                            >
                                Profit
                            </x-table.header-cell>
                            <x-table.header-cell>Actions</x-table.header-cell>
                        </x-table.row>
                    </x-table.header>
                    
                    <x-table.body>
                        @forelse($this->products as $product)
                            <x-table.row wire:key="product-{{ $product->sku }}">
                                <x-table.cell>
                                    <div>
                                        <div class="font-medium text-gray-900 dark:text-white">
                                            {{ Str::limit($product->title, 40) }}
                                        </div>
                                        <div class="text-sm text-gray-500">
                                            {{ $product->sku }}
                                            @if($product->category)
                                                • {{ $product->category }}
                                            @endif
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
                                    <div class="text-sm text-gray-500">
                                        £{{ number_format($product->avg_price, 2) }} avg
                                    </div>
                                </x-table.cell>
                                <x-table.cell>
                                    <span class="text-gray-900 dark:text-white">
                                        {{ number_format($product->total_orders) }}
                                    </span>
                                </x-table.cell>
                                <x-table.cell>
                                    @php
                                        $profit = $product->profit ?? 0;
                                        $profitColor = $profit >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400';
                                    @endphp
                                    <div class="{{ $profitColor }} font-medium">
                                        £{{ number_format($profit, 2) }}
                                    </div>
                                </x-table.cell>
                                <x-table.cell>
                                    <flux:button 
                                        wire:click="selectProduct('{{ $product->sku }}')" 
                                        size="sm" 
                                        variant="ghost"
                                    >
                                        <flux:icon.eye class="size-4" />
                                        View Details
                                    </flux:button>
                                </x-table.cell>
                            </x-table.row>
                        @empty
                            <x-table.row>
                                <x-table.cell colspan="6" class="text-center py-8 text-gray-500">
                                    No products found matching your criteria
                                </x-table.cell>
                            </x-table.row>
                        @endforelse
                    </x-table.body>
                </x-table>
            </div>
            
            {{-- Pagination --}}
            <div class="mt-6">
                {{ $this->products->links() }}
            </div>
        </x-card>
    </div>
</div>

@script
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
@endscript