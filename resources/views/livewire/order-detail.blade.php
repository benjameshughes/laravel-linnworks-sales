<div class="min-h-screen bg-zinc-50 dark:bg-zinc-900">
    <div class="space-y-6 p-6">
        {{-- Header with Order Info --}}
        <div class="bg-white dark:bg-zinc-800 rounded-xl shadow-sm border border-zinc-200 dark:border-zinc-700 p-6">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                <div class="flex items-center gap-6">
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
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            {{-- Order Total --}}
            <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl shadow-sm p-6 text-white">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-blue-100 text-sm font-medium">Order Total</p>
                        <p class="text-3xl font-bold">£{{ number_format($this->orderInfo['total_charge'], 2) }}</p>
                        <p class="text-sm text-blue-100 mt-1">
                            {{ $this->orderInfo['num_items'] }} items
                        </p>
                    </div>
                    <flux:icon name="currency-pound" class="size-10 text-blue-200/50" />
                </div>
            </div>

            {{-- Profit --}}
            <div class="bg-gradient-to-br from-green-500 to-green-600 rounded-xl shadow-sm p-6 text-white">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-green-100 text-sm font-medium">Profit</p>
                        <p class="text-3xl font-bold">£{{ number_format($this->profitAnalysis['profit'], 2) }}</p>
                        <p class="text-sm text-green-100 mt-1">
                            {{ number_format($this->profitAnalysis['margin_percentage'], 1) }}% margin
                        </p>
                    </div>
                    <flux:icon name="star" class="size-10 text-green-200/50" />
                </div>
            </div>

            {{-- Cost --}}
            <div class="bg-gradient-to-br from-purple-500 to-purple-600 rounded-xl shadow-sm p-6 text-white">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-purple-100 text-sm font-medium">Cost</p>
                        <p class="text-3xl font-bold">£{{ number_format($this->profitAnalysis['total_cost'], 2) }}</p>
                        <p class="text-sm text-purple-100 mt-1">
                            Product costs
                        </p>
                    </div>
                    <flux:icon name="calculator" class="size-10 text-purple-200/50" />
                </div>
            </div>

            {{-- Processing Time --}}
            <div class="bg-gradient-to-br from-orange-500 to-orange-600 rounded-xl shadow-sm p-6 text-white">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-orange-100 text-sm font-medium">Processing</p>
                        @if($this->processingTime)
                            <p class="text-3xl font-bold">{{ $this->processingTime['formatted'] }}</p>
                            <p class="text-sm text-orange-100 mt-1">
                                @if($this->processingTime['is_same_day'])
                                    Same day
                                @elseif($this->processingTime['is_fast'])
                                    Fast processing
                                @else
                                    Processing time
                                @endif
                            </p>
                        @else
                            <p class="text-3xl font-bold">Pending</p>
                            <p class="text-sm text-orange-100 mt-1">Not yet processed</p>
                        @endif
                    </div>
                    <flux:icon name="clock" class="size-10 text-orange-200/50" />
                </div>
            </div>
        </div>

        {{-- Order Timeline & Items --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            {{-- Items Breakdown --}}
            <div class="lg:col-span-2 bg-white dark:bg-zinc-800 rounded-xl shadow-sm border border-zinc-200 dark:border-zinc-700 p-6">
                <flux:heading size="lg" class="mb-4">Order Items</flux:heading>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-zinc-50 dark:bg-zinc-900/50">
                            <tr>
                                <th class="px-4 py-3 text-left font-medium text-zinc-600 dark:text-zinc-400">Product</th>
                                <th class="px-4 py-3 text-center font-medium text-zinc-600 dark:text-zinc-400">Qty</th>
                                <th class="px-4 py-3 text-right font-medium text-zinc-600 dark:text-zinc-400">Unit Price</th>
                                <th class="px-4 py-3 text-right font-medium text-zinc-600 dark:text-zinc-400">Cost</th>
                                <th class="px-4 py-3 text-right font-medium text-zinc-600 dark:text-zinc-400">Total</th>
                                <th class="px-4 py-3 text-right font-medium text-zinc-600 dark:text-zinc-400">Profit</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                            @foreach($this->orderItems as $item)
                                <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-900/50">
                                    <td class="px-4 py-3">
                                        <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ Str::limit($item['title'], 40) }}</div>
                                        <div class="text-xs text-zinc-500">{{ $item['sku'] }}</div>
                                    </td>
                                    <td class="px-4 py-3 text-center text-zinc-600 dark:text-zinc-400">
                                        {{ $item['quantity'] }}
                                    </td>
                                    <td class="px-4 py-3 text-right text-zinc-600 dark:text-zinc-400">
                                        £{{ number_format($item['price_per_unit'], 2) }}
                                    </td>
                                    <td class="px-4 py-3 text-right text-zinc-600 dark:text-zinc-400">
                                        £{{ number_format($item['unit_cost'], 2) }}
                                    </td>
                                    <td class="px-4 py-3 text-right font-medium text-zinc-900 dark:text-zinc-100">
                                        £{{ number_format($item['line_total'], 2) }}
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <span class="{{ $item['profit'] >= 0 ? 'text-green-600' : 'text-red-600' }} font-medium">
                                            £{{ number_format($item['profit'], 2) }}
                                        </span>
                                        <div class="text-xs text-zinc-500">
                                            {{ number_format($item['profit_margin'], 1) }}%
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="bg-zinc-50 dark:bg-zinc-900/50">
                            <tr class="font-medium">
                                <td class="px-4 py-3 text-zinc-900 dark:text-zinc-100">Total</td>
                                <td class="px-4 py-3 text-center text-zinc-600 dark:text-zinc-400">
                                    {{ $this->orderItems->sum('quantity') }}
                                </td>
                                <td class="px-4 py-3"></td>
                                <td class="px-4 py-3 text-right text-zinc-600 dark:text-zinc-400">
                                    £{{ number_format($this->profitAnalysis['total_cost'], 2) }}
                                </td>
                                <td class="px-4 py-3 text-right text-zinc-900 dark:text-zinc-100">
                                    £{{ number_format($this->profitAnalysis['total_revenue'], 2) }}
                                </td>
                                <td class="px-4 py-3 text-right text-green-600 font-bold">
                                    £{{ number_format($this->profitAnalysis['profit'], 2) }}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            {{-- Timeline & Details Sidebar --}}
            <div class="space-y-6">
                {{-- Order Timeline --}}
                <div class="bg-white dark:bg-zinc-800 rounded-xl shadow-sm border border-zinc-200 dark:border-zinc-700 p-6">
                    <flux:heading size="lg" class="mb-4">Order Timeline</flux:heading>
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
                <div class="bg-white dark:bg-zinc-800 rounded-xl shadow-sm border border-zinc-200 dark:border-zinc-700 p-6">
                    <flux:heading size="lg" class="mb-4">Order Details</flux:heading>
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
                    <div class="bg-white dark:bg-zinc-800 rounded-xl shadow-sm border border-zinc-200 dark:border-zinc-700 p-6">
                        <flux:heading size="lg" class="mb-4">Related Orders</flux:heading>
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
