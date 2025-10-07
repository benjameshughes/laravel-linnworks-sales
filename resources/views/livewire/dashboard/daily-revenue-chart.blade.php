<div class="transition-opacity duration-200" wire:loading.class="opacity-50">
    <x-chart-widget
        type="bar"
        :chart-key="$this->chartKey"
        :data="$this->chartData"
        title="Daily Revenue"
        subtitle="Revenue per day for {{ $this->periodLabel }}"
        icon="currency-pound"
        height="250px"
    />
</div>