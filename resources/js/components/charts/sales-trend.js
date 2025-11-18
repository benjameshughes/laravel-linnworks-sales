/**
 * Sales Trend Chart - Alpine.js Component
 *
 * Line chart (area) showing revenue or orders trend
 * Uses Chart.js for rendering
 *
 * Accepts raw daily breakdown data and formats it for Chart.js
 */
Alpine.data('salesTrendChart', (dailyBreakdown, viewMode) => ({
    chart: null,
    dailyBreakdown,
    viewMode,
    loading: true,

    init() {
        if (!this.dailyBreakdown || this.dailyBreakdown.length === 0) {
            console.log('SalesTrendChart: No data available');
            this.loading = false;
            return;
        }

        const formattedData = this.formatForChartJs(this.dailyBreakdown, this.viewMode);
        // Deep clone to strip Livewire proxies
        const chartData = JSON.parse(JSON.stringify(formattedData));

        this.chart = new Chart(this.$refs.canvas, {
            type: 'line',
            data: chartData,
            options: this.getChartOptions()
        });

        this.loading = false;

        // Watch for data changes (from Livewire)
        this.$watch('dailyBreakdown', (newBreakdown) => {
            if (this.chart && newBreakdown && newBreakdown.length > 0) {
                const newData = this.formatForChartJs(newBreakdown, this.viewMode);
                const cleanData = JSON.parse(JSON.stringify(newData)); // Strip proxies
                this.chart.data.labels = cleanData.labels;
                this.chart.data.datasets[0].data = cleanData.datasets[0].data;
                this.chart.data.datasets[0].label = cleanData.datasets[0].label;
                this.chart.update('none'); // Update without animation on data change
            }
        });

        // Watch for view mode changes (revenue <-> orders)
        this.$watch('viewMode', (newMode) => {
            if (this.chart && this.dailyBreakdown && this.dailyBreakdown.length > 0) {
                const newData = this.formatForChartJs(this.dailyBreakdown, newMode);
                const cleanData = JSON.parse(JSON.stringify(newData)); // Strip proxies
                this.chart.data.labels = cleanData.labels;
                this.chart.data.datasets[0].data = cleanData.datasets[0].data;
                this.chart.data.datasets[0].label = cleanData.datasets[0].label;
                this.chart.update('active'); // Animate the transition!
            }
        });
    },

    /**
     * Transform raw daily breakdown into Chart.js format
     */
    formatForChartJs(breakdown, mode) {
        const labels = breakdown.map(d => d.date);
        const data = breakdown.map(d => d[mode]);

        return {
            labels: labels,
            datasets: [{
                label: mode === 'revenue' ? 'Revenue' : 'Orders',
                data: data,
                borderColor: 'rgb(59, 130, 246)',
                backgroundColor: 'rgba(59, 130, 246, 0.1)',
                fill: true,
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
            plugins: {
                legend: {
                    display: true,
                    position: 'top',
                },
                tooltip: {
                    enabled: true,
                    mode: 'index',
                    intersect: false,
                },
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grace: '10%',
                    grid: {
                        display: true,
                        color: 'rgba(0, 0, 0, 0.05)',
                    },
                    ticks: {
                        padding: 10,
                    },
                },
                x: {
                    grid: {
                        display: false,
                    },
                    offset: true,
                    ticks: {
                        padding: 10,
                        autoSkip: true,
                        maxRotation: 0,
                    },
                },
            },
            elements: {
                line: {
                    tension: 0.4,
                    borderWidth: 2,
                },
                point: {
                    radius: 4,
                    hoverRadius: 6,
                    hitRadius: 10,
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
