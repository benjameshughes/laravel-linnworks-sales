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

        const formattedData = this.formatForChartJs(this.dailyBreakdown, this.viewMode);
        // Deep clone to strip Livewire proxies
        const chartData = JSON.parse(JSON.stringify(formattedData));

        // Store chart as raw object to prevent Alpine reactivity
        const chartInstance = new Chart(this.$refs.canvas, {
            type: 'bar',
            data: chartData,
            options: this.getChartOptions()
        });
        this.chart = Alpine.raw(chartInstance);

        this.loading = false;

        // Watch for data changes (from Livewire)
        this.$watch('dailyBreakdown', (newBreakdown) => {
            if (this.chart && newBreakdown && newBreakdown.length > 0) {
                const newData = this.formatForChartJs(newBreakdown, this.viewMode);
                const cleanData = JSON.parse(JSON.stringify(newData)); // Strip proxies

                // Handle dataset count changes (filter might change data structure)
                if (this.chart.data.datasets.length !== cleanData.datasets.length) {
                    // Recreate chart when dataset count changes
                    this.chart.destroy();
                    const newChartInstance = new Chart(this.$refs.canvas, {
                        type: 'bar',
                        data: cleanData,
                        options: this.getChartOptions()
                    });
                    this.chart = Alpine.raw(newChartInstance);
                } else {
                    // Replace entire data object (chart is raw, so data won't be wrapped in proxy)
                    this.chart.data = cleanData;
                    this.chart.update('active'); // Animate filter changes
                }
            }
        });

        // Watch for view mode changes (orders_revenue <-> items)
        this.$watch('viewMode', (newMode) => {
            if (this.chart && this.dailyBreakdown && this.dailyBreakdown.length > 0) {
                const newData = this.formatForChartJs(this.dailyBreakdown, newMode);
                const cleanData = JSON.parse(JSON.stringify(newData)); // Strip proxies

                // Handle dataset count changes (1 dataset vs 2 datasets)
                // When switching between 'items' (1 dataset) and 'orders_revenue' (2 datasets)
                if (this.chart.data.datasets.length !== cleanData.datasets.length) {
                    // Need to recreate when dataset count changes
                    this.chart.destroy();
                    const newChartInstance = new Chart(this.$refs.canvas, {
                        type: 'bar',
                        data: cleanData,
                        options: this.getChartOptions()
                    });
                    this.chart = Alpine.raw(newChartInstance);
                } else {
                    // Replace entire data object (chart is raw, so data won't be wrapped in proxy)
                    this.chart.data = cleanData;
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
