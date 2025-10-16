<div class="transition-opacity duration-200" wire:loading.class="opacity-50">
    <x-chart-widget
        type="doughnut"
        :chart-key="$this->chartKey()"
        :data="$this->chartData"
        title="Revenue by Channel"
        subtitle="Channel performance breakdown"
        icon="chart-pie"
        height="350px"
    >
        <x-slot:actions>
            <flux:radio.group
                wire:model.live="viewMode"
                variant="segmented"
                class="[&>label]:transition-all [&>label]:duration-200"
            >
                <flux:radio value="detailed" icon="view-columns">Detailed</flux:radio>
                <flux:radio value="grouped" icon="squares-2x2">Grouped</flux:radio>
            </flux:radio.group>
        </x-slot:actions>
    </x-chart-widget>
</div>