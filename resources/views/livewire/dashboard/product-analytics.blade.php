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
                    {{-- Enhanced Search Input --}}
                    <div class="relative min-w-96">
                        <div class="flex">
                            <flux:select 
                                wire:model.live="searchType" 
                                size="sm" 
                                class="rounded-r-none border-r-0 min-w-32"
                            >
                                @foreach($this->searchTypes as $type)
                                    <flux:select.option value="{{ $type['value'] }}">
                                        <div class="flex items-center gap-2">
                                            <flux:icon name="{{ $type['icon'] }}" class="size-3" />
                                            {{ $type['label'] }}
                                        </div>
                                    </flux:select.option>
                                @endforeach
                            </flux:select>
                            
                            <flux:input 
                                wire:model.live.debounce.300ms="search" 
                                placeholder="{{ $this->currentSearchType->getPlaceholder() }}" 
                                class="flex-1 rounded-l-none"
                                size="sm"
                            />
                            
                            @if($search)
                                <flux:button 
                                    variant="ghost" 
                                    size="sm" 
                                    wire:click="clearSearch"
                                    icon="x-mark"
                                    class="absolute right-2 top-1/2 transform -translate-y-1/2"
                                />
                            @endif
                        </div>
                        
                        {{-- Search Options Toggle --}}
                        @if($search)
                            <div class="absolute right-10 top-1/2 transform -translate-y-1/2">
                                <flux:button 
                                    variant="ghost" 
                                    size="xs" 
                                    wire:click="toggleSearchOptions"
                                    icon="cog-6-tooth"
                                    title="Search options"
                                />
                            </div>
                        @endif
                        
                        {{-- Autocomplete Suggestions --}}
                        @if(!empty($searchSuggestions))
                            <div class="absolute top-full left-0 right-0 mt-1 bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-lg shadow-lg z-50 max-h-60 overflow-y-auto">
                                @foreach($searchSuggestions as $suggestion)
                                    <div 
                                        class="px-4 py-2 hover:bg-zinc-50 dark:hover:bg-zinc-700 cursor-pointer border-b border-zinc-100 dark:border-zinc-600 last:border-b-0"
                                        wire:click="selectSearchSuggestion('{{ $suggestion['value'] }}')"
                                    >
                                        <div class="flex items-center gap-3">
                                            <flux:icon name="{{ $this->searchTypes->firstWhere('value', $suggestion['type'])['icon'] ?? 'magnifying-glass' }}" class="size-4 text-zinc-400" />
                                            <div class="flex-1">
                                                <div class="font-medium text-zinc-900 dark:text-zinc-100">
                                                    {!! $suggestion['highlight'] ?? $suggestion['label'] !!}
                                                </div>
                                                @if($suggestion['context'])
                                                    <div class="text-xs text-zinc-500">{{ $suggestion['context'] }}</div>
                                                @endif
                                            </div>
                                            <flux:badge color="zinc" size="xs">{{ $suggestion['type'] }}</flux:badge>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                    
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
                    
                    <flux:button 
                        variant="{{ $showFilters ? 'primary' : 'ghost' }}" 
                        size="sm" 
                        wire:click="toggleFilters" 
                        icon="funnel"
                    >
                        Filters
                        @if($this->activeFiltersCount > 0)
                            <flux:badge color="white" size="xs" class="ml-1">{{ $this->activeFiltersCount }}</flux:badge>
                        @endif
                    </flux:button>
                </div>
            </div>
        </div>

        {{-- Advanced Filters Panel --}}
        @if($showFilters)
            <div class="bg-white dark:bg-zinc-800 rounded-xl shadow-sm border border-zinc-200 dark:border-zinc-700 p-6">
                <div class="flex items-center justify-between mb-6">
                    <flux:heading size="lg" class="text-zinc-900 dark:text-zinc-100">Advanced Filters</flux:heading>
                    <div class="flex gap-2">
                        @if($this->activeFiltersCount > 0)
                            <flux:button variant="ghost" size="sm" wire:click="clearAllFilters" icon="x-mark">
                                Clear All
                            </flux:button>
                        @endif
                        <flux:button variant="ghost" size="sm" wire:click="toggleFilters" icon="x-mark">
                            Close
                        </flux:button>
                    </div>
                </div>

                {{-- Filter Presets --}}
                <div class="mb-6">
                    <flux:heading size="sm" class="text-zinc-700 dark:text-zinc-300 mb-3">Quick Filters</flux:heading>
                    <div class="flex flex-wrap gap-2">
                        @foreach($this->filterPresets as $presetKey => $preset)
                            <flux:button 
                                variant="{{ $activePreset === $presetKey ? 'primary' : 'outline' }}" 
                                size="sm" 
                                wire:click="applyPreset('{{ $presetKey }}')"
                                icon="{{ $preset['icon'] }}"
                                title="{{ $preset['description'] }}"
                            >
                                {{ $preset['label'] }}
                            </flux:button>
                        @endforeach
                    </div>
                </div>

                {{-- Individual Filters --}}
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    {{-- Profit Margin Filter --}}
                    <div>
                        <flux:field>
                            <flux:label>Profit Margin</flux:label>
                            <flux:select wire:model.live="filters.profit-margin" size="sm">
                                <flux:select.option value="">All Margins</flux:select.option>
                                <flux:select.option value="low">Low (0-10%)</flux:select.option>
                                <flux:select.option value="medium">Medium (10-25%)</flux:select.option>
                                <flux:select.option value="high">High (25-40%)</flux:select.option>
                                <flux:select.option value="premium">Premium (40%+)</flux:select.option>
                            </flux:select>
                        </flux:field>
                    </div>

                    {{-- Sales Velocity Filter --}}
                    <div>
                        <flux:field>
                            <flux:label>Sales Velocity</flux:label>
                            <flux:select wire:model.live="filters.sales-velocity" size="sm">
                                <flux:select.option value="">All Velocities</flux:select.option>
                                <flux:select.option value="slow">Slow (0-0.5/day)</flux:select.option>
                                <flux:select.option value="moderate">Moderate (0.5-2/day)</flux:select.option>
                                <flux:select.option value="fast">Fast (2-5/day)</flux:select.option>
                                <flux:select.option value="very-fast">Very Fast (5+/day)</flux:select.option>
                            </flux:select>
                        </flux:field>
                    </div>

                    {{-- Growth Rate Filter --}}
                    <div>
                        <flux:field>
                            <flux:label>Growth Trend</flux:label>
                            <flux:select wire:model.live="filters.growth-rate" size="sm">
                                <flux:select.option value="">All Trends</flux:select.option>
                                <flux:select.option value="declining">📉 Declining (-20%+)</flux:select.option>
                                <flux:select.option value="stable">➡️ Stable (-20% to +20%)</flux:select.option>
                                <flux:select.option value="growing">📈 Growing (+20% to +100%)</flux:select.option>
                                <flux:select.option value="surging">🚀 Surging (+100%+)</flux:select.option>
                            </flux:select>
                        </flux:field>
                    </div>

                    {{-- Revenue Tier Filter --}}
                    <div>
                        <flux:field>
                            <flux:label>Revenue Tier</flux:label>
                            <flux:select wire:model.live="filters.revenue-tier" size="sm">
                                <flux:select.option value="">All Revenue</flux:select.option>
                                <flux:select.option value="low">Low (£0-100)</flux:select.option>
                                <flux:select.option value="medium">Medium (£100-500)</flux:select.option>
                                <flux:select.option value="high">High (£500-2000)</flux:select.option>
                                <flux:select.option value="top">Top (£2000+)</flux:select.option>
                            </flux:select>
                        </flux:field>
                    </div>

                    {{-- Badge Filter --}}
                    <div>
                        <flux:field>
                            <flux:label>Performance Badge</flux:label>
                            <flux:select wire:model.live="filters.badge-type" size="sm">
                                <flux:select.option value="">All Products</flux:select.option>
                                <flux:select.option value="hot-seller">🔥 Hot Seller</flux:select.option>
                                <flux:select.option value="growing">📈 Growing</flux:select.option>
                                <flux:select.option value="declining">📉 Declining</flux:select.option>
                                <flux:select.option value="top-margin">⭐ Top Margin</flux:select.option>
                                <flux:select.option value="new-product">✨ New Product</flux:select.option>
                                <flux:select.option value="high-volume">📦 High Volume</flux:select.option>
                                <flux:select.option value="consistent">✅ Consistent</flux:select.option>
                            </flux:select>
                        </flux:field>
                    </div>

                    {{-- Category Filter --}}
                    <div>
                        <flux:field>
                            <flux:label>Category</flux:label>
                            <flux:select wire:model.live="filters.category" size="sm">
                                <flux:select.option value="">All Categories</flux:select.option>
                                @foreach($this->availableCategories as $category)
                                    <flux:select.option value="{{ $category }}">{{ $category }}</flux:select.option>
                                @endforeach
                            </flux:select>
                        </flux:field>
                    </div>
                </div>

                {{-- Active Filters Summary --}}
                @if($this->activeFiltersCount > 0)
                    <div class="mt-6 pt-6 border-t border-zinc-200 dark:border-zinc-700">
                        <flux:heading size="sm" class="text-zinc-700 dark:text-zinc-300 mb-3">Active Filters ({{ $this->activeFiltersCount }})</flux:heading>
                        <div class="flex flex-wrap gap-2">
                            @foreach($filters as $filterType => $filterValue)
                                @if(!is_null($filterValue) && $filterValue !== '')
                                    @php
                                        $filterEnum = \App\Enums\ProductFilterType::tryFrom($filterType);
                                        $filterOptions = $filterEnum?->getOptions() ?? [];
                                        $displayValue = $filterOptions[$filterValue]['label'] ?? $filterValue;
                                    @endphp
                                    <flux:badge 
                                        color="blue" 
                                        size="sm"
                                        class="cursor-pointer"
                                        wire:click="clearFilter('{{ $filterType }}')"
                                        title="Click to remove"
                                    >
                                        {{ $filterEnum?->label() ?? $filterType }}: {{ $displayValue }}
                                        <flux:icon name="x-mark" class="size-3 ml-1" />
                                    </flux:badge>
                                @endif
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        @endif

        {{-- Search Options Panel --}}
        @if($showSearchOptions && $search)
            <div class="bg-white dark:bg-zinc-800 rounded-xl shadow-sm border border-zinc-200 dark:border-zinc-700 p-6">
                <div class="flex items-center justify-between mb-4">
                    <flux:heading size="lg" class="text-zinc-900 dark:text-zinc-100">Search Options</flux:heading>
                    <flux:button variant="ghost" size="sm" wire:click="toggleSearchOptions" icon="x-mark">
                        Close
                    </flux:button>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    {{-- Search Type Info --}}
                    <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-4">
                        <div class="flex items-center gap-2 mb-2">
                            <flux:icon name="{{ $this->currentSearchType->getIcon() }}" class="size-5 text-blue-600 dark:text-blue-400" />
                            <flux:heading size="sm" class="text-blue-900 dark:text-blue-100">{{ $this->currentSearchType->label() }}</flux:heading>
                        </div>
                        <p class="text-sm text-blue-700 dark:text-blue-300">{{ $this->currentSearchType->getDescription() }}</p>
                        <div class="mt-3 space-y-1">
                            @foreach($this->currentSearchType->getSearchFields() as $field)
                                <flux:badge color="blue" size="xs">{{ $field }}</flux:badge>
                            @endforeach
                        </div>
                    </div>
                    
                    {{-- Search Options --}}
                    <div>
                        <flux:heading size="sm" class="text-zinc-900 dark:text-zinc-100 mb-3">Search Mode</flux:heading>
                        <div class="space-y-3">
                            <flux:checkbox 
                                wire:model.live="fuzzySearch" 
                                label="Fuzzy Search" 
                                description="Find partial matches and similar terms"
                                :disabled="!$this->currentSearchType->supportsFuzzySearch()"
                            />
                            <flux:checkbox 
                                wire:model.live="exactMatch" 
                                label="Exact Match" 
                                description="Match only exact phrases"
                            />
                        </div>
                    </div>
                    
                    {{-- Search Statistics --}}
                    <div>
                        <flux:heading size="sm" class="text-zinc-900 dark:text-zinc-100 mb-3">Search Stats</flux:heading>
                        <div class="space-y-2 text-sm">
                            <div class="flex justify-between">
                                <span class="text-zinc-600 dark:text-zinc-400">Query:</span>
                                <span class="font-mono text-zinc-900 dark:text-zinc-100">{{ $search }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-zinc-600 dark:text-zinc-400">Type:</span>
                                <span class="text-zinc-900 dark:text-zinc-100">{{ $this->currentSearchType->label() }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-zinc-600 dark:text-zinc-400">Results:</span>
                                <span class="text-zinc-900 dark:text-zinc-100">{{ $this->products->count() }} products</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endif

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
                                        
                                        {{-- Product Badges --}}
                                        @if(isset($item['badges']) && $item['badges'] && $item['badges']->isNotEmpty())
                                            <div class="flex flex-wrap gap-1 mt-2">
                                                @foreach($item['badges']->take(3) as $badge)
                                                    <flux:badge 
                                                        color="{{ $badge['color'] }}" 
                                                        size="xs"
                                                        title="{{ $badge['description'] }}"
                                                    >
                                                        <flux:icon name="{{ $badge['icon'] }}" class="size-3 mr-1" />
                                                        {{ $badge['label'] }}
                                                    </flux:badge>
                                                @endforeach
                                                @if(isset($item['badges']) && $item['badges'] && $item['badges']->count() > 3)
                                                    <flux:badge color="zinc" size="xs" title="View all badges">
                                                        +{{ $item['badges']->count() - 3 }}
                                                    </flux:badge>
                                                @endif
                                            </div>
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
                                        <div class="flex gap-2">
                                            <flux:button 
                                                variant="primary" 
                                                size="sm" 
                                                href="{{ route('products.detail', $product->sku) }}"
                                                icon="arrow-top-right-on-square"
                                            >
                                                Detail
                                            </flux:button>
                                            <flux:button variant="ghost" size="sm" wire:click="selectProduct('{{ $product->sku }}')">
                                                Quick View
                                            </flux:button>
                                        </div>
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