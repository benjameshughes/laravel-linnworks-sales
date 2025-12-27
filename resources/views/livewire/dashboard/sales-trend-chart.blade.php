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

    {{-- Always render canvas - never swap DOM elements --}}
    <div
        x-data
        x-init="
            const canvas = $refs.canvas;
            const data = @js($chartData);
            const options = {
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
            };

            console.log('SalesTrendChart x-init running', { hasExistingChart: !!canvas._chart, dataLabels: data.labels?.length });

            if (data.labels && data.labels.length > 0) {
                if (canvas._chart) {
                    canvas._chart.destroy();
                }
                canvas._chart = new Chart(canvas, { type: 'line', data: data, options: options });
            }
        "
        class="h-64 relative"
    >
        <canvas x-ref="canvas"></canvas>
        @if(empty($chartData['labels']))
            <div class="absolute inset-0 flex items-center justify-center text-zinc-500 dark:text-zinc-400">
                <p class="text-sm">No data available</p>
            </div>
        @endif
    </div>
</div>
