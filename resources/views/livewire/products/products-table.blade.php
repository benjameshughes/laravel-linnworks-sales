<div class="bg-white dark:bg-zinc-800 rounded-xl shadow-sm border border-zinc-200 dark:border-zinc-700">
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
                <flux:button
                    variant="{{ $sortBy === 'quantity' ? 'primary' : 'ghost' }}"
                    size="sm"
                    wire:click="sortBy('quantity')"
                >
                    Qty
                    @if($sortBy === 'quantity')
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
                                {{ number_format($item['total_revenue'], 2) }}
                            </div>
                            <div class="text-xs text-zinc-500">
                                {{ number_format($item['avg_selling_price'], 2) }} avg
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
