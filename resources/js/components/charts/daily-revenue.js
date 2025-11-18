/**
 * Daily Revenue Chart - Alpine.js Component
 *
 * Bar chart showing orders, revenue, or items sold
 * Uses Chart.js for rendering
 *
 * Accepts raw daily breakdown data and formats it for Chart.js
 */
Alpine.data('dailyRevenueChart', (initialBreakdown, initialViewMode) => ({
    chart: null,
    dailyBreakdown: initialBreakdown,
    viewMode: initialViewMode,
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
                // Destroy and recreate chart with new data
                this.chart.destroy();
                this.chart = new Chart(this.$refs.canvas, {
                    type: 'bar',
                    data: this.formatForChartJs(newBreakdown, this.viewMode),
                    options: this.getChartOptions()
                });
            }
        });

        // Watch for view mode changes (orders_revenue <-> items)
        this.$watch('viewMode', (newMode) => {
            if (this.chart && this.dailyBreakdown && this.dailyBreakdown.length > 0) {
                // Destroy and recreate chart with new view mode
                this.chart.destroy();
                this.chart = new Chart(this.$refs.canvas, {
                    type: 'bar',
                    data: this.formatForChartJs(this.dailyBreakdown, newMode),
                    options: this.getChartOptions()
                });
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
