<div class="transition-opacity duration-200" wire:loading.class="opacity-50">
    <x-chart-widget
        type="doughnut"
        :chart-key="$this->chartKey"
        :data="$this->chartData"
        title="Revenue by Channel"
        subtitle="Channel performance breakdown"
        icon="chart-pie"
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
                    <flux:radio value="detailed" icon="view-columns">Detailed</flux:radio>
                    <flux:radio value="grouped" icon="squares-2x2">Grouped</flux:radio>
                </flux:radio.group>
            </div>
        </x-slot:actions>
    </x-chart-widget>
</div>