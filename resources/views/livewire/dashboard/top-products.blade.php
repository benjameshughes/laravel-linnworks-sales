<div class="bg-white dark:bg-zinc-800 rounded-xl shadow-sm border border-zinc-200 dark:border-zinc-700 p-6 transition-opacity duration-200" wire:loading.class="opacity-50">
        <flux:heading size="lg" class="text-zinc-900 dark:text-zinc-100 mb-6">Top Products</flux:heading>
        <div class="space-y-4">
            @forelse($this->topProducts as $index => $product)
                <div class="flex items-center justify-between p-3 rounded-lg bg-zinc-50 dark:bg-zinc-700 border border-zinc-200 dark:border-zinc-600">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-full bg-blue-100 dark:bg-blue-900 flex items-center justify-center text-blue-600 dark:text-blue-400 text-sm font-bold">
                            {{ $index + 1 }}
                        </div>
                        <div>
                            <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $product->get('title') }}</div>
                            <div class="text-sm text-zinc-600 dark:text-zinc-400">{{ $product->get('sku') }}</div>
                        </div>
                    </div>
                    <div class="text-right">
                        <div class="font-bold text-zinc-900 dark:text-zinc-100">£{{ number_format($product->get('revenue'), 0) }}</div>
                        <div class="text-sm text-zinc-600 dark:text-zinc-400">{{ $product->get('quantity') }} sold</div>
                    </div>
                </div>
            @empty
                <div class="text-center py-8 text-zinc-500 dark:text-zinc-400">
                    <flux:icon name="cube" class="size-12 mx-auto mb-2 text-zinc-300 dark:text-zinc-600" />
                    <p>No products found</p>
                </div>
            @endforelse
        </div>
</div>
