<div class="space-y-10">
    @php
        use Carbon\Carbon;

        $dateLabel = Carbon::parse($startDate)->format('M j, Y') . ' – ' . Carbon::parse($endDate)->format('M j, Y');
        $channelCount = is_array($selectedChannels) ? count($selectedChannels) : 0;
        $ordersCount = $this->orders->count();
    @endphp

    <!-- Page Heading -->
    <div class="flex flex-wrap items-start justify-between gap-6">
        <div class="space-y-2">
            <div class="flex items-center gap-3">
                <span class="text-sm font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Analytics</span>
                <div wire:loading class="flex items-center gap-2 text-sm text-primary-600 dark:text-primary-400">
                    <svg class="h-4 w-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span>Updating...</span>
                </div>
            </div>
            <h1 class="text-3xl font-semibold text-zinc-900 dark:text-zinc-50">Sales Analytics</h1>
            <p class="text-sm text-zinc-500 dark:text-zinc-400">
                Monitoring <span class="font-medium text-zinc-800 dark:text-zinc-200">{{ number_format($ordersCount) }}</span> orders between
                <span class="font-medium text-zinc-800 dark:text-zinc-200">{{ $dateLabel }}</span>
                across <span class="font-medium text-zinc-800 dark:text-zinc-200">{{ $channelCount }}</span> channel{{ $channelCount === 1 ? '' : 's' }}.
            </p>
        </div>

    <!-- Filters -->
    <div
        class="rounded-2xl border border-zinc-200 bg-white px-6 py-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900"
        wire:loading.class="opacity-50"
        wire:target="datePreset,startDate,endDate,selectedChannels"
    >
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div class="flex flex-1 flex-col gap-4 sm:flex-row">
                <div class="w-full sm:w-48">
                    <flux:label class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Date range</flux:label>
                    <flux:select wire:model.live="datePreset" size="sm" class="mt-1">
                        <flux:select.option value="last_7_days">Last 7 days</flux:select.option>
                        <flux:select.option value="last_30_days">Last 30 days</flux:select.option>
                        <flux:select.option value="last_90_days">Last 90 days</flux:select.option>
                        <flux:select.option value="last_6_months">Last 6 months</flux:select.option>
                        <flux:select.option value="last_year">Last year</flux:select.option>
                        <flux:select.option value="year_to_date">Year to date</flux:select.option>
                        <flux:select.option value="custom">Custom range</flux:select.option>
                    </flux:select>
                </div>

                @if($datePreset === 'custom')
                    <div class="flex w-full flex-col gap-2 sm:w-auto sm:flex-row">
                        <flux:input
                            type="date"
                            wire:model.live="startDate"
                            max="{{ now()->format('Y-m-d') }}"
                            class="sm:w-44"
                        />
                        <flux:input
                            type="date"
                            wire:model.live="endDate"
                            max="{{ now()->format('Y-m-d') }}"
                            class="sm:w-44"
                        />
                    </div>
                @endif

                <div class="flex-1 min-w-[200px]">
                    <x-pill-selector
                        :options="$this->availableChannels->map(fn ($channel) => ['value' => $channel, 'label' => $channel])->toArray()"
                        :selected="$selectedChannels"
                        label="Channels"
                        placeholder="Select channels"
                    />
                </div>
            </div>

            <div class="flex items-center gap-3">
                <span class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ $ordersCount }} orders</span>
                <flux:button variant="outline" size="sm" icon="arrow-path" wire:click="resetFilters">Reset</flux:button>
            </div>
        </div>

        <div
            wire:loading.delay
            wire:target="datePreset,startDate,endDate,selectedChannels"
            class="mt-4 flex items-center gap-2 text-sm text-blue-600 dark:text-blue-300"
            role="status"
            aria-live="polite"
        >
            <svg class="h-4 w-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <span>Refreshing analytics…</span>
        </div>
    </div>

    <!-- KPI Row -->
    <div class="grid grid-cols-1 gap-4 xl:grid-cols-6" wire:loading.class="opacity-50">
        <div class="rounded-2xl border border-zinc-200 bg-white px-5 py-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <p class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Total revenue</p>
            <p class="mt-1 text-2xl font-semibold text-zinc-900 dark:text-zinc-50">£{{ number_format($this->salesMetrics->totalRevenue(), 2) }}</p>
        </div>

        <div class="rounded-2xl border border-zinc-200 bg-white px-5 py-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <p class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Avg. order value</p>
            <p class="mt-1 text-2xl font-semibold text-zinc-900 dark:text-zinc-50">£{{ number_format($this->salesMetrics->averageOrderValue(), 2) }}</p>
        </div>

        @php
            $bestDay = $this->salesMetrics->bestPerformingDay($this->startDate, $this->endDate);
        @endphp
        @if($bestDay)
            <div class="rounded-2xl border border-primary-200 bg-primary-50 px-5 py-4 shadow-sm dark:border-primary-800 dark:bg-primary-900/20">
                <div class="flex items-center gap-2">
                    <p class="text-xs font-medium uppercase tracking-wide text-primary-700 dark:text-primary-300">Best day</p>
                    <flux:icon name="star" class="size-3 text-primary-600 dark:text-primary-400" />
                </div>
                <p class="mt-1 text-2xl font-semibold text-primary-900 dark:text-primary-50">£{{ number_format($bestDay['revenue'], 2) }}</p>
                <p class="mt-1 text-xs text-primary-600 dark:text-primary-400">{{ $bestDay['date'] }} • {{ $bestDay['orders'] }} orders</p>
            </div>
        @endif

        <div class="rounded-2xl border border-zinc-200 bg-white px-5 py-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Processed orders</p>
                    <p class="mt-1 text-2xl font-semibold text-zinc-900 dark:text-zinc-50">{{ number_format($this->salesMetrics->totalProcessedOrders()) }}</p>
                </div>
                <div class="rounded-full bg-success-100 p-3 text-success-700 dark:bg-success-900/40 dark:text-success-300">
                    <flux:icon name="check-circle" class="size-5" />
                </div>
            </div>
            <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">£{{ number_format($this->salesMetrics->processedOrdersRevenue(), 2) }}</p>
        </div>

        <div class="rounded-2xl border border-zinc-200 bg-white px-5 py-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Open orders</p>
                    <p class="mt-1 text-2xl font-semibold text-zinc-900 dark:text-zinc-50">{{ number_format($this->salesMetrics->totalOpenOrders()) }}</p>
                </div>
                <div class="rounded-full bg-amber-100 p-3 text-amber-600 dark:bg-amber-900/40 dark:text-amber-300">
                    <flux:icon name="clock" class="size-5" />
                </div>
            </div>
            <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">£{{ number_format($this->salesMetrics->openOrdersRevenue(), 2) }}</p>
        </div>

        <div class="rounded-2xl border border-zinc-200 bg-white px-5 py-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Avg. items/order</p>
                    <p class="mt-1 text-2xl font-semibold text-zinc-900 dark:text-zinc-50">
                        {{ number_format($ordersCount > 0 ? $this->salesMetrics->totalItemsSold() / $ordersCount : 0, 1) }}
                    </p>
                </div>
                <div class="rounded-full bg-primary-100 p-3 text-primary-600 dark:bg-primary-900/40 dark:text-primary-300">
                    <flux:icon name="cursor-arrow-ripple" class="size-5" />
                </div>
            </div>
            <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">{{ number_format($this->salesMetrics->totalItemsSold()) }} units sold</p>
        </div>
    </div>

    <!-- Tabs -->
    @php
        $tabs = [
            'overview' => 'Overview',
            'trends' => 'Trends',
            'channels' => 'Channels',
            'orders' => 'Orders',
            'products' => 'Products',
        ];
    @endphp

    <div class="flex flex-wrap items-center gap-2">
        @foreach($tabs as $key => $label)
            <button
                wire:click="setTab('{{ $key }}')"
                class="rounded-full border px-4 py-2 text-sm font-medium transition {{ $activeTab === $key
                    ? 'border-primary-500 bg-primary-500 text-white shadow-sm'
                    : 'border-zinc-200 bg-white text-zinc-600 hover:border-zinc-300 hover:text-zinc-800 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-300 dark:hover:border-zinc-600' }}"
            >
                {{ $label }}
            </button>
        @endforeach
    </div>

    <!-- Overview -->
    @if($activeTab === 'overview')
        <div class="grid grid-cols-1 gap-6 xl:grid-cols-3" wire:loading.class="opacity-50" wire:target="activeTab">
            <div class="space-y-6 xl:col-span-2">
                <div class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                    <div class="flex flex-wrap items-center justify-between gap-4">
                        <div>
                            <h3 class="text-lg font-semibold text-zinc-900 dark:text-zinc-50">Performance trend</h3>
                            <p class="text-sm text-zinc-500 dark:text-zinc-400">Choose how you would like to visualise the period.</p>
                        </div>
                        <div class="flex items-center gap-3">
                            <flux:select wire:model.live="chartType" size="sm">
                                <flux:select.option value="line">Revenue trend</flux:select.option>
                                <flux:select.option value="bar">Revenue comparison</flux:select.option>
                                <flux:select.option value="order_count">Order volume</flux:select.option>
                            </flux:select>
                        </div>
                    </div>
                    <div class="mt-6 h-80">
                        @if($chartType === 'line')
                            <livewire:chart
                                type="line"
                                :data="$this->chartData"
                                :options="$this->chartInteractionOptions"
                                wire:key="overview-line-{{ $startDate }}-{{ $endDate }}"
                            />
                        @elseif($chartType === 'bar')
                            <livewire:chart
                                type="bar"
                                :data="$this->chartData"
                                :options="$this->chartInteractionOptions"
                                wire:key="overview-bar-{{ $startDate }}-{{ $endDate }}"
                            />
                        @else
                            <livewire:chart
                                type="bar"
                                :data="$this->chartData"
                                :options="$this->chartInteractionOptions"
                                wire:key="overview-count-{{ $startDate }}-{{ $endDate }}"
                            />
                        @endif
                    </div>
                </div>

                <div class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                    <h3 class="text-lg font-semibold text-zinc-900 dark:text-zinc-50 mb-4">Daily revenue</h3>
                    <div class="h-72">
                        <livewire:chart
                            type="bar"
                            :data="$this->salesMetrics->getBarChartData($this->getChartPeriod(), $this->startDate, $this->endDate)"
                            wire:key="overview-daily-{{ $startDate }}-{{ $endDate }}"
                        />
                    </div>
                </div>
            </div>

            <div class="space-y-6">
                <div class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                    <h3 class="text-lg font-semibold text-zinc-900 dark:text-zinc-50">Channel mix</h3>
                    <p class="text-sm text-zinc-500 dark:text-zinc-400 mb-4">Contribution to revenue by sales channel.</p>
                    <div class="h-64">
                        <livewire:chart type="doughnut" :data="$this->salesMetrics->getDoughnutChartData()" wire:key="overview-channel-{{ $startDate }}-{{ $endDate }}" />
                    </div>
                </div>

                <div class="rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900 overflow-hidden">
                    <div class="border-b border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-900/60 px-6 py-4">
                        <h3 class="text-lg font-semibold text-zinc-900 dark:text-zinc-50">Top products</h3>
                        <p class="text-sm text-zinc-500 dark:text-zinc-400">Best sellers by revenue</p>
                    </div>
                    <div class="divide-y divide-zinc-200 dark:divide-zinc-800">
                        @foreach($this->salesMetrics->topProducts(5) as $index => $product)
                            <div class="p-5 hover:bg-gray-50 dark:hover:bg-zinc-800/50 transition-colors">
                                <div class="flex items-start justify-between gap-4">
                                    <div class="flex items-start gap-3">
                                        <span class="inline-flex items-center justify-center w-7 h-7 rounded-full text-xs font-bold bg-secondary-100 text-secondary-700 dark:bg-secondary-800 dark:text-secondary-200">
                                            {{ $index + 1 }}
                                        </span>
                                        <div>
                                            <p class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ $product['title'] }}</p>
                                            <p class="text-xs text-zinc-500 dark:text-zinc-400">SKU {{ $product['sku'] }}</p>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">£{{ number_format($product['revenue'], 2) }}</p>
                                        <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ $product['quantity'] }} units</p>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- Trends -->
    @if($activeTab === 'trends')
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2" wire:loading.class="opacity-50" wire:target="activeTab">
            <div class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <h3 class="text-lg font-semibold text-zinc-900 dark:text-zinc-50 mb-4">Revenue vs order volume</h3>
                <livewire:chart type="line" :data="$this->salesMetrics->getLineChartData($this->getChartPeriod(), $this->startDate, $this->endDate)" wire:key="trends-revenue-{{ $startDate }}-{{ $endDate }}" />
            </div>
            <div class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <h3 class="text-lg font-semibold text-zinc-900 dark:text-zinc-50 mb-4">Order status split</h3>
                <livewire:chart type="bar" :data="$this->salesMetrics->getOrderCountChartData($this->getChartPeriod(), $this->startDate, $this->endDate)" wire:key="trends-status-{{ $startDate }}-{{ $endDate }}" />
            </div>
        </div>
    @endif

    <!-- Channels -->
    @if($activeTab === 'channels')
        <div class="rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900 overflow-hidden" wire:loading.class="opacity-50" wire:target="activeTab">
            <div class="border-b border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-900/60 px-6 py-4">
                <h3 class="text-lg font-semibold text-zinc-900 dark:text-zinc-50">Channel performance</h3>
                <p class="text-sm text-zinc-500 dark:text-zinc-400">Sales breakdown by channel with revenue and order metrics</p>
            </div>

            <div class="divide-y divide-zinc-200 dark:divide-zinc-800">
                @php
                    $channels = $this->salesMetrics->topChannels(limit: 20);
                    $totalRevenue = $this->salesMetrics->totalRevenue();
                @endphp

                @forelse($channels as $index => $channel)
                    <button
                        wire:click="toggleChannel('{{ $channel['name'] }}')"
                        class="w-full p-5 hover:bg-gray-50 dark:hover:bg-zinc-800/50 transition-colors text-left"
                    >
                        <div class="flex items-start justify-between gap-4">
                            <div class="flex items-start gap-4 flex-1">
                                @php
                                    $badgeClass = match($index) {
                                        0 => 'bg-primary-100 text-primary-700 dark:bg-primary-900/40 dark:text-primary-300',
                                        1 => 'bg-accent-100 text-accent-700 dark:bg-accent-900/40 dark:text-accent-300',
                                        2 => 'bg-success-100 text-success-700 dark:bg-success-900/40 dark:text-success-300',
                                        default => 'bg-secondary-100 text-secondary-700 dark:bg-secondary-800 dark:text-secondary-200',
                                    };
                                @endphp
                                <span class="inline-flex items-center justify-center w-8 h-8 rounded-full text-sm font-bold {{ $badgeClass }}">
                                    {{ $index + 1 }}
                                </span>

                                <div class="flex-1">
                                    <div class="flex items-center gap-2 mb-2">
                                        <h4 class="text-base font-semibold text-zinc-900 dark:text-zinc-100">{{ $channel['name'] }}</h4>
                                        @if($channel['subsource'])
                                            <span class="text-xs text-zinc-500 dark:text-zinc-400">({{ $channel['subsource'] }})</span>
                                        @endif
                                    </div>

                                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-3">
                                        <div>
                                            <p class="text-xs text-zinc-500 dark:text-zinc-400">Revenue</p>
                                            <p class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">£{{ number_format($channel['revenue'], 2) }}</p>
                                        </div>
                                        <div>
                                            <p class="text-xs text-zinc-500 dark:text-zinc-400">Orders</p>
                                            <p class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ number_format($channel['orders']) }}</p>
                                        </div>
                                        <div>
                                            <p class="text-xs text-zinc-500 dark:text-zinc-400">Avg. Order Value</p>
                                            <p class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">£{{ number_format($channel['avg_order_value'], 2) }}</p>
                                        </div>
                                        <div>
                                            <p class="text-xs text-zinc-500 dark:text-zinc-400">Share</p>
                                            <p class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ number_format($channel['percentage'], 1) }}%</p>
                                        </div>
                                    </div>

                                    <!-- Progress bar -->
                                    <div class="w-full bg-zinc-200 dark:bg-zinc-700 rounded-full h-2">
                                        <div class="bg-primary-500 h-2 rounded-full transition-all duration-300" style="width: {{ min($channel['percentage'], 100) }}%"></div>
                                    </div>
                                </div>
                            </div>

                            <div class="flex items-center gap-2">
                                <flux:icon name="arrow-right" class="size-5 text-zinc-400" />
                            </div>
                        </div>
                    </button>
                @empty
                    <div class="px-6 py-12 text-center text-sm text-zinc-500 dark:text-zinc-400">
                        No channel data available for the selected filters.
                    </div>
                @endforelse
            </div>
        </div>
    @endif

    <!-- Orders -->
    @if($activeTab === 'orders')
        <div class="rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900" wire:loading.class="opacity-50" wire:target="activeTab,setSortBy,perPage">
            <div class="flex flex-wrap items-center justify-between gap-4 border-b border-zinc-200 px-6 py-4 dark:border-zinc-700">
                <div>
                    <h3 class="text-lg font-semibold text-zinc-900 dark:text-zinc-50">Orders</h3>
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">Sorted by {{ str_replace('_', ' ', $sortBy) }} ({{ $sortDirection }})</p>
                </div>
                <div class="flex items-center gap-3">
                    <flux:label class="text-sm">Rows</flux:label>
                    <flux:select wire:model.live="perPage" size="sm">
                        <flux:select.option value="25">25</flux:select.option>
                        <flux:select.option value="50">50</flux:select.option>
                        <flux:select.option value="100">100</flux:select.option>
                    </flux:select>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
                    <thead class="bg-zinc-50 text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:bg-zinc-900 dark:text-zinc-400">
                        <tr>
                            <th class="px-6 py-3 text-left cursor-pointer" wire:click="setSortBy('number')">Order #</th>
                            <th class="px-6 py-3 text-left cursor-pointer" wire:click="setSortBy('received_at')">Date</th>
                            <th class="px-6 py-3 text-left cursor-pointer" wire:click="setSortBy('source')">Channel</th>
                            <th class="px-6 py-3 text-right cursor-pointer" wire:click="setSortBy('total_charge')">Total</th>
                            <th class="px-6 py-3 text-right">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 bg-white text-sm text-zinc-600 dark:divide-zinc-700 dark:bg-zinc-900 dark:text-zinc-300">
                        @forelse($this->paginatedOrders as $order)
                            <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800">
                                <td class="px-6 py-4">
                                    <div class="font-medium text-zinc-900 dark:text-zinc-100">#{{ $order->number }}</div>
                                    <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ Str::limit($order->order_id, 10) }}</div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ Carbon::parse($order->received_at)->format('M j, Y') }}</div>
                                    <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ Carbon::parse($order->received_at)->format('H:i') }}</div>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex items-center rounded-full bg-zinc-100 px-2.5 py-0.5 text-xs font-medium text-zinc-700 dark:bg-zinc-800 dark:text-zinc-200">
                                        {{ $order->source }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-right font-medium text-zinc-900 dark:text-zinc-100">£{{ number_format($order->total_charge, 2) }}</td>
                                <td class="px-6 py-4 text-right">
                                    <flux:badge color="{{ $order->is_open ? 'blue' : 'zinc' }}" size="sm">
                                        {{ $order->is_open ? 'Open' : 'Closed' }}
                                    </flux:badge>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-12 text-center text-sm text-zinc-500 dark:text-zinc-400">
                                    No orders match the current filters.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <!-- Products -->
    @if($activeTab === 'products')
        <div class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900" wire:loading.class="opacity-50" wire:target="activeTab">
            <h3 class="text-lg font-semibold text-zinc-900 dark:text-zinc-50 mb-6">Top products</h3>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
                    <thead class="bg-zinc-50 text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:bg-zinc-900 dark:text-zinc-400">
                        <tr>
                            <th class="px-6 py-3 text-left">Product</th>
                            <th class="px-6 py-3 text-right">Revenue</th>
                            <th class="px-6 py-3 text-right">Quantity</th>
                            <th class="px-6 py-3 text-right">Orders</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 bg-white text-sm text-zinc-600 dark:divide-zinc-700 dark:bg-zinc-900 dark:text-zinc-300">
                        @forelse($this->salesMetrics->topProducts(25) as $product)
                            <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800">
                                <td class="px-6 py-4">
                                    <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $product['title'] }}</div>
                                    <div class="text-xs text-zinc-500 dark:text-zinc-400">SKU {{ $product['sku'] }}</div>
                                </td>
                                <td class="px-6 py-4 text-right font-medium text-zinc-900 dark:text-zinc-100">£{{ number_format($product['revenue'], 2) }}</td>
                                <td class="px-6 py-4 text-right">{{ number_format($product['quantity']) }}</td>
                                <td class="px-6 py-4 text-right">{{ number_format($product['orders']) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-6 py-12 text-center text-sm text-zinc-500 dark:text-zinc-400">
                                    No product insights available for the selected filters.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
