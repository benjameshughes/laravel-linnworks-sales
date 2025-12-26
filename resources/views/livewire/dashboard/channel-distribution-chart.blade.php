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

    @if(empty($channelData))
        <div class="text-center text-zinc-500 dark:text-zinc-400 py-8">
            <p class="text-sm">No data available</p>
        </div>
    @else
        <div
            wire:key="chart-{{ md5(json_encode($channelData) . $viewMode) }}"
            x-data="{
                chart: null,
                init() {
                    this.chart = new Chart(this.$refs.canvas, {
                        type: 'doughnut',
                        data: @js($this->chartData()),
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
                destroy() {
                    if (this.chart) {
                        this.chart.destroy();
                        this.chart = null;
                    }
                }
            }"
            class="h-64"
        >
            <canvas x-ref="canvas"></canvas>
        </div>
    @endif
</div>
