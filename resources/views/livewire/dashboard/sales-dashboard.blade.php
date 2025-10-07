<div class="min-h-screen" x-data="{ showCharts: true, showMetrics: true }">
    <div class="space-y-6 p-6">

        {{-- Filters Island - Non-lazy, always loads first --}}
        <livewire:dashboard.dashboard-filters />

        {{-- Metrics Summary Island - Loads in parallel --}}
        <div x-show="showMetrics" x-transition>
            <livewire:dashboard.metrics-summary lazy />
        </div>

        {{-- Charts Section --}}
        <div x-show="showCharts" x-transition>
            {{-- Sales Trend & Channel Distribution - Load in parallel --}}
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                <livewire:dashboard.sales-trend-chart lazy />
                <livewire:dashboard.channel-distribution-chart lazy />
            </div>

            {{-- Daily Revenue Chart --}}
            <livewire:dashboard.daily-revenue-chart lazy />
        </div>

        {{-- Cache Status --}}
        <livewire:components.cache-status />

        {{-- Analytics Grid - Top Products & Channels load in parallel --}}
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

        <livewire:dashboard.recent-orders lazy />

    </div>
</div>
