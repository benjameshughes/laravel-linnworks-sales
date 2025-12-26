<div class="bg-white dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700 p-3">
    <flux:heading size="lg" class="text-zinc-900 dark:text-zinc-100 mb-3">Top Categories</flux:heading>
    <div class="space-y-2">
        @forelse($this->topCategories as $index => $category)
            <div class="flex items-center justify-between p-2 rounded-lg bg-zinc-50 dark:bg-zinc-700 border border-zinc-200 dark:border-zinc-600 cursor-pointer hover:bg-zinc-100 dark:hover:bg-zinc-600/50 {{ $selectedCategory === $category['category'] ? 'ring-2 ring-blue-500' : '' }}"
                 wire:click="selectCategory('{{ $category['category'] }}')">
                <div class="flex items-center gap-2">
                    <div class="w-6 h-6 rounded-full bg-purple-100 dark:bg-purple-900 flex items-center justify-center text-purple-600 dark:text-purple-400 text-xs font-bold">
                        {{ $index + 1 }}
                    </div>
                    <div>
                        <div class="font-medium text-zinc-900 dark:text-zinc-100 text-sm">{{ $category['category'] }}</div>
                        <div class="text-xs text-zinc-600 dark:text-zinc-400">{{ $category['product_count'] }} products</div>
                    </div>
                </div>
                <div class="text-right">
                    <div class="font-bold text-zinc-900 dark:text-zinc-100 text-sm">{{ number_format($category['total_revenue'], 0) }}</div>
                    <div class="text-xs text-zinc-600 dark:text-zinc-400">{{ number_format($category['total_quantity']) }} units</div>
                </div>
            </div>
        @empty
            <div class="text-center py-6 text-zinc-500 dark:text-zinc-400">
                <flux:icon name="tag" class="size-10 mx-auto mb-2 text-zinc-300 dark:text-zinc-600" />
                <p class="text-sm">No categories found</p>
            </div>
        @endforelse
    </div>
</div>
