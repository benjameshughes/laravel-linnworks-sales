<div class="min-h-screen bg-zinc-50 dark:bg-zinc-900">
    <div class="space-y-6 p-6">
        {{-- Filters Island - NOT lazy loaded (user needs this immediately) --}}
        <livewire:orders.order-filters />

        {{-- Metrics Island - lazy loaded --}}
        <livewire:orders.order-metrics lazy />

        {{-- Chart Island - lazy loaded --}}
        <livewire:orders.orders-chart lazy />

        {{-- Analytics Grid --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            {{-- Orders Table - lazy loaded --}}
            <div class="lg:col-span-2">
                <livewire:orders.orders-table lazy />
            </div>

            {{-- Sidebar --}}
            <div class="space-y-6">
                {{-- Top Customers - lazy loaded --}}
                <livewire:orders.top-customers lazy />
            </div>
        </div>

        {{-- Footer --}}
        <div class="flex justify-end items-center">
            <div class="text-sm text-zinc-600 dark:text-zinc-400">
                Last updated: {{ now()->format('M j, Y g:i A') }}
            </div>
        </div>
    </div>
</div>
