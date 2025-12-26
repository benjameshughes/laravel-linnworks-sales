<div class="bg-white dark:bg-zinc-800 rounded-xl shadow-sm border border-zinc-200 dark:border-zinc-700" wire:loading.class="opacity-50">
    <div class="p-4 border-b border-zinc-200 dark:border-zinc-700">
        <div class="flex items-center justify-between">
            <flux:heading size="lg">Orders</flux:heading>
            <span class="text-sm text-zinc-500">{{ $this->orders->total() }} total</span>
        </div>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-zinc-50 dark:bg-zinc-900/50">
                <tr>
                    <th class="px-4 py-3 text-left font-medium text-zinc-600 dark:text-zinc-400 cursor-pointer hover:text-zinc-900 dark:hover:text-zinc-100"
                        wire:click="sortBy('number')">
                        <div class="flex items-center gap-1">
                            Order
                            @if($sortBy === 'number')
                                <flux:icon name="{{ $sortDirection === 'asc' ? 'chevron-up' : 'chevron-down' }}" class="size-3" />
                            @endif
                        </div>
                    </th>
                    <th class="px-4 py-3 text-left font-medium text-zinc-600 dark:text-zinc-400 cursor-pointer hover:text-zinc-900 dark:hover:text-zinc-100"
                        wire:click="sortBy('received_at')">
                        <div class="flex items-center gap-1">
                            Date
                            @if($sortBy === 'received_at')
                                <flux:icon name="{{ $sortDirection === 'asc' ? 'chevron-up' : 'chevron-down' }}" class="size-3" />
                            @endif
                        </div>
                    </th>
                    <th class="px-4 py-3 text-left font-medium text-zinc-600 dark:text-zinc-400 cursor-pointer hover:text-zinc-900 dark:hover:text-zinc-100"
                        wire:click="sortBy('source')">
                        <div class="flex items-center gap-1">
                            Channel
                            @if($sortBy === 'source')
                                <flux:icon name="{{ $sortDirection === 'asc' ? 'chevron-up' : 'chevron-down' }}" class="size-3" />
                            @endif
                        </div>
                    </th>
                    <th class="px-4 py-3 text-left font-medium text-zinc-600 dark:text-zinc-400">Items</th>
                    <th class="px-4 py-3 text-right font-medium text-zinc-600 dark:text-zinc-400 cursor-pointer hover:text-zinc-900 dark:hover:text-zinc-100"
                        wire:click="sortBy('total_charge')">
                        <div class="flex items-center justify-end gap-1">
                            Total
                            @if($sortBy === 'total_charge')
                                <flux:icon name="{{ $sortDirection === 'asc' ? 'chevron-up' : 'chevron-down' }}" class="size-3" />
                            @endif
                        </div>
                    </th>
                    <th class="px-4 py-3 text-left font-medium text-zinc-600 dark:text-zinc-400">Status</th>
                    <th class="px-4 py-3 text-left font-medium text-zinc-600 dark:text-zinc-400">Badges</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @forelse($this->orders as $order)
                    <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-900/50 transition-colors cursor-pointer"
                        wire:click="viewOrder('{{ $order->number }}')">
                        <td class="px-4 py-3">
                            <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $order->number }}</div>
                            @if($order->channel_reference_number)
                                <div class="text-xs text-zinc-500">{{ Str::limit($order->channel_reference_number, 20) }}</div>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-zinc-600 dark:text-zinc-400">
                            {{ $order->received_at?->format('M j, Y') }}
                            <div class="text-xs text-zinc-500">{{ $order->received_at?->format('g:i A') }}</div>
                        </td>
                        <td class="px-4 py-3">
                            <flux:badge color="zinc" size="sm">{{ $order->source ?? 'Unknown' }}</flux:badge>
                        </td>
                        <td class="px-4 py-3 text-zinc-600 dark:text-zinc-400">
                            {{ $order->num_items ?? $order->orderItems->sum('quantity') }}
                        </td>
                        <td class="px-4 py-3 text-right font-medium text-zinc-900 dark:text-zinc-100">
                            £{{ number_format($order->total_charge, 2) }}
                        </td>
                        <td class="px-4 py-3">
                            @if($order->is_cancelled)
                                <flux:badge color="red" size="sm">Cancelled</flux:badge>
                            @elseif($order->is_paid)
                                <flux:badge color="green" size="sm">Paid</flux:badge>
                            @elseif($order->status === 1)
                                <flux:badge color="blue" size="sm">Processed</flux:badge>
                            @else
                                <flux:badge color="amber" size="sm">Open</flux:badge>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex flex-wrap gap-1">
                                @php $badges = $this->getOrderBadges($order); @endphp
                                @foreach($badges as $badge)
                                    <flux:badge color="{{ $badge['color'] }}" size="xs" title="{{ $badge['description'] }}">
                                        <flux:icon name="{{ $badge['icon'] }}" class="size-3 mr-0.5" />
                                        {{ $badge['label'] }}
                                    </flux:badge>
                                @endforeach
                            </div>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <flux:button variant="ghost" size="xs" icon="chevron-right" />
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-4 py-12 text-center text-zinc-500">
                            <flux:icon name="inbox" class="size-12 mx-auto mb-3 text-zinc-300" />
                            <p>No orders found</p>
                            <p class="text-sm mt-1">Try adjusting your filters</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Pagination --}}
    @if($this->orders->hasPages())
        <div class="p-4 border-t border-zinc-200 dark:border-zinc-700">
            {{ $this->orders->links() }}
        </div>
    @endif
</div>
