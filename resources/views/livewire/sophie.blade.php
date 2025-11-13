<div class="min-h-screen max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    {{-- Header --}}
    <div class="mb-8">
        <div class="mb-2">
            <div>
                <flux:heading size="xl" class="text-zinc-900 dark:text-zinc-100">Monthly Sales by Source and Subsource
                </flux:heading>
                <flux:subheading class="text-zinc-600 dark:text-zinc-400">Complete breakdown by variation groups. All
                    variations items sales grouped by parent SKU
                </flux:subheading>
            </div>
        </div>
    </div>

    {{-- Filters Section --}}
    <div class="py-4 mb-2">
        <div class="flex gap-10">
            {{-- Date Range Filter --}}
            <div class="flex-1">
                <flux:label>Date Range</flux:label>
                <div class="flex items-center gap-2">
                    <flux:input
                            type="date"
                            wire:model.live.debounce.500ms="dateFrom"
                            class="flex-1"
                    />
                    <span class="text-zinc-400">→</span>
                    <flux:input
                            type="date"
                            wire:model.live.debounce.500ms="dateTo"
                            class="flex-1"
                    />
                </div>
            </div>

            <div class="flex-1">

            </div>

            {{-- SKU Filter --}}
            <div class="flex-1">
                <flux:label>SKUs</flux:label>
                <x-pill-selector
                        :options="$this->availableSkus->map(fn ($sku) => ['value' => $sku, 'label' => $sku])->toArray()"
                        :selected="$selectedSkus"
                        :placeholder="'All SKUs (' . $this->availableSkus->count() . ')'"
                        toggle-method="toggleSku"
                        clear-method="clearFilters"
                />
            </div>

            {{-- Subsource Filter --}}
            <div class="flex-1">
                <flux:label>Subsources</flux:label>
                <x-pill-selector
                        :options="$this->availableSubsources->map(fn ($sub) => ['value' => $sub, 'label' => $sub])->toArray()"
                        :selected="$selectedSubsources"
                        :placeholder="'All Subsources (' . $this->availableSubsources->count() . ')'"
                        toggle-method="toggleSubsource"
                        clear-method="clearFilters"
                />
            </div>
        </div>

        {{-- Active Filters Summary --}}
        @if(!empty($selectedSkus) || !empty($selectedSubsources))
            <div class="mt-4 pt-4 border-t border-zinc-200 dark:border-zinc-700 flex items-center justify-between">
                <div class="text-sm text-zinc-600 dark:text-zinc-400">
                    <span class="font-medium">Filters active:</span>
                    @if(!empty($selectedSkus))
                        <span>{{ count($selectedSkus) }} SKU(s)</span>
                    @endif
                    @if(!empty($selectedSkus) && !empty($selectedSubsources))
                        <span class="text-zinc-400 mx-1">•</span>
                    @endif
                    @if(!empty($selectedSubsources))
                        <span>{{ count($selectedSubsources) }} Subsource(s)</span>
                    @endif
                </div>
                <flux:button
                        variant="ghost"
                        size="sm"
                        wire:click="clearFilters"
                        icon="x-mark"
                >
                    Clear filters
                </flux:button>
            </div>
        @endif
    </div>

    {{-- Stats Cards --}}
    @if($this->variationGroups->isNotEmpty())
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-white dark:bg-zinc-800 rounded-xl shadow-sm border border-zinc-200 dark:border-zinc-700 p-4">
                <div class="text-sm text-zinc-600 dark:text-zinc-400 mb-1">Variation Groups</div>
                <div class="text-2xl font-bold text-zinc-900 dark:text-zinc-100">{{ number_format($this->variationGroups->count()) }}</div>
            </div>
            <div class="bg-white dark:bg-zinc-800 rounded-xl shadow-sm border border-zinc-200 dark:border-zinc-700 p-4">
                <div class="text-sm text-zinc-600 dark:text-zinc-400 mb-1">Total Orders</div>
                <div class="text-2xl font-bold text-blue-600 dark:text-blue-400">{{ number_format($this->variationGroups->sum('order_count')) }}</div>
            </div>
            <div class="bg-white dark:bg-zinc-800 rounded-xl shadow-sm border border-zinc-200 dark:border-zinc-700 p-4">
                <div class="text-sm text-zinc-600 dark:text-zinc-400 mb-1">Total Units</div>
                <div class="text-2xl font-bold text-purple-600 dark:text-purple-400">{{ number_format($this->variationGroups->sum('total_units')) }}</div>
            </div>
            <div class="bg-white dark:bg-zinc-800 rounded-xl shadow-sm border border-zinc-200 dark:border-zinc-700 p-4">
                <div class="text-sm text-zinc-600 dark:text-zinc-400 mb-1">Total Revenue</div>
                <div class="text-2xl font-bold text-green-600 dark:text-green-400">
                    £{{ number_format($this->variationGroups->sum('total_revenue'), 2) }}</div>
            </div>
        </div>

        {{-- Action Bar --}}
        <div class="bg-white dark:bg-zinc-800 rounded-xl shadow-sm border border-zinc-200 dark:border-zinc-700 p-4 mb-6 flex items-center justify-between">
            <div class="text-sm text-zinc-600 dark:text-zinc-400">
                <span class="font-medium">Showing {{ number_format($this->variationGroups->count()) }} variation groups</span>
                <span class="text-zinc-400 mx-1">•</span>
                <span>{{ number_format($this->variationGroups->sum('order_count')) }} orders</span>
            </div>
            <flux:button
                    wire:click="downloadCsv"
                    class="bg-gradient-to-r from-pink-500 to-purple-600 hover:from-pink-600 hover:to-purple-700 text-white"
                    icon="arrow-down-tray"
            >
                <span wire:loading.remove wire:target="downloadCsv">Download CSV</span>
                <span wire:loading wire:target="downloadCsv">Preparing...</span>
            </flux:button>
        </div>
    @endif

    {{-- Main Table --}}
    <div class="bg-white dark:bg-zinc-800 rounded-xl shadow-lg border border-zinc-200 dark:border-zinc-700 overflow-hidden transition-opacity duration-200"
         wire:loading.class="opacity-50"
         wire:target="dateFrom,dateTo,toggleSku,toggleSubsource,clearFilters">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gradient-to-r from-zinc-100 to-zinc-50 dark:from-zinc-900 dark:to-zinc-800 border-b-2 border-zinc-300 dark:border-zinc-600">
                <tr>
                    <th class="px-6 py-4 text-left w-12"></th>
                    <th class="px-6 py-4 text-left">
                        <button wire:click="sortBy('sku')"
                                class="flex items-center gap-2 font-semibold text-zinc-900 dark:text-zinc-100 hover:text-pink-600 dark:hover:text-pink-400 transition-colors">
                            SKU
                            @if($sortBy === 'sku')
                                <flux:icon :name="$sortDirection === 'asc' ? 'arrow-up' : 'arrow-down'" class="size-4"/>
                            @endif
                        </button>
                    </th>
                    <th class="px-6 py-4 text-left">
                        <span class="font-semibold text-zinc-900 dark:text-zinc-100">Name</span>
                    </th>
                    <th class="px-6 py-4 text-right">
                        <button wire:click="sortBy('orders')"
                                class="flex items-center gap-2 font-semibold text-zinc-900 dark:text-zinc-100 hover:text-pink-600 dark:hover:text-pink-400 transition-colors ml-auto">
                            Orders
                            @if($sortBy === 'orders')
                                <flux:icon :name="$sortDirection === 'asc' ? 'arrow-up' : 'arrow-down'" class="size-4"/>
                            @endif
                        </button>
                    </th>
                    <th class="px-6 py-4 text-right">
                        <button wire:click="sortBy('units')"
                                class="flex items-center gap-2 font-semibold text-zinc-900 dark:text-zinc-100 hover:text-pink-600 dark:hover:text-pink-400 transition-colors ml-auto">
                            Units Sold
                            @if($sortBy === 'units')
                                <flux:icon :name="$sortDirection === 'asc' ? 'arrow-up' : 'arrow-down'" class="size-4"/>
                            @endif
                        </button>
                    </th>
                    <th class="px-6 py-4 text-right">
                        <button wire:click="sortBy('revenue')"
                                class="flex items-center gap-2 font-semibold text-zinc-900 dark:text-zinc-100 hover:text-pink-600 dark:hover:text-pink-400 transition-colors ml-auto">
                            Revenue
                            @if($sortBy === 'revenue')
                                <flux:icon :name="$sortDirection === 'asc' ? 'arrow-up' : 'arrow-down'" class="size-4"/>
                            @endif
                        </button>
                    </th>
                </tr>
                </thead>
                <tbody>
                @forelse($this->variationGroups as $index => $group)
                    {{-- Main Row --}}
                    <tr class="border-b border-zinc-200 dark:border-zinc-700 hover:bg-zinc-50 dark:hover:bg-zinc-700/50 transition-colors {{ in_array($group['sku'], $expandedRows) ? 'bg-pink-50 dark:bg-pink-900/10' : '' }}">
                        <td class="px-6 py-4">
                            <button wire:click="toggleRow('{{ $group['sku'] }}')"
                                    class="w-8 h-8 rounded-lg bg-gradient-to-br from-pink-500 to-purple-600 flex items-center justify-center text-white text-sm font-bold shadow hover:shadow-lg transition-all hover:scale-110">
                                @if(in_array($group['sku'], $expandedRows))
                                    <flux:icon name="chevron-down" class="size-5"/>
                                @else
                                    {{ $index + 1 }}
                                @endif
                            </button>
                        </td>
                        <td class="px-6 py-4">
                            <span class="font-mono font-semibold text-zinc-900 dark:text-zinc-100 bg-zinc-100 dark:bg-zinc-700 px-2 py-1 rounded">{{ $group['sku'] }}</span>
                        </td>
                        <td class="px-6 py-4">
                            <span class="text-zinc-700 dark:text-zinc-300 font-medium">{{ $group['name'] }}</span>
                        </td>
                        <td class="px-6 py-4 text-right">
                            <span class="text-zinc-900 dark:text-zinc-100 font-semibold">{{ number_format($group['order_count']) }}</span>
                        </td>
                        <td class="px-6 py-4 text-right">
                            <span class="text-zinc-900 dark:text-zinc-100 font-semibold">{{ number_format($group['total_units']) }}</span>
                        </td>
                        <td class="px-6 py-4 text-right">
                            <span class="text-lg font-bold text-green-600 dark:text-green-400">£{{ number_format($group['total_revenue'], 2) }}</span>
                        </td>
                    </tr>

                    {{-- Expanded Subsources --}}
                    @if(in_array($group['sku'], $expandedRows))
                        @php
                            $subsources = $this->getSubsources($group['sku']);
                        @endphp
                        @foreach($subsources as $subsource)
                            <tr class="bg-gradient-to-r from-pink-50/50 to-purple-50/50 dark:from-pink-900/5 dark:to-purple-900/5 border-b border-pink-200 dark:border-pink-800/30">
                                <td class="px-6 py-3"></td>
                                <td class="px-6 py-3 pl-12" colspan="2">
                                    <div class="flex items-center gap-2">
                                        <flux:icon name="arrow-turn-down-right" class="size-4 text-pink-500"/>
                                        <span class="text-sm font-medium text-zinc-700 dark:text-zinc-300">{{ $subsource->subsource }}</span>
                                    </div>
                                </td>
                                <td class="px-6 py-3 text-right">
                                    <span class="text-sm text-zinc-700 dark:text-zinc-300">{{ number_format($subsource->order_count) }}</span>
                                </td>
                                <td class="px-6 py-3 text-right">
                                    <span class="text-sm text-zinc-700 dark:text-zinc-300">{{ number_format($subsource->total_units) }}</span>
                                </td>
                                <td class="px-6 py-3 text-right">
                                    <span class="text-sm font-semibold text-green-600 dark:text-green-400">£{{ number_format($subsource->total_revenue, 2) }}</span>
                                </td>
                            </tr>
                        @endforeach
                    @endif
                @empty
                    <tr>
                        <td colspan="6" class="px-6 py-12">
                            <div class="text-center text-zinc-500 dark:text-zinc-400">
                                <flux:icon name="chart-bar"
                                           class="size-12 mx-auto mb-2 text-zinc-300 dark:text-zinc-600"/>
                                <p>No variation groups found</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
