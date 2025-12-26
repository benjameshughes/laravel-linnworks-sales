<div class="bg-white dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700 p-4" wire:loading.class="opacity-50">
    <div class="flex items-center justify-between mb-3">
        <span class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Top Customers</span>
    </div>

    <div class="space-y-2">
        @forelse($this->topCustomers as $index => $customer)
            <div class="flex items-center gap-3 py-2 {{ $index > 0 ? 'border-t border-zinc-100 dark:border-zinc-700/50' : '' }}">
                <span class="w-5 text-xs font-medium text-zinc-400">{{ $index + 1 }}</span>
                <div class="flex-1 min-w-0">
                    <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100 truncate">
                        {{ Str::limit($customer->get('reference'), 20) }}
                    </div>
                    <div class="text-xs text-zinc-500">
                        {{ $customer->get('order_count') }} orders · {{ $customer->get('channel') }}
                    </div>
                </div>
                <div class="text-right">
                    <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100">
                        £{{ number_format($customer->get('total_spent'), 0) }}
                    </div>
                </div>
            </div>
        @empty
            <div class="text-center py-6 text-zinc-500">
                <flux:icon name="users" class="size-6 mx-auto mb-1 text-zinc-300 dark:text-zinc-600" />
                <p class="text-xs">No customer data</p>
            </div>
        @endforelse
    </div>
</div>
