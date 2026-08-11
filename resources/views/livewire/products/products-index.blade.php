<div class="min-h-screen">
    <div class="space-y-3 p-3 lg:p-4">
        {{-- Filters Island - NOT lazy loaded (user needs this immediately) --}}
        <livewire:products.product-filters />

        {{-- Metrics Island - lazy loaded --}}
        <livewire:products.product-metrics lazy />

        {{-- Analytics Grid --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-3">
            {{-- Products Table - lazy loaded --}}
            <div class="lg:col-span-2">
                <livewire:products.products-table lazy />
            </div>

            {{-- Sidebar --}}
            <div class="space-y-3">
                {{-- Top Categories - lazy loaded --}}
                <livewire:products.top-categories lazy />

                {{-- Stock Alerts - lazy loaded --}}
                <livewire:products.stock-alerts lazy />
            </div>
        </div>

        {{-- Parent Performance Drawer --}}
        <flux:modal.trigger name="parent-performance">
            <flux:button variant="ghost" icon="squares-2x2" size="sm">Product Ranges</flux:button>
        </flux:modal.trigger>

        <flux:modal name="parent-performance" variant="flyout" class="md:min-w-[40rem]">
            <livewire:products.parent-performance lazy />
        </flux:modal>

        {{-- Product Quick View Flyout --}}
        <flux:modal name="product-quick-view" variant="flyout" class="md:min-w-[40rem]">
            <livewire:products.product-quick-view />
        </flux:modal>

        {{-- Footer --}}
        <div class="flex justify-end items-center">
            <div class="text-sm text-zinc-600 dark:text-zinc-400">
                Last updated: {{ now()->format('M j, Y g:i A') }}
            </div>
        </div>
    </div>
</div>
