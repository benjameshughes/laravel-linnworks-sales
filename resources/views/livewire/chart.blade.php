<div
    wire:ignore
    x-data="{
        chart: null,
        wireId: @js($this->getId()),

        init() {
            this.createChart();

            // Listen for Livewire updates on THIS component only
            window.addEventListener('livewire:commit', (event) => {
                // Only react to updates for THIS specific Livewire component
                if (event.detail?.component?.id === this.wireId) {
                    this.$nextTick(() => {
                        this.updateChart();
                    });
                }
            });
        },

        createChart() {
            const config = @js($this->getChartData());

            if (!config.data?.labels?.length) {
                return;
            }

            // Create chart only if it doesn't exist
            if (!this.chart) {
                this.chart = new Chart(this.$refs.canvas, config);
            }
        },

        updateChart() {
            const config = @js($this->getChartData());

            if (!config.data?.labels?.length) {
                return;
            }

            // If chart exists, UPDATE it instead of recreating
            if (this.chart) {
                // Update data
                this.chart.data = config.data;

                // Update options if they changed
                if (config.options) {
                    this.chart.options = config.options;
                }

                // Tell Chart.js to re-render with new data
                this.chart.update();
            } else {
                // Chart doesn't exist yet, create it
                this.chart = new Chart(this.$refs.canvas, config);
            }
        },

        destroy() {
            if (this.chart) {
                this.chart.destroy();
                this.chart = null;
            }
        }
    }"
    style="height: {{ $height }}; width: {{ $width }};"
>
    <canvas x-ref="canvas"></canvas>
</div>
