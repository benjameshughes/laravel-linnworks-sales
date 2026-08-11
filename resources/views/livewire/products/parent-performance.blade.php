<div>
    <div class="bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 p-6">
        <div class="flex items-center gap-3 mb-4">
            <div class="flex-shrink-0 w-8 h-8 bg-indigo-100 dark:bg-indigo-900/20 rounded-lg flex items-center justify-center">
                <flux:icon.squares-2x2 class="size-5 text-indigo-600 dark:text-indigo-400" />
            </div>
            <flux:heading size="lg">Product Ranges</flux:heading>
        </div>

        @if($this->parents->isEmpty())
            <div class="text-center py-8 text-zinc-500 dark:text-zinc-400 text-sm">
                No variation group data available
            </div>
        @else
            <div class="space-y-3">
                @foreach($this->parents as $parent)
                    <div class="p-4 rounded-lg bg-zinc-50 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 hover:border-zinc-300 dark:hover:border-zinc-600 transition-colors">
                        <div class="flex items-center justify-between mb-2">
                            <div>
                                <span class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $parent->parent_sku }}</span>
                                @if($parent->parent_title)
                                    <span class="text-sm text-zinc-500 dark:text-zinc-400 ml-2">{{ $parent->parent_title }}</span>
                                @endif
                            </div>
                            <flux:badge size="sm" color="zinc">{{ $parent->variant_count }} variants</flux:badge>
                        </div>

                        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 text-sm">
                            <div>
                                <span class="text-zinc-500 dark:text-zinc-400">Revenue</span>
                                <div class="font-semibold text-emerald-600 dark:text-emerald-400">£{{ number_format($parent->total_revenue, 2) }}</div>
                            </div>
                            <div>
                                <span class="text-zinc-500 dark:text-zinc-400">Units</span>
                                <div class="font-semibold text-zinc-900 dark:text-zinc-100">{{ number_format($parent->total_quantity) }}</div>
                            </div>
                            <div>
                                <span class="text-zinc-500 dark:text-zinc-400">Orders</span>
                                <div class="font-semibold text-zinc-900 dark:text-zinc-100">{{ number_format($parent->order_count) }}</div>
                            </div>
                            <div>
                                <span class="text-zinc-500 dark:text-zinc-400">Margin</span>
                                <div class="font-semibold {{ $parent->margin_percentage >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400' }}">{{ number_format($parent->margin_percentage, 1) }}%</div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
