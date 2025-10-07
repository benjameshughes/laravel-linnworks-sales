<div class="transition-opacity duration-200" wire:loading.class="opacity-50">
    <x-chart-widget
        type="area"
        :chart-key="$this->chartKey"
        :data="$this->chartData"
        title="Sales Trend"
        :subtitle="$this->periodLabel"
        icon="chart-line"
        height="350px"
    />
</div>