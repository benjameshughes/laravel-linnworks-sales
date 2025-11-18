/**
 * Channel Distribution Chart - Alpine.js Component
 *
 * Doughnut chart showing revenue distribution by channel
 * Uses Chart.js for rendering
 *
 * Accepts raw channel data and formats it for Chart.js
 */
Alpine.data('channelDistributionChart', (channelData, viewMode) => ({
    chart: null,
    channelData,
    viewMode,
    loading: true,

    init() {
        if (!this.channelData || this.channelData.length === 0) {
            console.log('ChannelDistributionChart: No data available');
            this.loading = false;
            return;
        }

        const formattedData = this.formatForChartJs(this.channelData, this.viewMode);
        // Deep clone to strip Livewire proxies
        const chartData = JSON.parse(JSON.stringify(formattedData));

        // Store chart as raw object to prevent Alpine reactivity
        const chartInstance = new Chart(this.$refs.canvas, {
            type: 'doughnut',
            data: chartData,
            options: this.getChartOptions()
        });
        this.chart = Alpine.raw(chartInstance);

        this.loading = false;

        // Watch for data changes (from Livewire)
        this.$watch('channelData', (newChannelData) => {
            if (this.chart && newChannelData && newChannelData.length > 0) {
                const newData = this.formatForChartJs(newChannelData, this.viewMode);
                const cleanData = JSON.parse(JSON.stringify(newData)); // Strip proxies

                // Destroy and recreate to avoid proxy issues
                this.chart.destroy();
                const newChartInstance = new Chart(this.$refs.canvas, {
                    type: 'doughnut',
                    data: cleanData,
                    options: this.getChartOptions()
                });
                this.chart = Alpine.raw(newChartInstance);
            }
        });

        // Watch for view mode changes (detailed <-> grouped)
        this.$watch('viewMode', (newMode) => {
            if (this.chart && this.channelData && this.channelData.length > 0) {
                const newData = this.formatForChartJs(this.channelData, newMode);
                const cleanData = JSON.parse(JSON.stringify(newData)); // Strip proxies

                // Destroy and recreate to avoid proxy issues
                this.chart.destroy();
                const newChartInstance = new Chart(this.$refs.canvas, {
                    type: 'doughnut',
                    data: cleanData,
                    options: this.getChartOptions()
                });
                this.chart = Alpine.raw(newChartInstance);
            }
        });
    },

    /**
     * Transform raw channel data into Chart.js doughnut format
     * Input: [{source: 'Amazon', revenue: 28732.76}, {source: 'eBay', revenue: 20156.42}]
     * Output: {labels: ['Amazon', 'eBay'], datasets: [{data: [28732.76, 20156.42], backgroundColor: [...]}]}
     */
    formatForChartJs(channels, mode) {
        const colors = ['#3B82F6', '#10B981', '#F59E0B', '#EF4444', '#8B5CF6', '#EC4899'];

        return {
            labels: channels.map(ch => ch.source),
            datasets: [{
                label: 'Revenue by Channel',
                data: channels.map(ch => ch.revenue),
                backgroundColor: channels.map((_, i) => colors[i % colors.length]),
                borderWidth: 2
            }]
        };
    },

    /**
     * Get Chart.js options with 3-second animations
     */
    getChartOptions() {
        return {
            responsive: true,
            maintainAspectRatio: false,
            animation: {
                duration: 3000  // 3-second animations!
            },
            cutout: '60%',
            plugins: {
                legend: {
                    display: true,
                    position: 'bottom',
                    labels: {
                        padding: 15,
                        usePointStyle: true,
                        font: {
                            size: 12,
                        },
                    },
                },
                tooltip: {
                    enabled: true,
                },
            },
        };
    },

    destroy() {
        if (this.chart) {
            this.chart.destroy();
            this.chart = null;
        }
    }
}));
