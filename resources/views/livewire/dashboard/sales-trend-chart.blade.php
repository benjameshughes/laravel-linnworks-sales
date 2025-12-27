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

    {{-- Chart container - wire:ignore prevents Livewire from touching it --}}
    <div
        wire:ignore
        x-data="{
            chart: null,
            hasData: {{ empty($this->chartData()['labels']) ? 'false' : 'true' }},

            init() {
                if (this.hasData) {
                    this.createChart(@js($this->chartData()));
                }
            },

            createChart(data) {
                if (this.chart) {
                    this.chart.destroy();
                }

                this.chart = new Chart(this.$refs.canvas, {
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
            },

            updateChart(data) {
                if (!data.labels || data.labels.length === 0) {
                    this.hasData = false;
                    if (this.chart) {
                        this.chart.destroy();
                        this.chart = null;
                    }
                    return;
                }

                this.hasData = true;

                if (!this.chart) {
                    this.createChart(data);
                } else {
                    this.chart.data = data;
                    this.chart.update('none');
                }
            }
        }"
        @sales-trend-updated.window="updateChart($event.detail.data)"
        class="h-64"
    >
        <div x-show="!hasData" class="flex items-center justify-center h-full text-zinc-500 dark:text-zinc-400">
            <p class="text-sm">No data available</p>
        </div>
        <canvas x-ref="canvas" x-show="hasData"></canvas>
    </div>
</div>
