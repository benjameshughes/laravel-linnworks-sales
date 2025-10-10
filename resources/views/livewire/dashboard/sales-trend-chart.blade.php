<div class="transition-opacity duration-200" wire:loading.class="opacity-50">
    <x-chart-widget
        type="area"
        :chart-key="$this->chartKey"
        :data="$this->chartData"
        title="Sales Trend"
        :subtitle="$this->periodLabel"
        icon="chart-line"
        height="350px"
    >
        <x-slot:actions>
            <flux:radio.group
                wire:model.live="viewMode"
                variant="segmented"
                class="[&>label]:transition-all [&>label]:duration-200"
            >
                <flux:radio value="revenue" icon="currency-pound">Revenue</flux:radio>
                <flux:radio value="orders" icon="shopping-cart">Orders</flux:radio>
            </flux:radio.group>
        </x-slot:actions>
    </x-chart-widget>
</div>