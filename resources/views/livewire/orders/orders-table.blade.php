<div class="bg-white dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700" wire:loading.class="opacity-50">
    <div class="px-4 py-3 border-b border-zinc-200 dark:border-zinc-700">
        <div class="flex items-center justify-between">
            <span class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Recent Orders</span>
            <span class="text-xs text-zinc-500">{{ $this->orders->total() }} total</span>
        </div>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-zinc-50 dark:bg-zinc-900/50 text-xs uppercase tracking-wide">
                <tr>
                    <th class="px-4 py-2.5 text-left font-medium text-zinc-500 cursor-pointer hover:text-zinc-700 dark:hover:text-zinc-300"
                        wire:click="sortBy('received_at')">
                        <div class="flex items-center gap-1">
                            Order
                            @if($sortBy === 'received_at')
                                <flux:icon name="{{ $sortDirection === 'asc' ? 'chevron-up' : 'chevron-down' }}" class="size-3" />
                            @endif
                        </div>
                    </th>
                    <th class="px-4 py-2.5 text-left font-medium text-zinc-500 cursor-pointer hover:text-zinc-700 dark:hover:text-zinc-300"
                        wire:click="sortBy('source')">
                        <div class="flex items-center gap-1">
                            Channel
                            @if($sortBy === 'source')
                                <flux:icon name="{{ $sortDirection === 'asc' ? 'chevron-up' : 'chevron-down' }}" class="size-3" />
                            @endif
                        </div>
                    </th>
                    <th class="px-4 py-2.5 text-right font-medium text-zinc-500 cursor-pointer hover:text-zinc-700 dark:hover:text-zinc-300"
                        wire:click="sortBy('total_charge')">
                        <div class="flex items-center justify-end gap-1">
                            Total
                            @if($sortBy === 'total_charge')
                                <flux:icon name="{{ $sortDirection === 'asc' ? 'chevron-up' : 'chevron-down' }}" class="size-3" />
                            @endif
                        </div>
                    </th>
                    <th class="px-4 py-2.5 text-left font-medium text-zinc-500">Status</th>
                    <th class="px-4 py-2.5 w-8"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-100 dark:divide-zinc-700/50">
                @forelse($this->orders as $order)
                    <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-700/30 transition-colors cursor-pointer"
                        wire:click="viewOrder('{{ $order->number }}')">
                        {{-- Order + Date --}}
                        <td class="px-4 py-2.5">
                            <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $order->number }}</div>
                            <div class="text-xs text-zinc-500">
                                {{ $order->received_at?->format('M j') }} · {{ $order->received_at?->format('g:ia') }}
                            </div>
                        </td>
                        {{-- Channel + Items --}}
                        <td class="px-4 py-2.5">
                            <span class="text-zinc-700 dark:text-zinc-300">{{ $order->source ?? 'Direct' }}</span>
                            <div class="text-xs text-zinc-500">{{ $order->num_items ?? $order->orderItems->sum('quantity') }} items</div>
                        </td>
                        {{-- Total --}}
                        <td class="px-4 py-2.5 text-right">
                            <span class="font-medium text-zinc-900 dark:text-zinc-100">£{{ number_format($order->total_charge, 2) }}</span>
                        </td>
                        {{-- Status + Badges --}}
                        <td class="px-4 py-2.5">
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
                        </td>
                        {{-- Arrow --}}
                        <td class="px-4 py-2.5 text-right">
                            <flux:icon name="chevron-right" class="size-4 text-zinc-400" />
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-10 text-center text-zinc-500">
                            <flux:icon name="inbox" class="size-8 mx-auto mb-2 text-zinc-300 dark:text-zinc-600" />
                            <p class="text-sm">No orders found</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Pagination --}}
    @if($this->orders->hasPages())
        <div class="px-4 py-3 border-t border-zinc-200 dark:border-zinc-700">
            {{ $this->orders->links() }}
        </div>
    @endif
</div>
