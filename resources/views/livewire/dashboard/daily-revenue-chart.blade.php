<div class="bg-white dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700 p-3">
    <div class="flex items-center justify-between mb-3">
        <div>
            <span class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $viewMode === 'items' ? 'Items Sold' : 'Orders vs Revenue' }}</span>
            <p class="text-xs text-zinc-500 mt-0.5">{{ $this->periodLabel }}</p>
        </div>
        <flux:radio.group
            wire:model.live="viewMode"
            variant="segmented"
            size="sm"
        >
            <flux:radio value="orders_revenue" icon="chart-bar">Orders/Revenue</flux:radio>
            <flux:radio value="items" icon="cube">Items</flux:radio>
        </flux:radio.group>
    </div>

    <x-chart
        type="bar"
        :data="$this->chartData"
        :options="[
            'plugins' => [
                'legend' => ['display' => true, 'position' => 'top'],
                'tooltip' => ['enabled' => true, 'mode' => 'index', 'intersect' => false]
            ],
            'scales' => [
                'y' => ['beginAtZero' => true, 'grid' => ['color' => 'rgba(0,0,0,0.05)']],
                'x' => ['grid' => ['display' => false]]
            ],
            'elements' => ['bar' => ['borderRadius' => 4]]
        ]"
    />
</div>
