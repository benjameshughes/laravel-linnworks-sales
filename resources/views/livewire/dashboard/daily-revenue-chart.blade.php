<div class="transition-opacity duration-200" wire:loading.class="opacity-50">
    <x-chart-widget
        type="line"
        :chart-key="$this->chartKey"
        :data="$this->chartData"
        :options="$this->chartOptions"
        title="{{ $viewMode === 'items' ? 'Items Sold' : 'Orders vs Revenue' }}"
        subtitle="{{ $this->periodLabel }}"
        icon="{{ $viewMode === 'items' ? 'cube' : 'chart-bar' }}"
        height="350px"
    >
        <x-slot:actions>
            <div x-data="{ cooldown: false }">
                <flux:radio.group
                    wire:model.live="viewMode"
                    variant="segmented"
                    class="[&>label]:transition-all [&>label]:duration-200"
                    wire:loading.attr="disabled"
                    x-bind:disabled="cooldown"
                    x-on:change="cooldown = true; setTimeout(() => cooldown = false, 500)"
                >
                    <flux:radio value="orders_revenue" icon="chart-bar">Orders/Revenue</flux:radio>
                    <flux:radio value="items" icon="cube">Items</flux:radio>
                </flux:radio.group>
            </div>
        </x-slot:actions>
    </x-chart-widget>
</div>