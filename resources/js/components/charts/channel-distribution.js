/**
 * Channel Distribution Chart - Alpine.js Component
 *
 * Doughnut chart showing revenue distribution by channel
 * Uses Chart.js for rendering
 *
 * Note: Channel data is pre-formatted by PHP (already in Chart.js format)
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

        // Watch for data changes (from Livewire - happens when viewMode changes)
        this.$watch('data', (newData) => {
            if (this.chart && newData && newData.labels && newData.labels.length > 0) {
                this.chart.data = newData;
                this.chart.update('active'); // Animate view mode transitions!
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
