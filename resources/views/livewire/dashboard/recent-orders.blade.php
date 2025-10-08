<div class="bg-white dark:bg-zinc-800 rounded-xl shadow-sm border border-zinc-200 dark:border-zinc-700 transition-opacity duration-200" wire:loading.class="opacity-50">
        <div class="p-6 border-b border-zinc-200 dark:border-zinc-700">
            <div class="flex items-center justify-between">
                <div>
                    <flux:heading size="lg" class="text-zinc-900 dark:text-zinc-100">Recent Orders</flux:heading>
                    <p class="text-sm text-zinc-600 dark:text-zinc-400 mt-1">
                        Showing {{ $this->recentOrders->count() }} of {{ number_format($this->totalOrders) }} orders
                    </p>
                </div>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-zinc-50 dark:bg-zinc-700 border-b border-zinc-200 dark:border-zinc-600">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">Order</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">Date</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">Channel</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">Value</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @forelse($this->recentOrders as $order)
                        <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-700 transition-colors">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100">#{{ $order->order_number }}</div>
                                <div class="text-sm text-zinc-600 dark:text-zinc-400">{{ Str::limit($order->linnworks_order_id, 8) }}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-zinc-900 dark:text-zinc-100">{{ $order->received_date?->format('M j, Y') }}</div>
                                <div class="text-sm text-zinc-600 dark:text-zinc-400">{{ $order->received_date?->format('g:i A') }}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex flex-col gap-1">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-zinc-100 dark:bg-zinc-600 text-zinc-800 dark:text-zinc-200">
                                        {{ $order->channel_name }}
                                    </span>
                                    @if($order->sub_source)
                                        <span class="text-xs text-zinc-500 dark:text-zinc-400">
                                            {{ $order->sub_source }}
                                        </span>
                                    @endif
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100">£{{ number_format($order->total_charge, 2) }}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <flux:badge color="{{ $order->is_open ? 'blue' : 'zinc' }}" size="sm">
                                    {{ $order->is_open ? 'Open' : 'Closed' }}
                                </flux:badge>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-12 text-center">
                                <div class="text-zinc-500 dark:text-zinc-400">
                                    <flux:icon name="shopping-bag" class="size-12 mx-auto mb-4 text-zinc-300 dark:text-zinc-600" />
                                    <p class="text-lg font-medium">No orders found</p>
                                    <p class="text-sm">Try adjusting your filters or sync some orders</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
</div>