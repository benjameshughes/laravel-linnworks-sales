<div 
    x-data="chartComponent(@js($this->getChartData()), @js($chartId))"
    x-init="initChart()"
    wire:ignore
    class="relative"
    style="height: {{ $height }}; width: {{ $width }};"
>
    <canvas x-ref="canvas"></canvas>
</div>

@assets
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns@3.0.0/dist/chartjs-adapter-date-fns.bundle.min.js"></script>
@endassets

@script
<script>
Alpine.data('chartComponent', (chartConfig, chartId) => ({
    chart: null,
    config: chartConfig,
    
    initChart() {
        // Wait for canvas to be available
        this.$nextTick(() => {
            const ctx = this.$refs.canvas.getContext('2d');
            
            // Process options to handle function strings
            this.processOptions(this.config.options);
            
            this.chart = new Chart(ctx, this.config);
            
            // Listen for Livewire updates
            Livewire.on('chart-update-' + chartId, (data) => {
                this.updateChart(data[0]);
            });
        });
    },
    
    processOptions(options) {
        // Convert function strings to actual functions
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
        
        // Update data
        if (newData.data) {
            this.chart.data = newData.data;
        }
        
        // Update options if provided
        if (newData.options) {
            this.processOptions(newData.options);
            this.chart.options = { ...this.chart.options, ...newData.options };
        }
        
        // Update the chart
        this.chart.update('active');
    },
    
    destroy() {
        if (this.chart) {
            this.chart.destroy();
        }
    }
}));
</script>
@endscript