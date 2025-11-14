// Base Chart Component
Alpine.data('baseChart', () => ({
    chart: null,
    config: null,
    chartId: null,
    livewireListener: null,

    init() {
        // Register Livewire event listener ONCE when Alpine component initializes
        // This prevents memory leaks and duplicate listeners
        this.livewireListener = Livewire.on('chart-update-' + this.chartId, (eventData) => {
            console.log('[Alpine Chart] Received chart-update event', {
                chartId: this.chartId,
                hasChart: !!this.chart,
                hasEventData: !!eventData[0]
            });

            const newConfig = eventData[0];
            if (!newConfig) return;

            const ctx = this.$refs.canvas?.getContext('2d');
            if (!ctx) return;

            // If chart doesn't exist and we have data, create it
            if (!this.chart && newConfig.data?.labels?.length > 0) {
                console.log('[Alpine Chart] Creating chart from update event');
                this.config = newConfig;
                this.processOptions(this.config.options);
                try {
                    this.chart = new Chart(ctx, this.config);
                } catch (error) {
                    console.error('[Alpine Chart] Chart creation failed:', error);
                }
                return;
            }

            // If chart exists, update it
            if (this.chart) {
                console.log('[Alpine Chart] Updating existing chart');
                this.updateChart(newConfig);
            }
        });
    },

    destroy() {
        // Alpine lifecycle hook - cleanup chart AND event listener
        if (this.chart) {
            this.chart.destroy();
            this.chart = null;
        }
        if (this.livewireListener) {
            this.livewireListener();
            this.livewireListener = null;
        }
    },

    initChart(config, chartId) {
        this.config = config;
        this.chartId = chartId;

        const ctx = this.$refs.canvas?.getContext('2d');
        if (!ctx) {
            console.warn('[Alpine Chart] Canvas context not available');
            return;
        }

        console.log('[Alpine Chart] initChart() called', {
            chartId: this.chartId,
            hasData: !!this.config.data,
            hasLabels: this.config.data?.labels?.length > 0,
            labelsCount: this.config.data?.labels?.length || 0,
            datasetsCount: this.config.data?.datasets?.length || 0
        });

        // Destroy existing chart if present (handles re-initialization)
        if (this.chart) {
            console.log('[Alpine Chart] Destroying existing chart before re-init');
            this.chart.destroy();
            this.chart = null;
        }

        // Only initialize if we have data
        if (this.config.data?.labels?.length > 0) {
            console.log('[Alpine Chart] Creating chart with data');
            this.processOptions(this.config.options);
            try {
                this.chart = new Chart(ctx, this.config);
            } catch (error) {
                console.error('[Alpine Chart] Chart creation failed:', error);
            }
        } else {
            console.log('[Alpine Chart] No data provided, skipping chart creation');
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
    }
}));
