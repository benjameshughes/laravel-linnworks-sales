<div class="bg-white dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700 p-3">
    <div class="flex items-center justify-between mb-2">
        <span class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Trend</span>
        <div class="flex items-center gap-1">
            {{-- Metric Toggle --}}
            <flux:button.group>
                <flux:button
                    wire:click="setMetric('revenue')"
                    variant="{{ $metric === 'revenue' ? 'filled' : 'ghost' }}"
                    size="xs"
                >
                    Revenue
                </flux:button>
                <flux:button
                    wire:click="setMetric('orders')"
                    variant="{{ $metric === 'orders' ? 'filled' : 'ghost' }}"
                    size="xs"
                >
                    Orders
                </flux:button>
                <flux:button
                    wire:click="setMetric('items')"
                    variant="{{ $metric === 'items' ? 'filled' : 'ghost' }}"
                    size="xs"
                >
                    Items
                </flux:button>
            </flux:button.group>

            {{-- Chart Type Toggle --}}
            <flux:button
                wire:click="toggleChartType"
                variant="ghost"
                size="xs"
                icon="{{ $chartType === 'line' ? 'chart-bar' : 'presentation-chart-line' }}"
                class="ml-1"
            />
        </div>
    </div>

    @if($this->chartData->isNotEmpty())
        <div class="h-40"
             wire:key="orders-chart-{{ $period }}-{{ $metric }}-{{ $chartType }}"
             wire:ignore
             x-init="
                 const ctx = $refs.canvas.getContext('2d');
                 new Chart(ctx, {
                     type: '{{ $chartType }}',
                     data: {
                         labels: {{ Js::from($this->chartLabels) }},
                         datasets: [{
                             label: '{{ ucfirst($metric) }}',
                             data: {{ Js::from($this->chartValues) }},
                             borderColor: 'rgb(113, 113, 122)',
                             backgroundColor: '{{ $chartType === 'line' ? 'rgba(113, 113, 122, 0.1)' : 'rgba(113, 113, 122, 0.6)' }}',
                             borderWidth: 2,
                             fill: {{ $chartType === 'line' ? 'true' : 'false' }},
                             tension: 0.4
                         }]
                     },
                     options: {
                         responsive: true,
                         maintainAspectRatio: false,
                         plugins: {
                             legend: { display: false }
                         },
                         scales: {
                             x: {
                                 grid: { display: false },
                                 ticks: { font: { size: 10 }, color: '#71717a' }
                             },
                             y: {
                                 beginAtZero: true,
                                 grid: { color: 'rgba(113, 113, 122, 0.1)' },
                                 ticks: { font: { size: 10 }, color: '#71717a' }
                             }
                         }
                     }
                 });
             ">
            <canvas x-ref="canvas"></canvas>
        </div>
    @else
        <div class="flex flex-col items-center justify-center h-40 text-zinc-400">
            <flux:icon name="chart-bar" class="size-8 mb-2" />
            <p class="text-sm">No data for this period</p>
        </div>
    @endif
</div>
