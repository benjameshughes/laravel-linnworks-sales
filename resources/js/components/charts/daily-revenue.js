/**
 * Daily Revenue Chart - Alpine.js Component
 *
 * Bar chart showing orders, revenue, or items sold
 * Uses Chart.js for rendering
 *
 * Accepts raw daily breakdown data and formats it for Chart.js
 */
Alpine.data('dailyRevenueChart', (dailyBreakdown, viewMode) => ({
    chart: null,
    dailyBreakdown,
    viewMode,
    loading: true,

    init() {
        if (!this.dailyBreakdown || this.dailyBreakdown.length === 0) {
            console.log('DailyRevenueChart: No data available');
            this.loading = false;
            return;
        }

        const chartData = this.formatForChartJs(this.dailyBreakdown, this.viewMode);

        this.chart = new Chart(this.$refs.canvas, {
            type: 'bar',
            data: chartData,
            options: this.getChartOptions()
        });

        this.loading = false;

        // Watch for data changes (from Livewire)
        this.$watch('dailyBreakdown', (newBreakdown) => {
            if (this.chart && newBreakdown && newBreakdown.length > 0) {
                const newData = this.formatForChartJs(newBreakdown, this.viewMode);
                // Update data in place (don't replace the object)
                this.chart.data.labels = newData.labels;
                // Handle multiple datasets (orders_revenue mode has 2 datasets)
                newData.datasets.forEach((newDataset, index) => {
                    if (this.chart.data.datasets[index]) {
                        this.chart.data.datasets[index].data = newDataset.data;
                        this.chart.data.datasets[index].label = newDataset.label;
                        this.chart.data.datasets[index].backgroundColor = newDataset.backgroundColor;
                        this.chart.data.datasets[index].borderColor = newDataset.borderColor;
                    }
                });
                this.chart.update('none'); // Update without animation on data change
            }
        });

        // Watch for view mode changes (orders_revenue <-> items)
        this.$watch('viewMode', (newMode) => {
            if (this.chart && this.dailyBreakdown && this.dailyBreakdown.length > 0) {
                const newData = this.formatForChartJs(this.dailyBreakdown, newMode);

                // Handle dataset count changes (1 dataset vs 2 datasets)
                // When switching between 'items' (1 dataset) and 'orders_revenue' (2 datasets)
                if (this.chart.data.datasets.length !== newData.datasets.length) {
                    // Need to recreate when dataset count changes
                    this.chart.destroy();
                    this.chart = new Chart(this.$refs.canvas, {
                        type: 'bar',
                        data: newData,
                        options: this.getChartOptions()
                    });
                } else {
                    // Same dataset count - update in place for animations
                    this.chart.data.labels = newData.labels;
                    newData.datasets.forEach((newDataset, index) => {
                        this.chart.data.datasets[index].data = newDataset.data;
                        this.chart.data.datasets[index].label = newDataset.label;
                        this.chart.data.datasets[index].backgroundColor = newDataset.backgroundColor;
                        this.chart.data.datasets[index].borderColor = newDataset.borderColor;
                    });
                    this.chart.update('active'); // Animate the transition!
                }
            }
        });
    },

    /**
     * Transform raw daily breakdown into Chart.js format
     */
    formatForChartJs(breakdown, mode) {
        const labels = breakdown.map(d => d.date);

        if (mode === 'orders_revenue') {
            return {
                labels: labels,
                datasets: [
                    {
                        label: 'Orders',
                        data: breakdown.map(d => d.orders),
                        borderColor: 'rgb(59, 130, 246)',
                        backgroundColor: 'rgba(59, 130, 246, 0.8)',
                        type: 'bar',
                    },
                    {
                        label: 'Revenue',
                        data: breakdown.map(d => d.revenue),
                        borderColor: 'rgb(34, 197, 94)',
                        backgroundColor: 'rgba(34, 197, 94, 0.8)',
                        type: 'bar',
                    },
                ]
            };
        } else {
            // items view
            return {
                labels: labels,
                datasets: [
                    {
                        label: 'Items Sold',
                        data: breakdown.map(d => d.items),
                        borderColor: 'rgb(168, 85, 247)',
                        backgroundColor: 'rgba(168, 85, 247, 0.8)',
                    },
                ]
            };
        }
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
                    grid: {
                        display: true,
                        color: 'rgba(0, 0, 0, 0.05)',
                    },
                },
                x: {
                    grid: {
                        display: false,
                    },
                },
            },
            elements: {
                bar: {
                    borderRadius: 4,
                    borderWidth: 0,
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
