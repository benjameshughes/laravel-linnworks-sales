<div class="space-y-3">
    {{-- Header matching Orders style - no card wrapper --}}
    <div class="flex items-center justify-between gap-4 pb-4 border-b border-zinc-200 dark:border-zinc-700">
        <div class="flex items-center gap-2">
            <flux:heading size="xl" class="text-zinc-900 dark:text-zinc-100">Products</flux:heading>

            {{-- Period selector inline with title --}}
            <flux:select wire:model.live="period" size="sm" class="!w-auto">
                @foreach(\App\Enums\Period::all() as $periodOption)
                    @if($periodOption->value !== 'custom')
                        <flux:select.option value="{{ $periodOption->value }}">
                            {{ $periodOption->label() }}
                        </flux:select.option>
                    @endif
                @endforeach
            </flux:select>

            @if($selectedCategory)
                <flux:button variant="outline" size="sm" wire:click="clearCategoryFilter" icon="x-mark">
                    {{ $selectedCategory }}
                </flux:button>
            @endif
        </div>

        {{-- Inline Filter Bar --}}
        <div class="flex items-center gap-2">
            {{-- Simple Search Input --}}
            <div class="relative">
                <flux:input
                    wire:model.live.debounce.300ms="search"
                    placeholder="Search..."
                    size="sm"
                    icon="magnifying-glass"
                    class="w-40"
                />

                {{-- Autocomplete Suggestions --}}
                @if(!empty($searchSuggestions))
                    <div class="absolute top-full left-0 right-0 mt-1 bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-lg shadow-lg z-50 max-h-60 overflow-y-auto min-w-64">
                        @foreach($searchSuggestions as $suggestion)
                            <div
                                class="px-3 py-2 hover:bg-zinc-50 dark:hover:bg-zinc-700 cursor-pointer border-b border-zinc-100 dark:border-zinc-600 last:border-b-0"
                                wire:click="selectSearchSuggestion('{{ $suggestion['value'] }}', '{{ $suggestion['type'] }}')"
                            >
                                <div class="flex items-center gap-2">
                                    <div class="flex-1">
                                        <div class="font-medium text-zinc-900 dark:text-zinc-100 text-sm">
                                            {!! $suggestion['highlight'] ?? $suggestion['label'] !!}
                                        </div>
                                        @if($suggestion['context'])
                                            <div class="text-xs text-zinc-500">{{ $suggestion['context'] }}</div>
                                        @endif
                                    </div>
                                    @if($suggestion['type'] === 'sku')
                                        <flux:icon.arrow-top-right-on-square class="size-4 text-blue-500" title="Go to product" />
                                    @endif
                                    <flux:badge color="{{ $suggestion['type'] === 'sku' ? 'blue' : 'zinc' }}" size="xs">{{ $suggestion['type'] }}</flux:badge>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Category Filter Dropdown --}}
            <flux:select wire:model.live="filters.category" size="sm" class="!w-auto">
                <flux:select.option value="">All Categories</flux:select.option>
                @foreach($this->availableCategories as $category)
                    <flux:select.option value="{{ $category }}">{{ $category }}</flux:select.option>
                @endforeach
            </flux:select>

            {{-- More Filters Button --}}
            <flux:button
                variant="{{ $showFilters ? 'primary' : 'ghost' }}"
                size="sm"
                wire:click="toggleFilters"
                icon="funnel"
            >
                @if($this->activeFiltersCount > 0)
                    <flux:badge color="white" size="xs">{{ $this->activeFiltersCount }}</flux:badge>
                @endif
            </flux:button>

            {{-- Refresh Button --}}
            <flux:button
                wire:click="refresh"
                variant="ghost"
                size="sm"
                icon="arrow-path"
                wire:loading.attr="disabled"
                wire:target="refresh"
            />

            {{-- Import/Export Button --}}
            <flux:button
                href="{{ route('products.import-export') }}"
                variant="ghost"
                size="sm"
                icon="arrow-up-tray"
                title="Import/Export Products"
            />
        </div>
    </div>

    {{-- Advanced Filters Panel --}}
    @if($showFilters)
        <div class="bg-white dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700 p-3">
            <div class="flex items-center justify-between mb-3">
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
            <div class="mb-4">
                <flux:heading size="sm" class="text-zinc-700 dark:text-zinc-300 mb-2">Quick Filters</flux:heading>
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
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-3">
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
                            <flux:select.option value="declining">Declining (-20%+)</flux:select.option>
                            <flux:select.option value="stable">Stable (-20% to +20%)</flux:select.option>
                            <flux:select.option value="growing">Growing (+20% to +100%)</flux:select.option>
                            <flux:select.option value="surging">Surging (+100%+)</flux:select.option>
                        </flux:select>
                    </flux:field>
                </div>

                {{-- Badge Filter --}}
                <div>
                    <flux:field>
                        <flux:label>Performance Badge</flux:label>
                        <flux:select wire:model.live="filters.badge-type" size="sm">
                            <flux:select.option value="">All Products</flux:select.option>
                            <flux:select.option value="hot-seller">Hot Seller</flux:select.option>
                            <flux:select.option value="growing">Growing</flux:select.option>
                            <flux:select.option value="declining">Declining</flux:select.option>
                            <flux:select.option value="top-margin">Top Margin</flux:select.option>
                            <flux:select.option value="new-product">New Product</flux:select.option>
                            <flux:select.option value="high-volume">High Volume</flux:select.option>
                            <flux:select.option value="consistent">Consistent</flux:select.option>
                        </flux:select>
                    </flux:field>
                </div>
            </div>

            {{-- Active Filters Summary --}}
            @if($this->activeFiltersCount > 0)
                <div class="mt-4 pt-4 border-t border-zinc-200 dark:border-zinc-700">
                    <flux:heading size="sm" class="text-zinc-700 dark:text-zinc-300 mb-2">Active Filters ({{ $this->activeFiltersCount }})</flux:heading>
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
</div>
