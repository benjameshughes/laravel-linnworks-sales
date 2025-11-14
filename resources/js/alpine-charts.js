// Base Chart Component
Alpine.data('baseChart', (config, chartId) => ({
    chart: null,
    config: config,
    chartId: chartId,

    initChart() {
        this.$nextTick(() => {
            const ctx = this.$refs.canvas.getContext('2d');

            console.log('[Alpine Chart] initChart() called', {
                chartId: this.chartId,
                hasData: this.config.data?.labels?.length > 0,
                labels: this.config.data?.labels,
                datasets: this.config.data?.datasets
            });

            // Only initialize if we have data
            if (this.config.data?.labels?.length > 0) {
                console.log('[Alpine Chart] Creating chart with initial data');
                this.processOptions(this.config.options);
                this.chart = new Chart(ctx, this.config);
            } else {
                console.log('[Alpine Chart] No data on init, waiting for update event');
            }

            // Register Livewire listener AFTER chart creation
            Livewire.on('chart-update-' + this.chartId, (data) => {
                console.log('[Alpine Chart] Received chart-update event', {
                    chartId: this.chartId,
                    hasChart: !!this.chart,
                    eventData: data[0]
                });

                const ctx = this.$refs.canvas?.getContext('2d');
                if (!ctx) {
                    console.warn('[Alpine Chart] Canvas ref not available');
                    return;
                }

                if (!this.chart && data[0]?.data?.labels?.length > 0) {
                    // First-time initialization with data (for delayed load)
                    console.log('[Alpine Chart] Creating chart from update event');
                    this.config = data[0];
                    this.processOptions(this.config.options);
                    this.chart = new Chart(ctx, this.config);
                } else if (this.chart) {
                    console.log('[Alpine Chart] Updating existing chart');
                    this.updateChart(data[0]);
                }
            });
        });
    },

    processOptions(options) {
        // Define reusable callback functions
        const callbacks = {
            '__DOUGHNUT_LABEL_CALLBACK__': function(context) {
                let label = context.label || '';
                if (label) {
                    label += ': ';
                }
                const value = context.parsed;
                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                const percentage = ((value / total) * 100).toFixed(1);
                label += percentage + '%';
                return label;
            },
            '__PIE_LABEL_CALLBACK__': function(context) {
                let label = context.label || '';
                if (label) {
                    label += ': ';
                }
                const value = context.parsed;
                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                const percentage = ((value / total) * 100).toFixed(1);
                label += percentage + '%';
                return label;
            }
        };

        for (const key in options) {
            if (typeof options[key] === 'object' && options[key] !== null) {
                this.processOptions(options[key]);
            } else if (typeof options[key] === 'string' && callbacks[options[key]]) {
                // Replace placeholder with actual function
                options[key] = callbacks[options[key]];
            }
        }
    },

    updateChart(newData) {
        if (!this.chart) return;

        if (newData.data) {
            this.chart.data = newData.data;
        }

        if (newData.options) {
            this.processOptions(newData.options);
            this.chart.options = { ...this.chart.options, ...newData.options };
        }

        this.chart.update('active');
    }
}));

// Channel Performance Chart Component
Alpine.data('channelChart', (chartData) => ({
    chart: null,

    createChart() {
        const ctx = this.$el.querySelector('canvas');
        if (!ctx) return;

        if (this.chart) this.chart.destroy();

        this.chart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: chartData.labels,
                datasets: [{
                    label: 'Revenue',
                    data: chartData.revenue,
                    backgroundColor: 'rgba(0, 165, 224, 0.8)',
                    borderColor: 'rgba(0, 165, 224, 1)',
                    borderWidth: 1,
                    yAxisID: 'y'
                }, {
                    label: 'Orders',
                    data: chartData.orders,
                    backgroundColor: 'rgba(202, 5, 77, 0.8)',
                    borderColor: 'rgba(202, 5, 77, 1)',
                    borderWidth: 1,
                    yAxisID: 'y1'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                plugins: {
                    legend: {
                        position: 'top',
                    },
                    title: {
                        display: true,
                        text: 'Channel Performance Comparison'
                    }
                },
                scales: {
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        title: {
                            display: true,
                            text: 'Revenue (£)'
                        },
                        ticks: {
                            callback: function(value) {
                                return '£' + value.toLocaleString();
                            }
                        }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        title: {
                            display: true,
                            text: 'Orders'
                        },
                        grid: {
                            drawOnChartArea: false,
                        }
                    }
                }
            }
        });
    }
}));

// Market Share Chart Component
Alpine.data('marketShareChart', (chartData) => ({
    chart: null,

    createChart() {
        const ctx = this.$el.querySelector('canvas');
        if (!ctx) return;

        if (this.chart) this.chart.destroy();

        this.chart = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: chartData.labels,
                datasets: [{
                    data: chartData.revenue,
                    backgroundColor: [
                        'rgba(0, 165, 224, 0.8)',
                        'rgba(202, 5, 77, 0.8)',
                        'rgba(168, 194, 86, 0.8)',
                        'rgba(115, 115, 115, 0.8)',
                        'rgba(64, 64, 64, 0.8)'
                    ],
                    borderColor: [
                        'rgba(0, 165, 224, 1)',
                        'rgba(202, 5, 77, 1)',
                        'rgba(168, 194, 86, 1)',
                        'rgba(115, 115, 115, 1)',
                        'rgba(64, 64, 64, 1)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                    },
                    title: {
                        display: true,
                        text: 'Channel Market Share'
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.parsed || 0;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = ((value / total) * 100).toFixed(1);
                                return label + ': £' + value.toLocaleString() + ' (' + percentage + '%)';
                            }
                        }
                    }
                }
            }
        });
    }
}));

// Channel Detail Chart Component
Alpine.data('channelDetailChart', (channelDetails) => ({
    chart: null,

    createChart() {
        const ctx = this.$el.querySelector('canvas');
        if (!ctx) return;

        const dailyData = channelDetails.daily_data || [];

        if (this.chart) this.chart.destroy();

        this.chart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: dailyData.map(item => item.date),
                datasets: [{
                    label: 'Revenue (£)',
                    data: dailyData.map(item => item.revenue),
                    borderColor: 'rgb(59, 130, 246)',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    tension: 0.4,
                    fill: true,
                    yAxisID: 'y'
                }, {
                    label: 'Orders',
                    data: dailyData.map(item => item.orders),
                    borderColor: 'rgb(16, 185, 129)',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    tension: 0.4,
                    yAxisID: 'y1'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'top' } },
                scales: {
                    y: {
                        beginAtZero: true,
                        position: 'left',
                        title: { display: true, text: 'Revenue (£)' },
                        ticks: { callback: function(value) { return '£' + value.toLocaleString(); } }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        title: { display: true, text: 'Orders' },
                        grid: { drawOnChartArea: false }
                    }
                }
            }
        });
    }
}));
