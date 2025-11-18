/**
 * Daily Revenue Chart - Alpine.js Component
 *
 * Bar chart showing orders, revenue, or items sold
 * Uses Chart.js for rendering
 */
Alpine.data('dailyRevenueChart', (initialData, initialOptions) => ({
    chart: null,
    data: initialData,
    options: initialOptions,
    loading: true,

    init() {
        if (!this.data || !this.data.labels || this.data.labels.length === 0) {
            console.log('DailyRevenueChart: No data available');
            this.loading = false;
            return;
        }

        this.chart = new Chart(this.$refs.canvas, {
            type: 'bar',
            data: this.data,
            options: this.options
        });

        this.loading = false;

        // Watch for data updates from Livewire
        this.$watch('data', (newData) => {
            if (this.chart && newData) {
                this.chart.data = newData;
                this.chart.update('none'); // Update without animation on data change
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
