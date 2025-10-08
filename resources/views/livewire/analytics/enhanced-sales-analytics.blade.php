<div>
    {{-- Header with Loading Indicator --}}
    <div class="mb-6 flex items-center justify-between">
        <div class="flex items-center gap-3">
            <flux:heading size="xl">Sales Analytics</flux:heading>

            {{-- Global Loading Spinner --}}
            <div wire:loading class="flex items-center gap-2 text-sm text-blue-600 dark:text-blue-400">
                <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span class="font-medium">Updating...</span>
            </div>
        </div>

        <div class="flex items-center gap-2">
            <flux:button
                wire:click="toggleComparison"
                wire:target="toggleComparison"
                wire:loading.attr="disabled"
                variant="{{ $showComparison ? 'primary' : 'outline' }}"
                size="sm"
            >
                <span wire:loading.remove wire:target="toggleComparison">
                    {{ $showComparison ? 'Hide' : 'Show' }} Comparison
                </span>
                <span wire:loading wire:target="toggleComparison" class="flex items-center gap-1">
                    <svg class="animate-spin h-3 w-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    Loading...
                </span>
            </flux:button>

            <flux:button
                wire:click="export('csv')"
                variant="outline"
                size="sm"
                icon="arrow-down-tray"
            >
                Export
            </flux:button>
        </div>
    </div>

    {{-- Filters Section --}}
    <div class="mb-8 bg-white dark:bg-zinc-900 rounded-lg shadow-sm border border-gray-200 dark:border-zinc-800 transition-opacity duration-200"
         wire:loading.class="opacity-50"
         wire:target="applyPreset,toggleChannel,startDate,endDate">
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                {{-- Date Preset --}}
                <div class="relative">
                    <flux:field>
                        <flux:label>Date Range</flux:label>
                        <flux:select wire:model.live="preset" wire:change="applyPreset($event.target.value)">
                            <flux:select.option value="last_7_days">Last 7 Days</flux:select.option>
                            <flux:select.option value="last_30_days">Last 30 Days</flux:select.option>
                            <flux:select.option value="last_90_days">Last 90 Days</flux:select.option>
                            <flux:select.option value="this_month">This Month</flux:select.option>
                            <flux:select.option value="last_month">Last Month</flux:select.option>
                            <flux:select.option value="year_to_date">Year to Date</flux:select.option>
                            <flux:select.option value="custom">Custom Range</flux:select.option>
                        </flux:select>
                    </flux:field>
                    <div wire:loading wire:target="applyPreset" class="absolute right-2 top-9">
                        <svg class="animate-spin h-4 w-4 text-blue-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                    </div>
                </div>

                {{-- Custom Date Range --}}
                @if($preset === 'custom')
                    <div>
                        <flux:field>
                            <flux:label>Start Date</flux:label>
                            <flux:input
                                type="date"
                                wire:model.blur="startDate"
                                max="{{ now()->format('Y-m-d') }}"
                            />
                        </flux:field>
                    </div>
                    <div>
                        <flux:field>
                            <flux:label>End Date</flux:label>
                            <flux:input
                                type="date"
                                wire:model.blur="endDate"
                                max="{{ now()->format('Y-m-d') }}"
                            />
                        </flux:field>
                    </div>
                @endif

                {{-- Channel Filter with Pill Selector --}}
                <div class="{{ $preset === 'custom' ? '' : 'md:col-span-2' }}">
                    <x-pill-selector
                        :options="$this->availableChannels->toArray()"
                        :selected="$channels"
                        label="Channels"
                        placeholder="All channels"
                    />
                    <div wire:loading wire:target="toggleChannel" class="text-xs text-blue-600 dark:text-blue-400 mt-1 flex items-center gap-1">
                        <svg class="animate-spin h-3 w-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Filtering...
                    </div>
                </div>
            </div>

            {{-- Date Range Display --}}
            <div class="mt-4 text-sm text-gray-600 dark:text-gray-400">
                Showing data from {{ \Carbon\Carbon::parse($startDate)->format('M j, Y') }} to {{ \Carbon\Carbon::parse($endDate)->format('M j, Y') }}
            </div>
        </div>
    </div>

    {{-- Comparison Banner with Transition --}}
    @if($showComparison)
        <div class="mb-6 bg-gradient-to-r from-blue-50 to-indigo-50 dark:from-blue-900/20 dark:to-indigo-900/20 rounded-lg border border-blue-200 dark:border-blue-800 p-6 transition-opacity duration-200"
             wire:loading.class="opacity-50"
             wire:target="toggleComparison">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                {{-- Revenue Comparison --}}
                <div>
                    <div class="text-sm font-medium text-gray-600 dark:text-gray-400 mb-1">Revenue vs Previous Period</div>
                    <div class="flex items-baseline gap-2">
                        <div class="text-2xl font-bold text-gray-900 dark:text-white">
                            £{{ number_format($this->comparison->currentRevenue, 2) }}
                        </div>
                        <div class="flex items-center text-sm {{ $this->comparison->isRevenueUp() ? 'text-green-600' : 'text-red-600' }}">
                            @if($this->comparison->isRevenueUp())
                                <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5.293 9.707a1 1 0 010-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 01-1.414 1.414L11 7.414V15a1 1 0 11-2 0V7.414L6.707 9.707a1 1 0 01-1.414 0z" clip-rule="evenodd"></path></svg>
                            @else
                                <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M14.707 10.293a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 111.414-1.414L9 12.586V5a1 1 0 012 0v7.586l2.293-2.293a1 1 0 011.414 0z" clip-rule="evenodd"></path></svg>
                            @endif
                            {{ abs(round($this->comparison->revenueChange(), 1)) }}%
                        </div>
                    </div>
                    <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                        Previous: £{{ number_format($this->comparison->previousRevenue, 2) }}
                    </div>
                </div>

                {{-- Orders Comparison --}}
                <div>
                    <div class="text-sm font-medium text-gray-600 dark:text-gray-400 mb-1">Orders vs Previous Period</div>
                    <div class="flex items-baseline gap-2">
                        <div class="text-2xl font-bold text-gray-900 dark:text-white">
                            {{ number_format($this->comparison->currentOrders) }}
                        </div>
                        <div class="flex items-center text-sm {{ $this->comparison->isOrdersUp() ? 'text-green-600' : 'text-red-600' }}">
                            @if($this->comparison->isOrdersUp())
                                <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5.293 9.707a1 1 0 010-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 01-1.414 1.414L11 7.414V15a1 1 0 11-2 0V7.414L6.707 9.707a1 1 0 01-1.414 0z" clip-rule="evenodd"></path></svg>
                            @else
                                <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M14.707 10.293a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 111.414-1.414L9 12.586V5a1 1 0 012 0v7.586l2.293-2.293a1 1 0 011.414 0z" clip-rule="evenodd"></path></svg>
                            @endif
                            {{ abs(round($this->comparison->ordersChange(), 1)) }}%
                        </div>
                    </div>
                    <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                        Previous: {{ number_format($this->comparison->previousOrders) }}
                    </div>
                </div>

                {{-- AOV Comparison --}}
                <div>
                    <div class="text-sm font-medium text-gray-600 dark:text-gray-400 mb-1">Avg Order Value vs Previous</div>
                    <div class="flex items-baseline gap-2">
                        <div class="text-2xl font-bold text-gray-900 dark:text-white">
                            £{{ number_format($this->comparison->currentAvgOrderValue, 2) }}
                        </div>
                        <div class="flex items-center text-sm {{ $this->comparison->isAvgOrderValueUp() ? 'text-green-600' : 'text-red-600' }}">
                            @if($this->comparison->isAvgOrderValueUp())
                                <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5.293 9.707a1 1 0 010-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 01-1.414 1.414L11 7.414V15a1 1 0 11-2 0V7.414L6.707 9.707a1 1 0 01-1.414 0z" clip-rule="evenodd"></path></svg>
                            @else
                                <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M14.707 10.293a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 111.414-1.414L9 12.586V5a1 1 0 012 0v7.586l2.293-2.293a1 1 0 011.414 0z" clip-rule="evenodd"></path></svg>
                            @endif
                            {{ abs(round($this->comparison->avgOrderValueChange(), 1)) }}%
                        </div>
                    </div>
                    <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                        Previous: £{{ number_format($this->comparison->previousAvgOrderValue, 2) }}
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Key Metrics with Loading State --}}
    <div class="mb-8 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 transition-opacity duration-200"
         wire:loading.class="opacity-50">
        <div class="bg-white dark:bg-zinc-900 rounded-lg shadow-sm border border-gray-200 dark:border-zinc-800 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Total Revenue</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">
                        £{{ number_format($this->summary['total_revenue'], 2) }}
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
                        {{ number_format($this->summary['total_orders']) }}
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
                        {{ number_format($this->summary['processed_orders']) }}
                    </p>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        £{{ number_format($this->summary['processed_revenue'], 2) }}
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
                        {{ number_format($this->summary['open_orders']) }}
                    </p>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        £{{ number_format($this->summary['open_revenue'], 2) }}
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

    {{-- Drill-Down Sections with Enhanced Design --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 transition-opacity duration-200"
         wire:loading.class="opacity-50">
        {{-- Channel Breakdown with Drill-Down --}}
        <div class="bg-white dark:bg-zinc-900 rounded-lg shadow-sm border border-gray-200 dark:border-zinc-800 p-6">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                    Channel Performance
                </h3>
                <span class="text-xs text-gray-500 dark:text-gray-400 bg-gray-100 dark:bg-zinc-800 px-2 py-1 rounded-full">
                    Top {{ $this->channelBreakdown->count() }}
                </span>
            </div>

            @if($this->channelBreakdown->isEmpty())
                <div class="text-center py-12">
                    <svg class="w-16 h-16 mx-auto text-gray-400 dark:text-zinc-600 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                    </svg>
                    <p class="text-gray-600 dark:text-gray-400">No channel data available</p>
                </div>
            @else
                <div class="space-y-2">
                    @foreach($this->channelBreakdown->take(10) as $index => $channel)
                        @php
                            // Business color palette - subtle and professional
                            $colors = [
                                ['bg' => 'bg-primary-500', 'text' => 'text-primary-600', 'ring' => 'ring-primary-500/20', 'light' => 'bg-primary-50 dark:bg-primary-900/10'],
                                ['bg' => 'bg-secondary-400', 'text' => 'text-secondary-600', 'ring' => 'ring-secondary-500/20', 'light' => 'bg-secondary-50 dark:bg-secondary-800/20'],
                                ['bg' => 'bg-success-500', 'text' => 'text-success-700', 'ring' => 'ring-success-500/20', 'light' => 'bg-success-50 dark:bg-success-900/10'],
                                ['bg' => 'bg-zinc-500', 'text' => 'text-zinc-600', 'ring' => 'ring-zinc-500/20', 'light' => 'bg-zinc-50 dark:bg-zinc-800/50'],
                                ['bg' => 'bg-accent-500', 'text' => 'text-accent-600', 'ring' => 'ring-accent-500/20', 'light' => 'bg-accent-50 dark:bg-accent-900/10'],
                            ];
                            $color = $colors[$index % count($colors)];
                        @endphp
                        <button
                            wire:click="drillDownChannel('{{ $channel['name'] }}')"
                            x-data="{ hover: false }"
                            @mouseenter="hover = true"
                            @mouseleave="hover = false"
                            class="w-full group relative overflow-hidden rounded-lg border border-gray-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-4 transition-all duration-200 hover:border-primary-300 dark:hover:border-primary-800"
                            :class="{ 'scale-[1.01] shadow-lg': hover }"
                        >
                            {{-- Rank Badge --}}
                            @if($index < 3)
                                <div class="absolute top-3 right-3">
                                    <div class="flex items-center justify-center w-8 h-8 rounded-full {{ $color['light'] }} {{ $color['text'] }} text-xs font-bold border border-{{ $color['text'] }}/20">
                                        #{{ $index + 1 }}
                                    </div>
                                </div>
                            @endif

                            <div class="flex items-start gap-4">
                                {{-- Channel Icon --}}
                                <div class="flex-shrink-0 w-12 h-12 rounded-lg {{ $color['light'] }} flex items-center justify-center group-hover:scale-105 transition-transform duration-200 border border-{{ $color['text'] }}/10">
                                    <svg class="w-6 h-6 {{ $color['text'] }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                                    </svg>
                                </div>

                                {{-- Content --}}
                                <div class="flex-1 min-w-0 {{ $index < 3 ? 'pr-10' : '' }}">
                                    <div class="flex items-start justify-between mb-3">
                                        <div class="flex-1">
                                            <div class="font-semibold text-gray-900 dark:text-white text-left">
                                                {{ $channel['name'] }}
                                            </div>
                                            @if($channel['subsource'])
                                                <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                                    {{ $channel['subsource'] }}
                                                </div>
                                            @endif
                                        </div>
                                        <div class="text-right ml-4">
                                            <div class="text-lg font-bold text-gray-900 dark:text-white">
                                                £{{ number_format($channel['revenue'], 0) }}
                                            </div>
                                            <div class="text-xs text-gray-500 dark:text-gray-400">
                                                {{ $channel['orders'] }} orders
                                            </div>
                                        </div>
                                    </div>

                                    {{-- Enhanced Progress Bar --}}
                                    <div class="relative">
                                        <div class="w-full bg-gray-100 dark:bg-secondary-800/30 rounded-full h-2.5 overflow-hidden shadow-inner">
                                            <div class="{{ $color['bg'] }} h-2.5 rounded-full transition-all duration-500 ease-out"
                                                 style="width: {{ min($channel['percentage'], 100) }}%"></div>
                                        </div>
                                        <div class="flex items-center justify-between mt-2">
                                            <span class="text-xs font-semibold {{ $color['text'] }}">
                                                {{ number_format($channel['percentage'], 1) }}%
                                            </span>
                                            <span class="text-xs text-gray-500 dark:text-gray-400">
                                                £{{ number_format($channel['avg_order_value'], 0) }} avg
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </button>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Product Breakdown with Drill-Down --}}
        <div class="bg-white dark:bg-zinc-900 rounded-lg shadow-sm border border-gray-200 dark:border-zinc-800 p-6">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                    Top Products
                </h3>
                <span class="text-xs text-gray-500 dark:text-gray-400 bg-gray-100 dark:bg-zinc-800 px-2 py-1 rounded-full">
                    {{ $this->productBreakdown->count() }} shown
                </span>
            </div>

            @if($this->productBreakdown->isEmpty())
                <div class="text-center py-12">
                    <svg class="w-16 h-16 mx-auto text-gray-400 dark:text-zinc-600 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                    </svg>
                    <p class="text-gray-600 dark:text-gray-400">No product data available</p>
                </div>
            @else
                <div class="space-y-2">
                    @foreach($this->productBreakdown->take(10) as $index => $product)
                        <button
                            wire:click="drillDownProduct('{{ $product['sku'] }}')"
                            x-data="{ hover: false }"
                            @mouseenter="hover = true"
                            @mouseleave="hover = false"
                            class="w-full group relative overflow-hidden rounded-lg border border-gray-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-4 hover:border-primary-300 dark:hover:border-primary-800 transition-all duration-200"
                            :class="{ 'scale-[1.01] shadow-lg': hover }"
                        >
                            <div class="flex items-center gap-4">
                                {{-- Product Image Placeholder --}}
                                <div class="flex-shrink-0">
                                    <div class="w-14 h-14 rounded-lg bg-gradient-to-br from-secondary-300 to-secondary-500 dark:from-secondary-600 dark:to-secondary-700 flex items-center justify-center text-white font-bold text-lg group-hover:scale-105 transition-transform duration-200 shadow-sm border border-secondary-400/20">
                                        {{ strtoupper(substr($product['title'], 0, 2)) }}
                                    </div>
                                </div>

                                {{-- Product Info --}}
                                <div class="flex-1 min-w-0 text-left">
                                    <div class="flex items-start justify-between gap-4">
                                        <div class="flex-1 min-w-0">
                                            <div class="font-semibold text-gray-900 dark:text-white truncate">
                                                {{ $product['title'] }}
                                            </div>
                                            <div class="flex items-center gap-2 mt-1">
                                                <span class="text-xs text-gray-500 dark:text-gray-400 font-mono">
                                                    {{ $product['sku'] }}
                                                </span>
                                                @if($index < 3)
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-success-100 text-success-800 dark:bg-success-900/30 dark:text-success-400 border border-success-300/30">
                                                        <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                            <path d="M10 12a2 2 0 100-4 2 2 0 000 4z"></path>
                                                            <path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd"></path>
                                                        </svg>
                                                        Top Seller
                                                    </span>
                                                @endif
                                            </div>
                                        </div>

                                        <div class="text-right flex-shrink-0">
                                            <div class="text-lg font-bold text-gray-900 dark:text-white">
                                                £{{ number_format($product['revenue'], 0) }}
                                            </div>
                                            <div class="text-xs text-gray-500 dark:text-gray-400">
                                                {{ $product['quantity'] }} sold
                                            </div>
                                        </div>
                                    </div>

                                    {{-- Mini Revenue Bar --}}
                                    <div class="mt-3">
                                        @php
                                            $maxRevenue = $this->productBreakdown->first()['revenue'] ?? 1;
                                            $percentage = ($product['revenue'] / $maxRevenue) * 100;
                                        @endphp
                                        <div class="w-full bg-gray-100 dark:bg-secondary-800/30 rounded-full h-2 overflow-hidden shadow-inner">
                                            <div class="bg-primary-500 h-2 rounded-full transition-all duration-500"
                                                 style="width: {{ $percentage }}%"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </button>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</div>
