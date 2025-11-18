/**
 * Channel Distribution Chart - Alpine.js Component
 *
 * Takes raw top_channels data and transforms it for Chart.js
 */
Alpine.data('channelDistributionChart', (rawChannelsData, options) => ({
    chart: null,
    loading: true,

    init() {
        if (!rawChannelsData || rawChannelsData.length === 0) {
            console.log('ChannelDistributionChart: No data available');
            this.loading = false;
            return;
        }

        // Transform raw channels data to Chart.js format
        const chartData = this.transformToChartFormat(rawChannelsData);

        this.chart = new Chart(this.$refs.canvas, {
            type: 'doughnut',
            data: chartData,
            options: options
        });

        this.loading = false;
    },

    /**
     * Transform raw channels array to Chart.js doughnut format
     * Input: [{source: 'Amazon', revenue: 28732.76}, {source: 'eBay', revenue: 20156.42}]
     * Output: {labels: ['Amazon', 'eBay'], datasets: [{data: [28732.76, 20156.42], backgroundColor: [...]}]}
     */
    transformToChartFormat(channels) {
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

    destroy() {
        if (this.chart) {
            this.chart.destroy();
            this.chart = null;
        }
    }
}));
