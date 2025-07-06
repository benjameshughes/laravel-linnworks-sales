<div class="min-h-screen bg-zinc-50 dark:bg-zinc-900">
    <div class="space-y-6 p-6">
        {{-- Condensed Header with Controls --}}
        <div class="bg-white dark:bg-zinc-800 rounded-xl shadow-sm border border-zinc-200 dark:border-zinc-700 p-4">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                <div class="flex items-center gap-6">
                    <div>
                        <flux:heading size="lg" class="text-zinc-900 dark:text-zinc-100">Product Analytics</flux:heading>
                        <div class="flex items-center gap-2 text-sm text-zinc-600 dark:text-zinc-400 mt-1">
                            <span>{{ $this->periodSummary->get('period_label') }}</span>
                            <span class="text-zinc-400">•</span>
                            <span class="font-medium text-zinc-900 dark:text-zinc-100">
                                {{ number_format($this->metrics->get('total_products')) }} products analyzed
                            </span>
                        </div>
                    </div>
                    
                    {{-- Quick Stats Inline --}}
                    <div class="hidden lg:flex items-center gap-3 text-sm">
                        <flux:icon name="cube" class="size-4 text-zinc-500" />
                        <span class="text-zinc-600 dark:text-zinc-400">{{ number_format($this->metrics->get('total_units_sold')) }} units sold</span>
                        @if($this->metrics->get('avg_profit_margin') > 0)
                            <flux:badge color="green" size="sm">
                                {{ number_format($this->metrics->get('avg_profit_margin'), 1) }}% avg margin
                            </flux:badge>
                        @endif
                    </div>
                </div>
                
                {{-- Controls --}}
                <div class="flex flex-wrap gap-2">
                    <flux:input 
                        wire:model.live="search" 
                        placeholder="Search products..." 
                        class="min-w-48"
                        size="sm"
                    />
                    
                    <flux:select wire:model.live="period" size="sm" class="min-w-32">
                        <flux:select.option value="7">7 days</flux:select.option>
                        <flux:select.option value="30">30 days</flux:select.option>
                        <flux:select.option value="90">90 days</flux:select.option>
                        <flux:select.option value="365">1 year</flux:select.option>
                    </flux:select>
                    
                    @if($selectedCategory)
                        <flux:button variant="outline" size="sm" wire:click="clearCategoryFilter" icon="x-mark">
                            {{ $selectedCategory }}
                        </flux:button>
                    @endif
                    
                    <flux:button variant="primary" size="sm" wire:click="syncProducts" icon="cloud-arrow-down">
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
                {{-- Total Products --}}
                <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl shadow-sm p-6 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-blue-100 text-sm font-medium">Products Analyzed</p>
                            <p class="text-3xl font-bold">{{ number_format($this->metrics->get('total_products')) }}</p>
                            <p class="text-sm text-blue-100 mt-1">
                                Active products with sales
                            </p>
                        </div>
                        <flux:icon name="cube" class="size-8 text-blue-200" />
                    </div>
                </div>

                {{-- Total Units Sold --}}
                <div class="bg-gradient-to-br from-emerald-500 to-emerald-600 rounded-xl shadow-sm p-6 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-emerald-100 text-sm font-medium">Units Sold</p>
                            <p class="text-3xl font-bold">{{ number_format($this->metrics->get('total_units_sold')) }}</p>
                            <p class="text-sm text-emerald-100 mt-1">
                                Total quantity moved
                            </p>
                        </div>
                        <flux:icon name="shopping-cart" class="size-8 text-emerald-200" />
                    </div>
                </div>

                {{-- Total Revenue --}}
                <div class="bg-gradient-to-br from-purple-500 to-purple-600 rounded-xl shadow-sm p-6 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-purple-100 text-sm font-medium">Product Revenue</p>
                            <p class="text-3xl font-bold">£{{ number_format($this->metrics->get('total_revenue'), 0) }}</p>
                            <p class="text-sm text-purple-100 mt-1">Total sales value</p>
                        </div>
                        <flux:icon name="currency-pound" class="size-8 text-purple-200" />
                    </div>
                </div>

                {{-- Average Profit Margin --}}
                <div class="bg-gradient-to-br from-orange-500 to-orange-600 rounded-xl shadow-sm p-6 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-orange-100 text-sm font-medium">Avg Profit Margin</p>
                            <p class="text-3xl font-bold">{{ number_format($this->metrics->get('avg_profit_margin'), 1) }}%</p>
                            <p class="text-sm text-orange-100 mt-1">Across all products</p>
                        </div>
                        <flux:icon name="chart-bar" class="size-8 text-orange-200" />
                    </div>
                </div>
            </div>
        @endif

        {{-- Charts Section --}}
        @if($showCharts && $selectedProduct)
            <div class="bg-white dark:bg-zinc-800 rounded-xl shadow-sm border border-zinc-200 dark:border-zinc-700 p-6">
                <div class="flex items-center justify-between mb-6">
                    <flux:heading size="lg" class="text-zinc-900 dark:text-zinc-100">
                        Sales Trend: {{ $this->productDetails['product']->title ?? 'Selected Product' }}
                    </flux:heading>
                    <flux:button variant="ghost" size="sm" wire:click="toggleCharts" icon="eye-slash">
                        Hide Chart
                    </flux:button>
                </div>
                <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-7 gap-4">
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

        {{-- Analytics Grid --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            {{-- Top Products Table --}}
            <div class="lg:col-span-2 bg-white dark:bg-zinc-800 rounded-xl shadow-sm border border-zinc-200 dark:border-zinc-700">
                <div class="p-6 border-b border-zinc-200 dark:border-zinc-700">
                    <div class="flex items-center justify-between">
                        <div>
                            <flux:heading size="lg" class="text-zinc-900 dark:text-zinc-100">Top Products</flux:heading>
                            <p class="text-sm text-zinc-600 dark:text-zinc-400 mt-1">
                                Showing {{ $this->products->count() }} top performing products
                            </p>
                        </div>
                        <div class="flex gap-2">
                            <flux:button 
                                variant="{{ $showOnlyWithSales ? 'primary' : 'ghost' }}" 
                                size="sm" 
                                wire:click="toggleSalesFilter"
                                icon="{{ $showOnlyWithSales ? 'eye' : 'eye-slash' }}"
                            >
                                {{ $showOnlyWithSales ? 'With Sales' : 'All Products' }}
                            </flux:button>
                            <flux:button 
                                variant="{{ $sortBy === 'revenue' ? 'primary' : 'ghost' }}" 
                                size="sm" 
                                wire:click="sortBy('revenue')"
                            >
                                Revenue
                                @if($sortBy === 'revenue')
                                    <flux:icon name="{{ $sortDirection === 'asc' ? 'arrow-up' : 'arrow-down' }}" class="size-3 ml-1" />
                                @endif
                            </flux:button>
                            <flux:button 
                                variant="{{ $sortBy === 'margin' ? 'primary' : 'ghost' }}" 
                                size="sm" 
                                wire:click="sortBy('margin')"
                            >
                                Margin
                                @if($sortBy === 'margin')
                                    <flux:icon name="{{ $sortDirection === 'asc' ? 'arrow-up' : 'arrow-down' }}" class="size-3 ml-1" />
                                @endif
                            </flux:button>
                        </div>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-zinc-50 dark:bg-zinc-700 border-b border-zinc-200 dark:border-zinc-600">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">Product</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">Sold</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">Revenue</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">Margin</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                            @forelse($this->products as $item)
                                @php $product = $item['product']; @endphp
                                <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-700 transition-colors {{ $selectedProduct === $product->sku ? 'bg-blue-50 dark:bg-blue-900/20' : '' }}">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100">
                                            {{ Str::limit($product->title, 30) }}
                                        </div>
                                        <div class="text-sm text-zinc-600 dark:text-zinc-400">{{ $product->sku }}</div>
                                        @if($product->category_name)
                                            <div class="text-xs text-zinc-500">{{ $product->category_name }}</div>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <flux:badge color="blue" size="sm">
                                            {{ number_format($item['total_sold']) }}
                                        </flux:badge>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100">
                                            £{{ number_format($item['total_revenue'], 2) }}
                                        </div>
                                        <div class="text-xs text-zinc-500">
                                            £{{ number_format($item['avg_selling_price'], 2) }} avg
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <flux:badge 
                                            color="{{ $item['profit_margin_percent'] >= 20 ? 'green' : ($item['profit_margin_percent'] >= 10 ? 'yellow' : 'red') }}" 
                                            size="sm"
                                        >
                                            {{ number_format($item['profit_margin_percent'], 1) }}%
                                        </flux:badge>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <flux:button variant="ghost" size="sm" wire:click="selectProduct('{{ $product->sku }}')">
                                            View
                                        </flux:button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-6 py-12 text-center">
                                        <div class="text-zinc-500 dark:text-zinc-400">
                                            <flux:icon name="cube" class="size-12 mx-auto mb-4 text-zinc-300 dark:text-zinc-600" />
                                            <p class="text-lg font-medium">No products found</p>
                                            <p class="text-sm">Try adjusting your search or sync some products</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            
            {{-- Sidebar --}}
            <div class="space-y-6">
                {{-- Top Categories --}}
                <div class="bg-white dark:bg-zinc-800 rounded-xl shadow-sm border border-zinc-200 dark:border-zinc-700 p-6">
                    <flux:heading size="lg" class="text-zinc-900 dark:text-zinc-100 mb-6">Top Categories</flux:heading>
                    <div class="space-y-4">
                        @forelse($this->topCategories as $index => $category)
                            <div class="flex items-center justify-between p-3 rounded-lg bg-zinc-50 dark:bg-zinc-700 border border-zinc-200 dark:border-zinc-600 cursor-pointer hover:bg-zinc-100 dark:hover:bg-zinc-600/50 {{ $selectedCategory === $category['category'] ? 'ring-2 ring-blue-500' : '' }}" 
                                 wire:click="selectCategory('{{ $category['category'] }}')">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-full bg-purple-100 dark:bg-purple-900 flex items-center justify-center text-purple-600 dark:text-purple-400 text-sm font-bold">
                                        {{ $index + 1 }}
                                    </div>
                                    <div>
                                        <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $category['category'] }}</div>
                                        <div class="text-sm text-zinc-600 dark:text-zinc-400">{{ $category['product_count'] }} products</div>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <div class="font-bold text-zinc-900 dark:text-zinc-100">£{{ number_format($category['total_revenue'], 0) }}</div>
                                    <div class="text-sm text-zinc-600 dark:text-zinc-400">{{ number_format($category['total_quantity']) }} units</div>
                                </div>
                            </div>
                        @empty
                            <div class="text-center py-8 text-zinc-500 dark:text-zinc-400">
                                <flux:icon name="tag" class="size-12 mx-auto mb-2 text-zinc-300 dark:text-zinc-600" />
                                <p>No categories found</p>
                            </div>
                        @endforelse
                    </div>
                </div>

                {{-- Stock Alerts --}}
                <div class="bg-white dark:bg-zinc-800 rounded-xl shadow-sm border border-zinc-200 dark:border-zinc-700 p-6">
                    <flux:heading size="lg" class="text-zinc-900 dark:text-zinc-100 mb-6">Stock Alerts</flux:heading>
                    <div class="space-y-4">
                        @forelse($this->stockAlerts as $alert)
                            @php $product = $alert['product']; @endphp
                            <div class="flex items-center justify-between p-3 rounded-lg bg-zinc-50 dark:bg-zinc-700 border border-zinc-200 dark:border-zinc-600">
                                <div>
                                    <div class="font-medium text-zinc-900 dark:text-zinc-100">
                                        {{ Str::limit($product->title, 20) }}
                                    </div>
                                    <div class="text-sm text-zinc-600 dark:text-zinc-400">{{ $product->sku }}</div>
                                </div>
                                <div class="text-right">
                                    <flux:badge color="red" size="sm">
                                        {{ $alert['stock_level'] }} left
                                    </flux:badge>
                                    <div class="text-xs text-zinc-500 mt-1">Min: {{ $alert['stock_minimum'] }}</div>
                                </div>
                            </div>
                        @empty
                            <div class="text-center py-8 text-zinc-500 dark:text-zinc-400">
                                <flux:icon name="check-circle" class="size-12 mx-auto mb-2 text-green-300 dark:text-green-600" />
                                <p>All stock levels OK</p>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

        {{-- Product Details Modal/Panel --}}
        @if($this->productDetails)
            @php 
                $details = $this->productDetails;
                $product = $details['product'];
                $profit = $details['profit_analysis'];
                $channels = $details['channel_performance'];
                $stock = $details['stock_info'];
            @endphp
            <div class="bg-white dark:bg-zinc-800 rounded-xl shadow-sm border border-zinc-200 dark:border-zinc-700">
                <div class="p-6 border-b border-zinc-200 dark:border-zinc-700">
                    <div class="flex items-center justify-between">
                        <div>
                            <flux:heading size="lg" class="text-zinc-900 dark:text-zinc-100">{{ $product->title }}</flux:heading>
                            <div class="flex items-center gap-4 text-sm text-zinc-600 dark:text-zinc-400 mt-1">
                                <span>SKU: {{ $product->sku }}</span>
                                @if($product->category_name)
                                    <span>•</span>
                                    <span>{{ $product->category_name }}</span>
                                @endif
                                <span>•</span>
                                <span>{{ number_format($profit['total_sold']) }} units sold</span>
                            </div>
                        </div>
                        <div class="flex gap-2">
                            @if(!$showCharts)
                                <flux:button variant="ghost" size="sm" wire:click="toggleCharts" icon="chart-bar">
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
                                £{{ number_format($profit['total_revenue'], 2) }}
                            </div>
                            <div class="text-sm text-blue-600/80 dark:text-blue-400/80">Total Revenue</div>
                        </div>
                        
                        {{-- Profit --}}
                        <div class="text-center p-4 rounded-lg bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800">
                            <div class="text-2xl font-bold text-green-600 dark:text-green-400">
                                £{{ number_format($profit['total_profit'], 2) }}
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
                                                <div class="font-bold text-zinc-900 dark:text-zinc-100">£{{ number_format($channel['revenue'], 2) }}</div>
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

        {{-- Action Buttons --}}
        <div class="flex justify-between items-center">
            <div class="flex gap-2">
                <flux:button variant="ghost" size="sm" wire:click="toggleMetrics" icon="{{ $showMetrics ? 'eye-slash' : 'eye' }}">
                    {{ $showMetrics ? 'Hide' : 'Show' }} Metrics
                </flux:button>
            </div>
            
            <div class="text-sm text-zinc-600 dark:text-zinc-400">
                Last updated: {{ now()->format('M j, Y g:i A') }}
            </div>
        </div>
    </div>
</div>