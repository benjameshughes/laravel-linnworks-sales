<div class="bg-white dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700 p-3">
    <div class="flex items-center justify-between mb-3">
        <div>
            <span class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Sales Trend</span>
            <p class="text-xs text-zinc-500 mt-0.5">{{ $this->periodLabel }}</p>
        </div>
        <flux:radio.group
            wire:model.live="viewMode"
            variant="segmented"
            size="sm"
        >
            <flux:radio value="revenue" icon="currency-pound">Revenue</flux:radio>
            <flux:radio value="orders" icon="shopping-cart">Orders</flux:radio>
        </flux:radio.group>
    </div>

    <div
        wire:ignore
        x-data="{
            chart: null,
            createChart(data) {
                const canvas = this.$refs.canvas;
                if (this.chart) {
                    this.chart.destroy();
                }
                if (data.labels && data.labels.length > 0) {
                    this.chart = new Chart(canvas, {
                        type: 'line',
                        data: data,
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
                }
            }
        }"
        x-init="createChart(@js($chartData))"
        class="h-64 relative"
        @sales-chart-updated.window="createChart($event.detail)"
    >
        <canvas x-ref="canvas"></canvas>
    </div>
</div>
