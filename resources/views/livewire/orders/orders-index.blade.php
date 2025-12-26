<div class="min-h-screen">
    <div class="space-y-4 p-4 lg:p-6">
        {{-- Filters Island - NOT lazy loaded (user needs this immediately) --}}
        <livewire:orders.order-filters />

        {{-- Metrics Island - lazy loaded --}}
        <livewire:orders.order-metrics lazy />

        {{-- Chart Island - lazy loaded --}}
        <livewire:orders.orders-chart lazy />

        {{-- Analytics Grid --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            {{-- Orders Table - lazy loaded --}}
            <div class="lg:col-span-2">
                <livewire:orders.orders-table lazy />
            </div>

            {{-- Sidebar --}}
            <div>
                {{-- Top Customers - lazy loaded --}}
                <livewire:orders.top-customers lazy />
            </div>
        </div>
    </div>
</div>
