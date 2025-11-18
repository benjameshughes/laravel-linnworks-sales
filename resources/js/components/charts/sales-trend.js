/**
 * Sales Trend Chart - Alpine.js Component
 *
 * Line chart (area) showing revenue or orders trend
 * Uses Chart.js for rendering
 */
Alpine.data('salesTrendChart', (initialData, initialOptions) => ({
    chart: null,
    data: initialData,
    options: initialOptions,
    loading: true,

    init() {
        if (!this.data || !this.data.labels || this.data.labels.length === 0) {
            console.log('SalesTrendChart: No data available');
            this.loading = false;
            return;
        }

        this.chart = new Chart(this.$refs.canvas, {
            type: 'line',
            data: this.data,
            options: this.options
        });

        this.loading = false;

        this.$watch('data', (newData) => {
            if (this.chart && newData) {
                this.chart.data = newData;
                this.chart.update('none');
            }
        });
    },

    destroy() {
        if (this.chart) {
            this.chart.destroy();
            this.chart = null;
        }
    }
}));
