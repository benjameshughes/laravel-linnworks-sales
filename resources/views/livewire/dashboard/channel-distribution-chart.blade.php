<div class="bg-white dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700 p-3">
    <div class="flex items-center justify-between mb-3">
        <div>
            <span class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Revenue by Channel</span>
            <p class="text-xs text-zinc-500 mt-0.5">Channel performance breakdown</p>
        </div>
        <flux:radio.group
            wire:model.live="viewMode"
            variant="segmented"
            size="sm"
        >
            <flux:radio value="detailed" icon="view-columns">Detailed</flux:radio>
            <flux:radio value="grouped" icon="squares-2x2">Grouped</flux:radio>
        </flux:radio.group>
    </div>

    <div
        wire:ignore
        x-data="{
            chart: null,
            initialData: @js($chartData),

            init() {
                this.$nextTick(() => {
                    this.createChart(this.initialData);
                });
            },

            createChart(data) {
                if (!data || !data.labels || data.labels.length === 0) {
                    return;
                }

                if (this.chart) {
                    this.chart.destroy();
                }

                this.chart = new Chart(this.$refs.canvas, {
                    type: 'doughnut',
                    data: data,
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: true,
                                position: 'right',
                                labels: {
                                    boxWidth: 12,
                                    padding: 16
                                }
                            },
                            tooltip: { enabled: true }
                        },
                        cutout: '60%'
                    }
                });
            },

            updateChart(data) {
                if (!data || !data.labels || data.labels.length === 0) {
                    return;
                }

                if (!this.chart) {
                    this.createChart(data);
                    return;
                }

                this.chart.data = data;
                this.chart.update();
            }
        }"
        x-on:channel-distribution-chart-updated.window="updateChart($event.detail.data)"
        class="h-64"
    >
        <canvas x-ref="canvas"></canvas>
    </div>
</div>
