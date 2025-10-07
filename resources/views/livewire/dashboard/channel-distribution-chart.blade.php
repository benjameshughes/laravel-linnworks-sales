<div>
    <x-chart-widget
        type="doughnut"
        :chart-key="$this->chartKey"
        :data="$this->chartData"
        title="Revenue by Channel"
        subtitle="Channel performance breakdown"
        icon="chart-pie"
        height="350px"
    />
</div>
