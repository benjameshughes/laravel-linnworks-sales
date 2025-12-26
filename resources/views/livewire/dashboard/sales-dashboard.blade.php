<div class="min-h-screen">
    <div class="space-y-3 p-3 lg:p-4">
        {{-- Filters Island --}}
        <livewire:dashboard.dashboard-filters />

        {{-- Metrics Summary Island --}}
        <livewire:dashboard.metrics-summary />

        {{-- Charts Grid --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-3">
            <livewire:dashboard.sales-trend-chart lazy />
            <livewire:dashboard.channel-distribution-chart lazy />
        </div>

        {{-- Daily Revenue Chart --}}
        <livewire:dashboard.daily-revenue-chart lazy />

        {{-- Analytics Grid --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-3">
            <livewire:dashboard.top-products lazy />
            <livewire:dashboard.top-channels lazy />
        </div>

        {{-- Recent Orders Table --}}
        <livewire:dashboard.recent-orders lazy />
    </div>
</div>
