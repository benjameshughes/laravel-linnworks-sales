<div class="min-h-screen">
    <div class="space-y-3 p-3 lg:p-4">
        {{-- Header + Controls --}}
        <div class="bg-white dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700 p-3">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <flux:heading size="lg">Channel Comparison</flux:heading>
                    <flux:subheading class="mt-0.5">
                        {{ $this->currentLabel }} vs {{ $this->baselineLabel }}
                        <span class="text-zinc-400 dark:text-zinc-500">&middot; {{ $this->comparisonMode->abbreviation() }}</span>
                    </flux:subheading>
                </div>

                <div class="flex items-center gap-2">
                    <flux:radio.group wire:model.live="mode" variant="segmented" size="sm">
                        @foreach (\App\Enums\ComparisonMode::all() as $comparisonMode)
                            <flux:radio value="{{ $comparisonMode->value }}">{{ $comparisonMode->abbreviation() }}</flux:radio>
                        @endforeach
                    </flux:radio.group>

                    <div class="flex items-center gap-2">
                        <flux:select wire:model.live="month" size="sm" class="min-w-40">
                            @foreach ($this->availableMonths as $value => $label)
                                <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
                            @endforeach
                        </flux:select>

                        <flux:select wire:model.live="source" size="sm" class="min-w-36">
                            <flux:select.option value="all">All sources</flux:select.option>
                            @foreach ($this->availableSources as $availableSource)
                                <flux:select.option value="{{ $availableSource }}">{{ $availableSource }}</flux:select.option>
                            @endforeach
                        </flux:select>

                        <flux:select wire:model.live="status" size="sm" class="min-w-32">
                            <flux:select.option value="all">All orders</flux:select.option>
                            <flux:select.option value="open_paid">Paid</flux:select.option>
                            <flux:select.option value="open">Open</flux:select.option>
                            <flux:select.option value="processed">Processed</flux:select.option>
                        </flux:select>
                    </div>

                    <flux:button
                        size="sm"
                        icon="{{ $includeSubsources ? 'squares-2x2' : 'view-columns' }}"
                        :variant="$includeSubsources ? 'primary' : 'outline'"
                        wire:click="toggleSubsources"
                    >
                        Subsources
                    </flux:button>
                </div>
            </div>
        </div>

        {{-- Summary --}}
        <div class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-4">
            <div class="bg-white dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700 p-3">
                <div class="flex items-start justify-between">
                    <span class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Revenue</span>
                    <x-growth-badge :growth="$this->totals['revenue_indicator']" />
                </div>
                <div class="mt-2 text-2xl font-semibold text-zinc-900 dark:text-white">
                    £{{ number_format($this->totals['current_revenue'], 2) }}
                </div>
                <div class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                    was £{{ number_format($this->totals['baseline_revenue'], 2) }} in {{ $this->baselineLabel }}
                </div>
            </div>

            <div class="bg-white dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700 p-3">
                <div class="flex items-start justify-between">
                    <span class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Orders</span>
                    <x-growth-badge :growth="$this->totals['orders_indicator']" />
                </div>
                <div class="mt-2 text-2xl font-semibold text-zinc-900 dark:text-white">
                    {{ number_format($this->totals['current_orders']) }}
                </div>
                <div class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                    was {{ number_format($this->totals['baseline_orders']) }} in {{ $this->baselineLabel }}
                </div>
            </div>

            <div class="bg-white dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700 p-3">
                <div class="flex items-start justify-between">
                    <span class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Avg Order Value</span>
                    <x-growth-badge :growth="$this->totals['aov_indicator']" />
                </div>
                <div class="mt-2 text-2xl font-semibold text-zinc-900 dark:text-white">
                    £{{ number_format($this->totals['current_aov'], 2) }}
                </div>
                <div class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                    was £{{ number_format($this->totals['baseline_aov'], 2) }} in {{ $this->baselineLabel }}
                </div>
            </div>

            <div class="bg-white dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700 p-3">
                <span class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Channel Movement</span>
                <div class="mt-2 flex items-baseline gap-4">
                    <div>
                        <span class="text-2xl font-semibold text-green-600 dark:text-green-400">{{ $this->totals['winners'] }}</span>
                        <span class="ml-1 text-xs text-zinc-500 dark:text-zinc-400">up</span>
                    </div>
                    <div>
                        <span class="text-2xl font-semibold text-red-600 dark:text-red-400">{{ $this->totals['losers'] }}</span>
                        <span class="ml-1 text-xs text-zinc-500 dark:text-zinc-400">down</span>
                    </div>
                </div>
                <div class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                    {{ $this->channels->count() }} {{ Str::plural('channel', $this->channels->count()) }} tracked
                </div>
            </div>
        </div>

        {{-- Daily overlay --}}
        <div class="bg-white dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700 p-3">
            <div class="mb-3">
                <span class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Daily Revenue Overlay</span>
                <p class="mt-0.5 text-xs text-zinc-500">Both periods aligned by day of month</p>
            </div>

            <x-chart
                type="line"
                class="h-72"
                :data="$this->trendChart"
                :options="[
                    'interaction' => ['mode' => 'index', 'intersect' => false],
                    'plugins' => [
                        'legend' => ['display' => true, 'position' => 'top', 'labels' => ['boxWidth' => 12, 'padding' => 16]],
                    ],
                    'scales' => [
                        'y' => ['beginAtZero' => true, 'grace' => '10%'],
                    ],
                ]"
            />
        </div>

        {{-- Grouped bar --}}
        <div class="bg-white dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700 p-3">
            <div class="mb-3">
                <span class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Revenue by Channel</span>
                <p class="mt-0.5 text-xs text-zinc-500">Top 10 channels, both periods side by side</p>
            </div>

            <x-chart
                type="bar"
                class="h-80"
                :data="$this->revenueChart"
                :options="[
                    'plugins' => [
                        'legend' => ['display' => true, 'position' => 'top', 'labels' => ['boxWidth' => 12, 'padding' => 16]],
                    ],
                    'scales' => [
                        'y' => ['beginAtZero' => true],
                    ],
                ]"
            />
        </div>

        {{-- Breakdown --}}
        <div class="bg-white dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700 p-3">
            <div class="mb-3 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <span class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Channel Breakdown</span>
                    <p class="mt-0.5 text-xs text-zinc-500">{{ $this->currentLabel }} measured against {{ $this->baselineLabel }}</p>
                </div>

                <flux:select wire:model.live="sortBy" size="sm" class="sm:max-w-44">
                    <flux:select.option value="revenue">Sort: Revenue</flux:select.option>
                    <flux:select.option value="orders">Sort: Orders</flux:select.option>
                    <flux:select.option value="items">Sort: Items</flux:select.option>
                    <flux:select.option value="growth">Sort: Growth %</flux:select.option>
                    <flux:select.option value="delta">Sort: Revenue change</flux:select.option>
                </flux:select>
            </div>

            <div class="overflow-x-auto">
                <x-table>
                    <x-table.header>
                        <x-table.row>
                            <x-table.header-cell>Channel</x-table.header-cell>
                            <x-table.header-cell class="text-right">{{ $this->currentLabel }}</x-table.header-cell>
                            <x-table.header-cell class="text-right">{{ $this->baselineLabel }}</x-table.header-cell>
                            <x-table.header-cell class="text-right">Change</x-table.header-cell>
                            <x-table.header-cell class="text-right">Orders</x-table.header-cell>
                            <x-table.header-cell class="text-right">Items</x-table.header-cell>
                            <x-table.header-cell class="text-right">AOV</x-table.header-cell>
                            <x-table.header-cell class="text-right">Share</x-table.header-cell>
                        </x-table.row>
                    </x-table.header>

                    <x-table.body>
                        @forelse ($this->channels as $channel)
                            <x-table.row>
                                <x-table.cell>
                                    <div class="font-medium text-zinc-900 dark:text-white">{{ $channel->name }}</div>
                                    @if ($channel->isNew())
                                        <flux:badge color="blue" size="sm" class="mt-1">New this period</flux:badge>
                                    @elseif ($channel->isLost())
                                        <flux:badge color="red" size="sm" class="mt-1">No orders</flux:badge>
                                    @endif
                                </x-table.cell>

                                <x-table.cell class="text-right font-semibold">
                                    £{{ number_format($channel->currentRevenue, 2) }}
                                </x-table.cell>

                                <x-table.cell class="text-right text-zinc-500 dark:text-zinc-400">
                                    £{{ number_format($channel->baselineRevenue, 2) }}
                                </x-table.cell>

                                <x-table.cell class="text-right">
                                    <div class="flex flex-col items-end gap-0.5">
                                        <span class="font-medium {{ $channel->isImproving() ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                            {{ $channel->revenueDelta() >= 0 ? '+' : '-' }}£{{ number_format(abs($channel->revenueDelta()), 2) }}
                                        </span>
                                        <x-growth-badge :growth="$channel->revenueIndicator()" />
                                    </div>
                                </x-table.cell>

                                <x-table.cell class="text-right">
                                    <div class="flex flex-col items-end gap-0.5">
                                        <span>{{ number_format($channel->currentOrders) }}</span>
                                        <x-growth-badge :growth="$channel->ordersIndicator()" />
                                    </div>
                                </x-table.cell>

                                <x-table.cell class="text-right">
                                    <div class="flex flex-col items-end gap-0.5">
                                        <span>{{ number_format($channel->currentItems) }}</span>
                                        <span class="text-xs text-zinc-500 dark:text-zinc-400">
                                            was {{ number_format($channel->baselineItems) }}
                                        </span>
                                    </div>
                                </x-table.cell>

                                <x-table.cell class="text-right">
                                    <div class="flex flex-col items-end gap-0.5">
                                        <span>£{{ number_format($channel->currentAov(), 2) }}</span>
                                        <x-growth-badge :growth="$channel->aovIndicator()" />
                                    </div>
                                </x-table.cell>

                                <x-table.cell class="text-right">
                                    {{ number_format($channel->revenueShare, 1) }}%
                                </x-table.cell>
                            </x-table.row>
                        @empty
                            <x-table.row>
                                <x-table.cell colspan="8" class="py-8 text-center text-zinc-500">
                                    No orders found for {{ $this->currentLabel }} or {{ $this->baselineLabel }}.
                                </x-table.cell>
                            </x-table.row>
                        @endforelse
                    </x-table.body>
                </x-table>
            </div>
        </div>
    </div>
</div>
