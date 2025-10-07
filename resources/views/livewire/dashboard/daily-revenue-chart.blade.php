<div>
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
