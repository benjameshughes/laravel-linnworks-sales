<div class="transition-opacity duration-200" wire:loading.class="opacity-50">
    <x-chart-widget
        type="line"
        :chart-key="$this->chartKey()"
        :data="$this->chartData"
        :options="$this->chartOptions"
        title="{{ $viewMode === 'items' ? 'Items Sold' : 'Orders vs Revenue' }}"
        subtitle="{{ $this->periodLabel }}"
        icon="{{ $viewMode === 'items' ? 'cube' : 'chart-bar' }}"
        height="350px"
    >
        <x-slot:actions>
            <flux:radio.group
                wire:model.live="viewMode"
                variant="segmented"
                class="[&>label]:transition-all [&>label]:duration-200"
            >
                <flux:radio value="orders_revenue" icon="chart-bar">Orders/Revenue</flux:radio>
                <flux:radio value="items" icon="cube">Items</flux:radio>
            </flux:radio.group>
        </x-slot:actions>
    </x-chart-widget>
</div>