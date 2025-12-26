<div class="bg-white dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700 p-3">
    <span class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Top Products</span>
    <div class="space-y-2 mt-3">
        @forelse($this->topProducts as $index => $product)
            <div class="flex items-center justify-between p-2 rounded-lg bg-zinc-50 dark:bg-zinc-700/50 border border-zinc-200 dark:border-zinc-600">
                <div class="flex items-center gap-2">
                    <div class="w-6 h-6 rounded-full bg-zinc-200 dark:bg-zinc-600 flex items-center justify-center text-zinc-600 dark:text-zinc-300 text-xs font-bold">
                        {{ $index + 1 }}
                    </div>
                    <div>
                        <div class="font-medium text-zinc-900 dark:text-zinc-100 text-sm">{{ Str::limit($product->get('title'), 25) }}</div>
                        <div class="text-xs text-zinc-500">{{ $product->get('sku') }}</div>
                    </div>
                </div>
                <div class="text-right">
                    <div class="font-semibold text-emerald-600 dark:text-emerald-400 text-sm">£{{ number_format($product->get('revenue'), 0) }}</div>
                    <div class="text-xs text-zinc-500">{{ $product->get('quantity') }} sold</div>
                </div>
            </div>
        @empty
            <div class="text-center py-6 text-zinc-500 dark:text-zinc-400">
                <flux:icon name="cube" class="size-10 mx-auto mb-2 text-zinc-300 dark:text-zinc-600" />
                <p class="text-sm">No products found</p>
            </div>
        @endforelse
    </div>
</div>
