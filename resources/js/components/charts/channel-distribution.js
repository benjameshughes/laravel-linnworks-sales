/**
 * Channel Distribution Chart - Alpine.js Component
 *
 * Doughnut chart showing revenue distribution by channel
 * Uses Chart.js for rendering
 */
Alpine.data('channelDistributionChart', (initialData, initialOptions) => ({
    chart: null,
    data: initialData,
    options: initialOptions,
    loading: true,

    init() {
        if (!this.data || !this.data.labels || this.data.labels.length === 0) {
            console.log('ChannelDistributionChart: No data available');
            this.loading = false;
            return;
        }

        this.chart = new Chart(this.$refs.canvas, {
            type: 'doughnut',
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
