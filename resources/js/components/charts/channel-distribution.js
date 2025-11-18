/**
 * Channel Distribution Chart - Alpine.js Component
 *
 * Doughnut chart showing revenue distribution by channel
 * Uses Chart.js for rendering
 *
 * Handles client-side view mode transformations:
 * - 'detailed': Show all subsources (e.g., "FBA (AMAZON)", "FBM (AMAZON)")
 * - 'grouped': Aggregate by channel (e.g., "AMAZON")
 */
Alpine.data('channelDistributionChart', (data, options, viewMode) => ({
    chart: null,
    data,
    options,
    viewMode,
    loading: true,

    init() {
        // Transform data based on view mode before creating chart
        const transformedData = this.transformDataForViewMode(this.data, this.viewMode);

        if (!transformedData || !transformedData.labels || transformedData.labels.length === 0) {
            console.log('ChannelDistributionChart: No data available');
            this.loading = false;
            return;
        }

        // Deep clone to remove Livewire's reactive proxies before passing to Chart.js
        const chartData = JSON.parse(JSON.stringify(transformedData));

        this.chart = new Chart(this.$refs.canvas, {
            type: 'doughnut',
            data: chartData,
            options: this.options
        });

        this.loading = false;

        // Watch for data changes (from Livewire - happens when filters change)
        this.$watch('data', (newData) => {
            if (this.chart && newData) {
                const transformed = this.transformDataForViewMode(newData, this.viewMode);
                if (transformed && transformed.labels && transformed.labels.length > 0) {
                    // Deep clone to strip Livewire proxies
                    const cleanData = JSON.parse(JSON.stringify(transformed));
                    this.chart.data.labels = cleanData.labels;
                    this.chart.data.datasets[0].data = cleanData.datasets[0].data;
                    this.chart.data.datasets[0].backgroundColor = cleanData.datasets[0].backgroundColor;
                    this.chart.update('none');
                }
            }
        });

        // Watch for view mode changes (detailed <-> grouped)
        this.$watch('viewMode', (newMode) => {
            if (this.chart && this.data) {
                const transformed = this.transformDataForViewMode(this.data, newMode);
                if (transformed && transformed.labels && transformed.labels.length > 0) {
                    // Deep clone to strip Livewire proxies
                    const cleanData = JSON.parse(JSON.stringify(transformed));
                    this.chart.data.labels = cleanData.labels;
                    this.chart.data.datasets[0].data = cleanData.datasets[0].data;
                    this.chart.data.datasets[0].backgroundColor = cleanData.datasets[0].backgroundColor;
                    this.chart.update('active'); // Animate the transition!
                }
            }
        });
    },

    /**
     * Transform data based on view mode
     * - 'detailed': Show all subsources (e.g., "FBA (AMAZON)", "FBM (AMAZON)")
     * - 'grouped': Aggregate by channel (e.g., "AMAZON")
     */
    transformDataForViewMode(rawData, mode) {
        if (!rawData || !rawData.labels || rawData.labels.length === 0) {
            return rawData;
        }

        if (mode === 'detailed') {
            return rawData; // Return as-is for detailed view
        }

        // Grouped view: Extract channel from "Subsource (CHANNEL)" format
        const grouped = {};

        rawData.labels.forEach((label, index) => {
            // Extract channel from parentheses, or use full label if no parentheses
            let channel = label;
            const match = label.match(/\(([^)]+)\)$/);
            if (match) {
                channel = match[1]; // Extract "AMAZON" from "FBA (AMAZON)"
            }

            if (!grouped[channel]) {
                grouped[channel] = {
                    value: 0,
                    color: rawData.datasets[0].backgroundColor[index] || '#3B82F6'
                };
            }

            grouped[channel].value += rawData.datasets[0].data[index];
        });

        // Rebuild chart data structure
        return {
            labels: Object.keys(grouped),
            datasets: [{
                label: 'Revenue by Channel',
                data: Object.values(grouped).map(g => g.value),
                backgroundColor: Object.values(grouped).map(g => g.color),
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
