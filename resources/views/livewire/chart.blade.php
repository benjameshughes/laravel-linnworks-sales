<div
    wire:key="{{ $chartId }}"
    x-data="{
        chart: null,

        init() {
            const config = @js($this->getChartData());
            if (!config.data?.labels?.length) {
                console.log('[Chart] No data provided, skipping chart creation');
                return;
            }

            console.log('[Chart] Creating chart', {
                chartId: @js($chartId),
                labelsCount: config.data.labels.length,
                datasetsCount: config.data.datasets.length
            });

            this.processOptions(config.options);

            try {
                this.chart = new Chart(this.$refs.canvas, config);
            } catch (error) {
                console.error('[Chart] Creation failed:', error);
            }
        },

        destroy() {
            if (this.chart) {
                console.log('[Chart] Destroying chart');
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
