<div wire:loading.class="opacity-50">
    <div class="flex items-center justify-between mb-3">
        <div>
            <span class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Recent Orders</span>
            <p class="text-xs text-zinc-500 mt-0.5">
                Showing {{ $this->recentOrders->count() }} of {{ number_format($this->totalOrders) }} orders
            </p>
        </div>
    </div>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>Order</flux:table.column>
            <flux:table.column>Date</flux:table.column>
            <flux:table.column>Channel</flux:table.column>
            <flux:table.column>Value</flux:table.column>
            <flux:table.column>Status</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse($this->recentOrders as $order)
                <flux:table.row :key="$order->id">
                    <flux:table.cell>
                        <div class="font-medium">#{{ $order->number }}</div>
                        <div class="text-sm text-zinc-500">{{ Str::limit($order->order_id, 8) }}</div>
                    </flux:table.cell>
                    <flux:table.cell>
                        <div>{{ $order->received_at?->format('M j, Y') }}</div>
                        <div class="text-sm text-zinc-500">{{ $order->received_at?->format('g:i A') }}</div>
                    </flux:table.cell>
                    <flux:table.cell>
                        <flux:badge color="zinc" size="sm">{{ $order->source }}</flux:badge>
                        @if($order->subsource)
                            <div class="text-xs text-zinc-500 mt-1">{{ $order->subsource }}</div>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>
                        <span class="font-semibold text-emerald-600 dark:text-emerald-400">£{{ number_format($order->total_charge, 2) }}</span>
                    </flux:table.cell>
                    <flux:table.cell>
                        <flux:badge color="{{ $order->status ? 'blue' : 'zinc' }}" size="sm">
                            {{ $order->status ? 'Open' : 'Closed' }}
                        </flux:badge>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="5" class="text-center py-12">
                        <flux:icon name="shopping-bag" class="size-10 mx-auto mb-3 text-zinc-300 dark:text-zinc-600" />
                        <p class="text-sm font-medium text-zinc-500">No orders found</p>
                        <p class="text-xs text-zinc-400">Try adjusting your filters or sync some orders</p>
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</div>
