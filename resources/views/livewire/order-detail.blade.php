<div class="min-h-screen">
    <div class="space-y-3 p-3 lg:p-4">
        {{-- Header with Order Info --}}
        <div class="bg-white dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700 p-3">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3">
                <div class="flex items-center gap-4">
                    <div class="flex items-center gap-3">
                        <flux:button
                            variant="ghost"
                            size="sm"
                            href="{{ route('orders.analytics') }}"
                            icon="arrow-left"
                        >
                            Back to Orders
                        </flux:button>
                        <div class="w-px h-6 bg-zinc-300 dark:bg-zinc-600"></div>
                    </div>
                    <div>
                        <flux:heading size="lg" class="text-zinc-900 dark:text-zinc-100">
                            Order #{{ $this->orderInfo['number'] }}
                        </flux:heading>
                        <div class="flex items-center gap-4 text-sm text-zinc-600 dark:text-zinc-400 mt-1">
                            <flux:badge color="zinc">{{ $this->orderInfo['channel'] }}</flux:badge>
                            @if($this->orderInfo['subsource'])
                                <span class="text-zinc-400">·</span>
                                <span>{{ $this->orderInfo['subsource'] }}</span>
                            @endif
                            <span class="text-zinc-400">·</span>
                            <span>{{ $this->orderInfo['received_at'] }}</span>
                        </div>

                        {{-- Order Badges --}}
                        @if($this->orderBadges->isNotEmpty())
                            <div class="flex flex-wrap gap-2 mt-3">
                                @foreach($this->orderBadges as $badge)
                                    <flux:badge
                                        color="{{ $badge['color'] }}"
                                        size="sm"
                                        title="{{ $badge['description'] }}"
                                    >
                                        <flux:icon name="{{ $badge['icon'] }}" class="size-4 mr-1" />
                                        {{ $badge['label'] }}
                                    </flux:badge>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Controls --}}
                <div class="flex flex-wrap items-center gap-3">
                    {{-- Status Badges --}}
                    @if($this->orderInfo['is_cancelled'])
                        <flux:badge color="red" size="lg">Cancelled</flux:badge>
                    @elseif($this->orderInfo['is_paid'])
                        <flux:badge color="green" size="lg">Paid</flux:badge>
                    @else
                        <flux:badge color="amber" size="lg">{{ $this->orderInfo['status'] }}</flux:badge>
                    @endif

                    <flux:button variant="ghost" size="sm" wire:click="$refresh" icon="arrow-path">
                        Refresh
                    </flux:button>
                </div>
            </div>
        </div>

        {{-- Key Metrics Grid --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-2">
            {{-- Order Total --}}
            <div class="bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-lg p-3">
                <div class="flex items-center justify-between mb-1">
                    <span class="text-xs font-medium text-zinc-500 uppercase tracking-wide">Total</span>
                    <flux:icon name="currency-pound" class="size-4 text-zinc-400" />
                </div>
                <p class="text-2xl font-semibold text-zinc-900 dark:text-zinc-100">
                    £{{ number_format($this->orderInfo['total_charge'], 2) }}
                </p>
                <p class="text-xs text-zinc-500 mt-1">
                    {{ $this->orderInfo['num_items'] }} items
                </p>
            </div>

            {{-- Profit --}}
            <div class="bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-lg p-3">
                <div class="flex items-center justify-between mb-1">
                    <span class="text-xs font-medium text-zinc-500 uppercase tracking-wide">Profit</span>
                    <flux:icon name="star" class="size-4 text-zinc-400" />
                </div>
                <p class="text-2xl font-semibold {{ $this->profitAnalysis['profit'] >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400' }}">
                    £{{ number_format($this->profitAnalysis['profit'], 2) }}
                </p>
                <p class="text-xs text-zinc-500 mt-1">
                    {{ number_format($this->profitAnalysis['margin_percentage'], 1) }}% margin
                </p>
            </div>

            {{-- Cost --}}
            <div class="bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-lg p-3">
                <div class="flex items-center justify-between mb-1">
                    <span class="text-xs font-medium text-zinc-500 uppercase tracking-wide">Cost</span>
                    <flux:icon name="calculator" class="size-4 text-zinc-400" />
                </div>
                <p class="text-2xl font-semibold text-zinc-900 dark:text-zinc-100">
                    £{{ number_format($this->profitAnalysis['total_cost'], 2) }}
                </p>
                <p class="text-xs text-zinc-500 mt-1">
                    Product costs
                </p>
            </div>

            {{-- Processing Time --}}
            <div class="bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-lg p-3">
                <div class="flex items-center justify-between mb-1">
                    <span class="text-xs font-medium text-zinc-500 uppercase tracking-wide">Processing</span>
                    <flux:icon name="clock" class="size-4 text-zinc-400" />
                </div>
                @if($this->processingTime)
                    <p class="text-2xl font-semibold text-zinc-900 dark:text-zinc-100">{{ $this->processingTime['formatted'] }}</p>
                    <p class="text-xs text-zinc-500 mt-1">
                        @if($this->processingTime['is_same_day'])
                            Same day
                        @elseif($this->processingTime['is_fast'])
                            Fast processing
                        @else
                            Processing time
                        @endif
                    </p>
                @else
                    <p class="text-2xl font-semibold text-zinc-900 dark:text-zinc-100">Pending</p>
                    <p class="text-xs text-zinc-500 mt-1">Not yet processed</p>
                @endif
            </div>
        </div>

        {{-- Order Timeline & Items --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-3">
            {{-- Items Breakdown --}}
            <div class="lg:col-span-2">
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>Product</flux:table.column>
                        <flux:table.column align="center">Qty</flux:table.column>
                        <flux:table.column align="end">Unit Price</flux:table.column>
                        <flux:table.column align="end">Cost</flux:table.column>
                        <flux:table.column align="end">Total</flux:table.column>
                        <flux:table.column align="end">Profit</flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @foreach($this->orderItems as $item)
                            <flux:table.row>
                                <flux:table.cell>
                                    <div class="font-medium">{{ Str::limit($item['title'], 40) }}</div>
                                    <div class="text-xs text-zinc-500">{{ $item['sku'] }}</div>
                                </flux:table.cell>
                                <flux:table.cell align="center">{{ $item['quantity'] }}</flux:table.cell>
                                <flux:table.cell align="end">£{{ number_format($item['price_per_unit'], 2) }}</flux:table.cell>
                                <flux:table.cell align="end">£{{ number_format($item['unit_cost'], 2) }}</flux:table.cell>
                                <flux:table.cell align="end" variant="strong">£{{ number_format($item['line_total'], 2) }}</flux:table.cell>
                                <flux:table.cell align="end">
                                    <span class="{{ $item['profit'] >= 0 ? 'text-emerald-600' : 'text-red-600' }} font-medium">
                                        £{{ number_format($item['profit'], 2) }}
                                    </span>
                                    <div class="text-xs text-zinc-500">{{ number_format($item['profit_margin'], 1) }}%</div>
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach

                        {{-- Totals Row --}}
                        <flux:table.row>
                            <flux:table.cell variant="strong">Total</flux:table.cell>
                            <flux:table.cell align="center">{{ $this->orderItems->sum('quantity') }}</flux:table.cell>
                            <flux:table.cell></flux:table.cell>
                            <flux:table.cell align="end">£{{ number_format($this->profitAnalysis['total_cost'], 2) }}</flux:table.cell>
                            <flux:table.cell align="end" variant="strong">£{{ number_format($this->profitAnalysis['total_revenue'], 2) }}</flux:table.cell>
                            <flux:table.cell align="end">
                                <span class="text-emerald-600 font-bold">£{{ number_format($this->profitAnalysis['profit'], 2) }}</span>
                            </flux:table.cell>
                        </flux:table.row>
                    </flux:table.rows>
                </flux:table>
            </div>

            {{-- Timeline & Details Sidebar --}}
            <div class="space-y-3">
                {{-- Order Timeline --}}
                <div class="bg-white dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700 p-3">
                    <flux:heading size="lg" class="mb-3">Order Timeline</flux:heading>
                    @if($this->timeline->isNotEmpty())
                        <div class="space-y-4">
                            @foreach($this->timeline as $event)
                                <div class="flex items-start gap-3">
                                    <div class="flex-shrink-0 w-8 h-8 rounded-full bg-{{ $event['color'] }}-100 dark:bg-{{ $event['color'] }}-900/30 flex items-center justify-center">
                                        <flux:icon name="{{ $event['icon'] }}" class="size-4 text-{{ $event['color'] }}-600" />
                                    </div>
                                    <div>
                                        <p class="font-medium text-zinc-900 dark:text-zinc-100">{{ $event['event'] }}</p>
                                        <p class="text-sm text-zinc-500">{{ $event['formatted_date'] }}</p>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-zinc-500 text-sm">No timeline events available</p>
                    @endif
                </div>

                {{-- Order Details --}}
                <div class="bg-white dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700 p-3">
                    <flux:heading size="lg" class="mb-3">Order Details</flux:heading>
                    <dl class="space-y-3 text-sm">
                        <div class="flex justify-between">
                            <dt class="text-zinc-500">Payment Method</dt>
                            <dd class="font-medium text-zinc-900 dark:text-zinc-100">{{ $this->orderInfo['payment_method'] ?? 'N/A' }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-zinc-500">Postage</dt>
                            <dd class="font-medium text-zinc-900 dark:text-zinc-100">£{{ number_format($this->orderInfo['postage_cost'], 2) }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-zinc-500">Tax</dt>
                            <dd class="font-medium text-zinc-900 dark:text-zinc-100">£{{ number_format($this->orderInfo['tax'], 2) }}</dd>
                        </div>
                        @if($this->orderInfo['channel_reference'])
                            <div class="flex justify-between">
                                <dt class="text-zinc-500">Channel Ref</dt>
                                <dd class="font-medium text-zinc-900 dark:text-zinc-100 truncate max-w-[150px]" title="{{ $this->orderInfo['channel_reference'] }}">
                                    {{ $this->orderInfo['channel_reference'] }}
                                </dd>
                            </div>
                        @endif
                    </dl>
                </div>

                {{-- Related Orders --}}
                @if($this->relatedOrders->isNotEmpty())
                    <div class="bg-white dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700 p-3">
                        <flux:heading size="lg" class="mb-3">Related Orders</flux:heading>
                        <div class="space-y-3">
                            @foreach($this->relatedOrders as $related)
                                <a href="{{ route('orders.detail', $related['number']) }}"
                                   class="block p-3 rounded-lg bg-zinc-50 dark:bg-zinc-900/50 hover:bg-zinc-100 dark:hover:bg-zinc-700/50 transition-colors">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <p class="font-medium text-zinc-900 dark:text-zinc-100">#{{ $related['number'] }}</p>
                                            <p class="text-xs text-zinc-500">{{ $related['date'] }}</p>
                                        </div>
                                        <div class="text-right">
                                            <p class="font-medium text-zinc-900 dark:text-zinc-100">£{{ number_format($related['total'], 2) }}</p>
                                            <p class="text-xs text-zinc-500">{{ $related['items'] }} items</p>
                                        </div>
                                    </div>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
