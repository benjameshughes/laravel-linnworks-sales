<div class="min-h-screen" x-data="{ showCharts: true, showMetrics: true }">
    <div class="space-y-6 p-6">
        {{-- Filters Island - NOT lazy loaded (user needs this immediately) --}}
        <livewire:dashboard.dashboard-filters />

        {{-- Metrics Summary Island - NOT lazy loaded (animated ticker deserves immediate glory) --}}
        <livewire:dashboard.metrics-summary />

        {{-- Charts Section - Lazy loaded for parallel execution --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            <livewire:dashboard.sales-trend-chart lazy />
            <livewire:dashboard.channel-distribution-chart lazy />
        </div>

        {{-- Daily Revenue Chart - Lazy loaded for parallel execution --}}
        <livewire:dashboard.daily-revenue-chart lazy />

        {{-- Analytics Grid - Lazy loaded for parallel execution --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <livewire:dashboard.top-products lazy />
            <livewire:dashboard.top-channels lazy />
        </div>

        {{-- Recent Orders Table --}}
        <div class="flex items-center justify-end gap-2 mb-4">
            <flux:button
                variant="ghost"
                size="sm"
                @click="showMetrics = !showMetrics"
                x-bind:icon="showMetrics ? 'eye-slash' : 'eye'"
            >
                <span x-text="showMetrics ? 'Hide' : 'Show'"></span> Metrics
            </flux:button>

            <flux:button
                variant="ghost"
                size="sm"
                @click="showCharts = !showCharts"
                x-bind:icon="showCharts ? 'eye-slash' : 'chart-bar'"
            >
                <span x-text="showCharts ? 'Hide' : 'Show'"></span> Charts
            </flux:button>
        </div>

        {{-- Recent Orders - Lazy loaded for parallel execution --}}
        <livewire:dashboard.recent-orders lazy />
    </div>
</div>