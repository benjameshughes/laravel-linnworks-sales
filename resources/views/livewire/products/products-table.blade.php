<div>
    <div class="flex items-center justify-between mb-3">
        <div>
            <flux:heading size="lg" class="text-zinc-900 dark:text-zinc-100">Top Products</flux:heading>
            <p class="text-sm text-zinc-500 mt-1">
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

    <flux:table>
        <flux:table.columns>
            <flux:table.column>Product</flux:table.column>
            <flux:table.column>Sold</flux:table.column>
            <flux:table.column>Revenue</flux:table.column>
            <flux:table.column>Margin</flux:table.column>
            <flux:table.column>Action</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse($this->products as $item)
                @php $product = $item['product']; @endphp
                <flux:table.row :key="$product->id" class="{{ $selectedProduct === $product->sku ? 'bg-blue-50 dark:bg-blue-900/20' : '' }}">
                    <flux:table.cell>
                        <div class="font-medium">{{ Str::limit($product->title, 30) }}</div>
                        <div class="text-sm text-zinc-500">{{ $product->sku }}</div>
                        @if($product->category_name)
                            <div class="text-xs text-zinc-500">{{ $product->category_name }}</div>
                        @endif
                        @if(isset($item['badges']) && $item['badges'] && $item['badges']->isNotEmpty())
                            <div class="flex flex-wrap gap-1 mt-2">
                                @foreach($item['badges']->take(3) as $badge)
                                    <flux:badge color="{{ $badge['color'] }}" size="xs" title="{{ $badge['description'] }}">
                                        <flux:icon name="{{ $badge['icon'] }}" class="size-3 mr-1" />
                                        {{ $badge['label'] }}
                                    </flux:badge>
                                @endforeach
                                @if($item['badges']->count() > 3)
                                    <flux:badge color="zinc" size="xs" title="View all badges">
                                        +{{ $item['badges']->count() - 3 }}
                                    </flux:badge>
                                @endif
                            </div>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>
                        <flux:badge color="blue" size="sm">{{ number_format($item['total_sold']) }}</flux:badge>
                    </flux:table.cell>
                    <flux:table.cell>
                        <div class="font-medium">£{{ number_format($item['total_revenue'], 2) }}</div>
                        <div class="text-xs text-zinc-500">£{{ number_format($item['avg_selling_price'], 2) }} avg</div>
                    </flux:table.cell>
                    <flux:table.cell>
                        <flux:badge
                            color="{{ $item['profit_margin_percent'] >= 20 ? 'green' : ($item['profit_margin_percent'] >= 10 ? 'yellow' : 'red') }}"
                            size="sm"
                        >
                            {{ number_format($item['profit_margin_percent'], 1) }}%
                        </flux:badge>
                    </flux:table.cell>
                    <flux:table.cell>
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
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="5" class="text-center py-12">
                        <flux:icon name="cube" class="size-12 mx-auto mb-4 text-zinc-300 dark:text-zinc-600" />
                        <p class="text-lg font-medium text-zinc-500">No products found</p>
                        <p class="text-sm text-zinc-500">Try adjusting your search or sync some products</p>
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</div>
