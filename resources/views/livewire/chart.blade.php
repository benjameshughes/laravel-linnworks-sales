<div
    wire:ignore
    wire:key="{{ $chartId }}"
    x-data="{
        chart: null,

        init() {
            const config = @js($this->getChartData());
            if (!config.data?.labels?.length) {
                console.log('[Chart] No initial data, skipping chart creation');
                return;
            }

            console.log('[Chart] Creating chart', {
                chartId: @js($chartId),
                labelsCount: config.data.labels.length,
                datasetsCount: config.data.datasets.length
            });

            // Deep clone config to prevent reference issues
            const chartConfig = JSON.parse(JSON.stringify(config));
            this.processOptions(chartConfig.options);

            // Disable animations on initial creation to prevent race conditions
            if (!chartConfig.options) chartConfig.options = {};
            if (!chartConfig.options.animation) chartConfig.options.animation = {};
            chartConfig.options.animation.duration = 0;

            try {
                this.chart = new Chart(this.$refs.canvas, chartConfig);
            } catch (error) {
                console.error('[Chart] Creation failed:', error, config);
            }

            // Watch for data changes from Livewire
            this.$wire.$watch('data', (newData) => {
                console.log('[Chart] Data updated via $wire.$watch', newData);
                this.updateChart(newData);
            });
        },

        updateChart(newData) {
            if (!this.chart) {
                console.warn('[Chart] Cannot update - chart not initialized');
                return;
            }

            if (!newData?.labels?.length) {
                console.log('[Chart] Empty data received, skipping update');
                return;
            }

            console.log('[Chart] Updating chart with new data', {
                labelsCount: newData.labels.length,
                datasetsCount: newData.datasets.length
            });

            // Update chart data
            this.chart.data.labels = newData.labels;
            this.chart.data.datasets = newData.datasets;

            // Update without animation for smooth transitions
            this.chart.update('none');
        },

        destroy() {
            if (this.chart) {
                console.log('[Chart] Destroying chart');
                // Stop any pending animations before destroying
                this.chart.stop();
                this.chart.destroy();
                this.chart = null;
            }
        },

        processOptions(options) {
            const callbacks = {
                '__DOUGHNUT_LABEL_CALLBACK__': function(context) {
                    let label = context.label || '';
                    if (label) label += ': ';
                    const value = context.parsed;
                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                    const percentage = ((value / total) * 100).toFixed(1);
                    return label + percentage + '%';
                },
                '__PIE_LABEL_CALLBACK__': function(context) {
                    let label = context.label || '';
                    if (label) label += ': ';
                    const value = context.parsed;
                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                    const percentage = ((value / total) * 100).toFixed(1);
                    return label + percentage + '%';
                }
            };

            for (const key in options) {
                if (typeof options[key] === 'object' && options[key] !== null) {
                    this.processOptions(options[key]);
                } else if (typeof options[key] === 'string' && callbacks[options[key]]) {
                    options[key] = callbacks[options[key]];
                }
            }
        }
    }"
    style="height: {{ $height }}; width: {{ $width }};"
>
    <canvas x-ref="canvas"></canvas>
</div>
