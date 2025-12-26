<div class="space-y-3">
    {{-- Condensed Header with Controls --}}
    <div class="bg-white dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700 p-3">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
            <div class="flex items-center gap-6">
                <div>
                    <flux:heading size="lg" class="text-zinc-900 dark:text-zinc-100">Product Analytics</flux:heading>
                    <div class="flex items-center gap-2 text-sm text-zinc-600 dark:text-zinc-400 mt-1">
                        <span>{{ $this->periodSummary->get('period_label') }}</span>
                        @if($this->lastWarmedAt)
                            <span class="text-zinc-400 dark:text-zinc-500">·</span>
                            <span class="text-zinc-500 dark:text-zinc-400" title="Data last updated {{ $this->lastWarmedAt }}">
                                Updated {{ \Carbon\Carbon::parse($this->lastWarmedAt)->diffForHumans() }}
                            </span>
                        @endif
                    </div>
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

                <flux:button variant="primary" size="sm" wire:click="syncProducts" icon="cloud-arrow-down">
                    Sync
                </flux:button>

                <flux:button variant="ghost" size="sm" wire:click="refresh" icon="arrow-path">
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
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
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

                {{-- Revenue Tier Filter --}}
                <div>
                    <flux:field>
                        <flux:label>Revenue Tier</flux:label>
                        <flux:select wire:model.live="filters.revenue-tier" size="sm">
                            <flux:select.option value="">All Revenue</flux:select.option>
                            <flux:select.option value="low">Low (0-100)</flux:select.option>
                            <flux:select.option value="medium">Medium (100-500)</flux:select.option>
                            <flux:select.option value="high">High (500-2000)</flux:select.option>
                            <flux:select.option value="top">Top (2000+)</flux:select.option>
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

    {{-- Search Options Panel --}}
    @if($showSearchOptions && $search)
        <div class="bg-white dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700 p-3">
            <div class="flex items-center justify-between mb-3">
                <flux:heading size="lg" class="text-zinc-900 dark:text-zinc-100">Search Options</flux:heading>
                <flux:button variant="ghost" size="sm" wire:click="toggleSearchOptions" icon="x-mark">
                    Close
                </flux:button>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
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
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
