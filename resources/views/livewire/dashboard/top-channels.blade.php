<div class="bg-white dark:bg-zinc-800 rounded-xl shadow-sm border border-zinc-200 dark:border-zinc-700 p-6 transition-opacity duration-200" wire:loading.class="opacity-50">
        <flux:heading size="lg" class="text-zinc-900 dark:text-zinc-100 mb-6">Top Channels</flux:heading>
        <div class="space-y-4">
            @forelse($this->topChannels as $index => $channel)
                <div class="flex items-center justify-between p-3 rounded-lg bg-zinc-50 dark:bg-zinc-700 border border-zinc-200 dark:border-zinc-600">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-full bg-emerald-100 dark:bg-emerald-900 flex items-center justify-center text-emerald-600 dark:text-emerald-400 text-sm font-bold">
                            {{ $index + 1 }}
                        </div>
                        <div>
                            <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $channel->get('name') }}</div>
                            <div class="text-sm text-zinc-600 dark:text-zinc-400">£{{ number_format($channel->get('avg_order_value'), 0) }} avg</div>
                        </div>
                    </div>
                    <div class="text-right">
                        <div class="font-bold text-zinc-900 dark:text-zinc-100">£{{ number_format($channel->get('revenue'), 0) }}</div>
                        <div class="text-sm text-zinc-600 dark:text-zinc-400">{{ $channel->get('orders') }} orders</div>
                    </div>
                </div>
            @empty
                <div class="text-center py-8 text-zinc-500 dark:text-zinc-400">
                    <flux:icon name="chart-bar" class="size-12 mx-auto mb-2 text-zinc-300 dark:text-zinc-600" />
                    <p>No channels found</p>
                </div>
            @endforelse
        </div>
</div>
