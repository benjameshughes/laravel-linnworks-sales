<div class="bg-white dark:bg-zinc-800 rounded-md shadow-sm border border-zinc-200 dark:border-zinc-700">
    <div class="p-3 border-b border-zinc-200 dark:border-zinc-700">
        <div class="flex items-center justify-between">
            <div>
                <div class="flex items-center gap-3">
                    <flux:icon name="chart-line" class="size-5 text-zinc-500 dark:text-zinc-400" />
                    <h3 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">Sales Trend</h3>
                </div>
                <p class="text-sm text-zinc-600 dark:text-zinc-400 mt-1">{{ $this->periodLabel }}</p>
            </div>
            <div class="flex items-center gap-2">
                <flux:radio.group
                    wire:model.live="viewMode"
                    variant="segmented"
                >
                    <flux:radio value="revenue" icon="currency-pound">Revenue</flux:radio>
                    <flux:radio value="orders" icon="shopping-cart">Orders</flux:radio>
                </flux:radio.group>
            </div>
        </div>
    </div>

    <div class="p-6">
        @if(empty($dailyBreakdown))
            <div class="text-center text-zinc-500 dark:text-zinc-400 py-8">
                <p>No data available</p>
            </div>
        @else
            <div
                wire:key="chart-{{ md5(json_encode($dailyBreakdown)) }}"
                x-data="{
                    chart: null,
                    init() {
                        this.chart = new Chart(this.$refs.canvas, {
                            type: 'line',
                            data: @js($this->chartData()),
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                plugins: {
                                    legend: { display: false },
                                    tooltip: { enabled: true, mode: 'index', intersect: false }
                                },
                                scales: {
                                    y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.05)' } },
                                    x: { grid: { display: false } }
                                },
                                elements: {
                                    line: { tension: 0.3 },
                                    point: { radius: 3, hoverRadius: 5 }
                                }
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
