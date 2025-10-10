// Unified Chart Widget Component
Alpine.data('chartWidget', (type, data, options, chartId) => ({
    chart: null,
    type: type,
    data: data,
    options: options,
    chartId: chartId,

    initChart() {
        const ctx = this.$refs.canvas.getContext('2d');

        // Merge default options with provided options
        const defaultOptions = this.getDefaultOptions();
        const mergedOptions = this.deepMerge(defaultOptions, options || {});

        // Process area chart data if needed
        let processedData = { ...data };
        if (type === 'area' && processedData.datasets) {
            processedData.datasets = processedData.datasets.map(dataset => ({
                ...dataset,
                fill: dataset.fill ?? 'start',
                backgroundColor: dataset.backgroundColor ?? this.hexToRgba(dataset.borderColor || '#3B82F6', 0.1)
            }));
        }

        // Create chart
        const chartType = type === 'area' ? 'line' : type;
        this.chart = new Chart(ctx, {
            type: chartType,
            data: processedData,
            options: mergedOptions
        });
    },

    getDefaultOptions() {
        return {
            responsive: true,
            maintainAspectRatio: false,
            animation: {
                duration: 300 // Faster animations
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
            scales: this.type === 'doughnut' || this.type === 'pie' ? undefined : {
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
            }
        };
    },

    deepMerge(target, source) {
        const output = { ...target };
        if (this.isObject(target) && this.isObject(source)) {
            Object.keys(source).forEach(key => {
                if (this.isObject(source[key])) {
                    if (!(key in target)) {
                        Object.assign(output, { [key]: source[key] });
                    } else {
                        output[key] = this.deepMerge(target[key], source[key]);
                    }
                } else {
                    Object.assign(output, { [key]: source[key] });
                }
            });
        }
        return output;
    },

    isObject(item) {
        return item && typeof item === 'object' && !Array.isArray(item);
    },

    hexToRgba(hex, alpha) {
        if (!hex) return `rgba(59, 130, 246, ${alpha})`;
        hex = hex.replace('#', '');

        if (hex.length === 3) {
            const r = parseInt(hex[0] + hex[0], 16);
            const g = parseInt(hex[1] + hex[1], 16);
            const b = parseInt(hex[2] + hex[2], 16);
            return `rgba(${r}, ${g}, ${b}, ${alpha})`;
        }

        const r = parseInt(hex.substring(0, 2), 16);
        const g = parseInt(hex.substring(2, 4), 16);
        const b = parseInt(hex.substring(4, 6), 16);
        return `rgba(${r}, ${g}, ${b}, ${alpha})`;
    }
}));

// Legacy Base Chart Component (kept for backwards compatibility)
Alpine.data('baseChart', (config, chartId) => ({
    chart: null,
    config: config,
    chartId: chartId,

    initChart() {
        const ctx = this.$refs.canvas.getContext('2d');
        this.processOptions(this.config.options);
        this.chart = new Chart(ctx, this.config);

        Livewire.on('chart-update-' + this.chartId, (data) => {
            this.updateChart(data[0]);
        });
    },

    processOptions(options) {
        for (const key in options) {
            if (typeof options[key] === 'object' && options[key] !== null) {
                this.processOptions(options[key]);
            } else if (typeof options[key] === 'string' && options[key].startsWith('function')) {
                try {
                    options[key] = eval('(' + options[key] + ')');
                } catch (e) {
                    console.error('Error parsing function:', e);
                }
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
