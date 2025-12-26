<div class="bg-white dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700 p-3">
    <flux:heading size="lg" class="text-zinc-900 dark:text-zinc-100 mb-3">Stock Alerts</flux:heading>
    <div class="space-y-2">
        @forelse($this->stockAlerts as $alert)
            @php $product = $alert['product']; @endphp
            <div class="flex items-center justify-between p-2 rounded-lg bg-zinc-50 dark:bg-zinc-700 border border-zinc-200 dark:border-zinc-600">
                <div>
                    <div class="font-medium text-zinc-900 dark:text-zinc-100 text-sm">
                        {{ Str::limit($product->title, 20) }}
                    </div>
                    <div class="text-xs text-zinc-600 dark:text-zinc-400">{{ $product->sku }}</div>
                </div>
                <div class="text-right">
                    <flux:badge color="red" size="sm">
                        {{ $alert['stock_level'] }} left
                    </flux:badge>
                    <div class="text-xs text-zinc-500 mt-1">Min: {{ $alert['stock_minimum'] }}</div>
                </div>
            </div>
        @empty
            <div class="text-center py-6 text-zinc-500 dark:text-zinc-400">
                <flux:icon name="check-circle" class="size-10 mx-auto mb-2 text-green-300 dark:text-green-600" />
                <p class="text-sm">All stock levels OK</p>
            </div>
        @endforelse
    </div>
</div>
