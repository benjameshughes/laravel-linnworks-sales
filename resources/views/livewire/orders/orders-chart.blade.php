<div class="bg-white dark:bg-zinc-800 rounded-xl shadow-sm border border-zinc-200 dark:border-zinc-700 p-6">
    <div class="flex items-center justify-between mb-6">
        <flux:heading size="lg">Revenue Trend</flux:heading>
        <div class="flex items-center gap-2">
            {{-- Metric Toggle --}}
            <flux:button.group>
                <flux:button
                    wire:click="setMetric('revenue')"
                    variant="{{ $metric === 'revenue' ? 'primary' : 'ghost' }}"
                    size="xs"
                >
                    Revenue
                </flux:button>
                <flux:button
                    wire:click="setMetric('orders')"
                    variant="{{ $metric === 'orders' ? 'primary' : 'ghost' }}"
                    size="xs"
                >
                    Orders
                </flux:button>
                <flux:button
                    wire:click="setMetric('items')"
                    variant="{{ $metric === 'items' ? 'primary' : 'ghost' }}"
                    size="xs"
                >
                    Items
                </flux:button>
            </flux:button.group>

            {{-- Chart Type Toggle --}}
            <flux:button
                wire:click="toggleChartType"
                variant="ghost"
                size="sm"
                icon="{{ $chartType === 'line' ? 'chart-bar' : 'presentation-chart-line' }}"
                title="Toggle chart type"
            />
        </div>
    </div>

    @if($this->chartData->isNotEmpty())
        <div class="h-64"
             wire:key="orders-chart-{{ $period }}-{{ $metric }}-{{ $chartType }}"
             wire:ignore
             x-data="{
                 chart: null,
                 init() {
                     const ctx = this.$refs.canvas.getContext('2d');
                     this.chart = new Chart(ctx, {
                         type: '{{ $chartType }}',
                         data: {
                             labels: @json($this->chartLabels),
                             datasets: [{
                                 label: '{{ ucfirst($metric) }}',
                                 data: @json($this->chartValues),
                                 borderColor: 'rgb(59, 130, 246)',
                                 backgroundColor: '{{ $chartType === 'line' ? 'rgba(59, 130, 246, 0.1)' : 'rgba(59, 130, 246, 0.8)' }}',
                                 borderWidth: 2,
                                 fill: {{ $chartType === 'line' ? 'true' : 'false' }},
                                 tension: 0.4,
                             }]
                         },
                         options: {
                             responsive: true,
                             maintainAspectRatio: false,
                             plugins: {
                                 legend: { display: false },
                                 tooltip: {
                                     callbacks: {
                                         label: function(context) {
                                             let value = context.raw;
                                             if ('{{ $metric }}' === 'revenue') {
                                                 return '£' + value.toLocaleString('en-GB', {minimumFractionDigits: 2});
                                             }
                                             return value.toLocaleString();
                                         }
                                     }
                                 }
                             },
                             scales: {
                                 y: {
                                     beginAtZero: true,
                                     ticks: {
                                         callback: function(value) {
                                             if ('{{ $metric }}' === 'revenue') {
                                                 return '£' + value.toLocaleString();
                                             }
                                             return value;
                                         }
                                     }
                                 }
                             }
                         }
                     });
                 }
             }">
            <canvas x-ref="canvas"></canvas>
        </div>
    @else
        <div class="flex flex-col items-center justify-center h-64 text-zinc-400">
            <flux:icon name="chart-bar" class="size-12 mb-2" />
            <p>No data available for this period</p>
        </div>
    @endif
</div>
