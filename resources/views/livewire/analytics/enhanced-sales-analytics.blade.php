<div>
    <div class="mb-6 flex items-center justify-between">
        <flux:heading size="xl">Sales Analytics</flux:heading>

        <div class="flex items-center gap-2">
            <flux:button
                wire:click="toggleComparison"
                variant="{{ $showComparison ? 'primary' : 'outline' }}"
                size="sm"
            >
                {{ $showComparison ? 'Hide' : 'Show' }} Comparison
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
    <div class="mb-8 bg-white dark:bg-zinc-900 rounded-lg shadow-sm border border-gray-200 dark:border-zinc-800">
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                {{-- Date Preset --}}
                <div>
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

                {{-- Channel Filter --}}
                <div>
                    <flux:field>
                        <flux:label>Channels ({{ count($channels) }} selected)</flux:label>
                        <flux:select wire:model.live="channels" multiple>
                            @foreach($this->availableChannels as $channel)
                                <flux:select.option value="{{ $channel }}">{{ $channel }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    </flux:field>
                </div>

                {{-- Actions --}}
                <div class="flex items-end gap-2">
                    <flux:button wire:click="clearFilters" variant="outline" size="sm">
                        Reset Filters
                    </flux:button>
                </div>
            </div>

            {{-- Active Filters Display --}}
            @if($this->filter->hasActiveFilters() || !empty($search))
                <div class="mt-4 flex flex-wrap gap-2">
                    @foreach($channels as $channel)
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                            {{ $channel }}
                            <button wire:click="toggleChannel('{{ $channel }}')" class="ml-2">×</button>
                        </span>
                    @endforeach
                </div>
            @endif

            {{-- Date Range Display --}}
            <div class="mt-4 text-sm text-gray-600 dark:text-gray-400">
                Showing data from {{ \Carbon\Carbon::parse($startDate)->format('M j, Y') }} to {{ \Carbon\Carbon::parse($endDate)->format('M j, Y') }}
            </div>
        </div>
    </div>

    {{-- Comparison Banner --}}
    @if($showComparison)
        <div class="mb-6 bg-gradient-to-r from-blue-50 to-indigo-50 dark:from-blue-900/20 dark:to-indigo-900/20 rounded-lg border border-blue-200 dark:border-blue-800 p-6">
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

    {{-- Key Metrics --}}
    <div class="mb-8 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
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

    {{-- Drill-Down Sections --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Channel Breakdown with Drill-Down --}}
        <div class="bg-white dark:bg-zinc-900 rounded-lg shadow-sm border border-gray-200 dark:border-zinc-800 p-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                Channel Performance
                <span class="text-sm font-normal text-gray-500 dark:text-gray-400">(Click to drill down)</span>
            </h3>
            <div class="space-y-3">
                @foreach($this->channelBreakdown->take(10) as $channel)
                    <button
                        wire:click="drillDownChannel('{{ $channel['name'] }}')"
                        class="w-full text-left p-3 rounded-lg hover:bg-gray-50 dark:hover:bg-zinc-800 transition-colors"
                    >
                        <div class="flex items-center justify-between mb-2">
                            <div>
                                <div class="font-medium text-gray-900 dark:text-white">{{ $channel['name'] }}</div>
                                @if($channel['subsource'])
                                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ $channel['subsource'] }}</div>
                                @endif
                            </div>
                            <div class="text-right">
                                <div class="font-semibold text-gray-900 dark:text-white">£{{ number_format($channel['revenue'], 2) }}</div>
                                <div class="text-sm text-gray-500 dark:text-gray-400">{{ $channel['orders'] }} orders</div>
                            </div>
                        </div>
                        <div class="w-full bg-gray-200 dark:bg-zinc-700 rounded-full h-2">
                            <div class="bg-blue-600 h-2 rounded-full" style="width: {{ min($channel['percentage'], 100) }}%"></div>
                        </div>
                    </button>
                @endforeach
            </div>
        </div>

        {{-- Product Breakdown with Drill-Down --}}
        <div class="bg-white dark:bg-zinc-900 rounded-lg shadow-sm border border-gray-200 dark:border-zinc-800 p-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                Top Products
                <span class="text-sm font-normal text-gray-500 dark:text-gray-400">(Click to view details)</span>
            </h3>
            <div class="space-y-3">
                @foreach($this->productBreakdown->take(10) as $product)
                    <button
                        wire:click="drillDownProduct('{{ $product['sku'] }}')"
                        class="w-full text-left p-3 rounded-lg hover:bg-gray-50 dark:hover:bg-zinc-800 transition-colors"
                    >
                        <div class="flex items-center justify-between">
                            <div class="flex-1 min-w-0">
                                <div class="font-medium text-gray-900 dark:text-white truncate">{{ $product['title'] }}</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">SKU: {{ $product['sku'] }}</div>
                            </div>
                            <div class="text-right ml-4">
                                <div class="font-semibold text-gray-900 dark:text-white">£{{ number_format($product['revenue'], 2) }}</div>
                                <div class="text-sm text-gray-500 dark:text-gray-400">{{ $product['quantity'] }} sold</div>
                            </div>
                        </div>
                    </button>
                @endforeach
            </div>
        </div>
    </div>
</div>
