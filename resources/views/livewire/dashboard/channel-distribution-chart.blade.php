<div class="bg-white dark:bg-zinc-800 rounded-xl shadow-sm border border-zinc-200 dark:border-zinc-700">
    <div class="p-3 pb-2 border-b border-zinc-200 dark:border-zinc-700">
        <div class="flex items-center justify-between">
            <div>
                <div class="flex items-center gap-3">
                    <flux:icon name="chart-pie" class="size-5 text-zinc-500 dark:text-zinc-400" />
                    <h3 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">Revenue by Channel</h3>
                </div>
                <p class="text-sm text-zinc-600 dark:text-zinc-400 mt-1">Channel performance breakdown</p>
            </div>
            <div class="flex items-center gap-2">
                <flux:radio.group
                    wire:model.live="viewMode"
                    variant="segmented"
                >
                    <flux:radio value="detailed" icon="view-columns">Detailed</flux:radio>
                    <flux:radio value="grouped" icon="squares-2x2">Grouped</flux:radio>
                </flux:radio.group>
            </div>
        </div>
    </div>

    <div class="p-6">
        @if(empty($channelData))
            <div class="text-center text-zinc-500 dark:text-zinc-400 py-8">
                <p>No data available</p>
            </div>
        @else
            {{-- wire:key forces full replacement when data changes, avoiding Chart.js conflicts --}}
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
                style="height: 350px"
            >
                <canvas x-ref="canvas"></canvas>
            </div>
        @endif
    </div>
</div>
