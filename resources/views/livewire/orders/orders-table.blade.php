<div wire:loading.class="opacity-50">
    <flux:table :paginate="$this->orders">
        <flux:table.columns>
            <flux:table.column sortable :sorted="$sortBy === 'received_at'" :direction="$sortDirection" wire:click="sortBy('received_at')">
                Order
            </flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'source'" :direction="$sortDirection" wire:click="sortBy('source')">
                Channel
            </flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'total_charge'" :direction="$sortDirection" wire:click="sortBy('total_charge')" align="end">
                Total
            </flux:table.column>
            <flux:table.column>Status</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse($this->orders as $order)
                <flux:table.row :key="$order->id" wire:click="viewOrder('{{ $order->number }}')" class="cursor-pointer">
                    <flux:table.cell>
                        <div class="font-medium">{{ $order->number }}</div>
                        <div class="text-xs text-zinc-500">
                            {{ $order->received_at?->format('M j') }} · {{ $order->received_at?->format('g:ia') }}
                        </div>
                    </flux:table.cell>
                    <flux:table.cell>
                        <span>{{ $order->source ?? 'Direct' }}</span>
                        <div class="text-xs text-zinc-500">{{ $order->num_items ?? $order->orderItems->sum('quantity') }} items</div>
                    </flux:table.cell>
                    <flux:table.cell align="end" variant="strong">
                        £{{ number_format($order->total_charge, 2) }}
                    </flux:table.cell>
                    <flux:table.cell>
                        <div class="flex flex-wrap items-center gap-1">
                            @if($order->is_cancelled)
                                <flux:badge color="red" size="sm">Cancelled</flux:badge>
                            @elseif($order->is_paid)
                                <flux:badge color="lime" size="sm">Paid</flux:badge>
                            @elseif($order->status === 1)
                                <flux:badge color="sky" size="sm">Processed</flux:badge>
                            @else
                                <flux:badge color="amber" size="sm">Open</flux:badge>
                            @endif
                            @php $badges = $this->getOrderBadges($order); @endphp
                            @foreach(array_slice($badges, 0, 2) as $badge)
                                <flux:badge color="zinc" size="sm" title="{{ $badge['description'] }}">
                                    {{ $badge['label'] }}
                                </flux:badge>
                            @endforeach
                        </div>
                    </flux:table.cell>
                    <flux:table.cell>
                        <flux:icon name="chevron-right" class="size-4 text-zinc-400" />
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="5" class="text-center py-10">
                        <flux:icon name="inbox" class="size-8 mx-auto mb-2 text-zinc-300 dark:text-zinc-600" />
                        <p class="text-sm text-zinc-500">No orders found</p>
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</div>
