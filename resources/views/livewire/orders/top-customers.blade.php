<div class="bg-white dark:bg-zinc-800 rounded-xl shadow-sm border border-zinc-200 dark:border-zinc-700 p-6" wire:loading.class="opacity-50">
    <flux:heading size="lg" class="mb-4">Top Customers</flux:heading>

    <div class="space-y-3">
        @forelse($this->topCustomers as $index => $customer)
            <div class="flex items-center gap-3 p-3 rounded-lg bg-zinc-50 dark:bg-zinc-900/50 hover:bg-zinc-100 dark:hover:bg-zinc-700/50 transition-colors">
                <div class="flex-shrink-0 w-8 h-8 rounded-full bg-gradient-to-br from-blue-500 to-blue-600 flex items-center justify-center text-white font-bold text-sm">
                    {{ $index + 1 }}
                </div>
                <div class="flex-1 min-w-0">
                    <div class="font-medium text-zinc-900 dark:text-zinc-100 truncate">
                        {{ Str::limit($customer->get('reference'), 25) }}
                    </div>
                    <div class="flex items-center gap-2 text-xs text-zinc-500">
                        <span>{{ $customer->get('order_count') }} orders</span>
                        <span>•</span>
                        <span>{{ $customer->get('channel') }}</span>
                    </div>
                </div>
                <div class="text-right">
                    <div class="font-bold text-zinc-900 dark:text-zinc-100">
                        £{{ number_format($customer->get('total_spent'), 0) }}
                    </div>
                    <div class="text-xs text-zinc-500">
                        avg £{{ number_format($customer->get('avg_order_value'), 0) }}
                    </div>
                </div>
            </div>
        @empty
            <div class="text-center py-8 text-zinc-500">
                <flux:icon name="users" class="size-12 mx-auto mb-2 text-zinc-300" />
                <p>No customer data found</p>
            </div>
        @endforelse
    </div>
</div>
