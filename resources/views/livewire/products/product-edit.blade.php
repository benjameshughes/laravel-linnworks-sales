<div class="max-w-4xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
    {{-- Header --}}
    <div class="mb-8">
        <div class="flex items-center gap-3 mb-2">
            <a href="{{ route('products.detail', $product->sku) }}" class="text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300 transition-colors">
                <flux:icon.arrow-left class="size-5" />
            </a>
            <flux:heading size="xl">Edit Product</flux:heading>
        </div>
        <flux:subheading class="ml-8">
            <span class="font-mono text-sm bg-zinc-100 dark:bg-zinc-800 px-2 py-1 rounded">{{ $product->sku }}</span>
        </flux:subheading>
    </div>

    <form wire:submit="save" class="space-y-8">
        {{-- Basic Information --}}
        <x-animations.fade-in-up :delay="100" class="bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 p-6 space-y-6">
            <div class="flex items-start gap-3">
                <div class="flex-shrink-0 w-10 h-10 bg-blue-100 dark:bg-blue-900/20 rounded-lg flex items-center justify-center">
                    <flux:icon.cube class="size-6 text-blue-600 dark:text-blue-400" />
                </div>
                <div class="flex-1">
                    <flux:heading size="lg">Basic Information</flux:heading>
                    <flux:subheading>Product name, description, and categorization</flux:subheading>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <flux:field class="md:col-span-2">
                    <flux:label>Title</flux:label>
                    <flux:input wire:model="title" type="text" required />
                    <flux:error name="title" />
                </flux:field>

                <flux:field class="md:col-span-2">
                    <flux:label>Description</flux:label>
                    <flux:textarea wire:model="description" rows="3" />
                    <flux:error name="description" />
                </flux:field>

                <flux:field>
                    <flux:label>Brand</flux:label>
                    <flux:input wire:model="brand" type="text" />
                    <flux:error name="brand" />
                </flux:field>

                <flux:field>
                    <flux:label>Category</flux:label>
                    <flux:input wire:model="category_name" type="text" />
                    <flux:error name="category_name" />
                </flux:field>

                <flux:field>
                    <flux:label>Barcode</flux:label>
                    <flux:input wire:model="barcode" type="text" placeholder="EAN/UPC/ISBN" />
                    <flux:error name="barcode" />
                </flux:field>

                <flux:field>
                    <div class="flex items-center justify-between">
                        <flux:label>Active</flux:label>
                        <flux:switch wire:model="is_active" />
                    </div>
                    <flux:description>Inactive products are excluded from reports and searches</flux:description>
                </flux:field>
            </div>
        </x-animations.fade-in-up>

        {{-- Pricing & Costs --}}
        <x-animations.fade-in-up :delay="200" class="bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 p-6 space-y-6">
            <div class="flex items-start gap-3">
                <div class="flex-shrink-0 w-10 h-10 bg-emerald-100 dark:bg-emerald-900/20 rounded-lg flex items-center justify-center">
                    <flux:icon.banknotes class="size-6 text-emerald-600 dark:text-emerald-400" />
                </div>
                <div class="flex-1">
                    <flux:heading size="lg">Pricing & Costs</flux:heading>
                    <flux:subheading>Set purchase cost, retail price, shipping, and tax</flux:subheading>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <flux:field>
                    <flux:label>Purchase Price (Cost)</flux:label>
                    <flux:input wire:model="purchase_price" type="number" step="0.0001" min="0" icon="currency-pound" />
                    <flux:description>Your cost to acquire this product</flux:description>
                    <flux:error name="purchase_price" />
                </flux:field>

                <flux:field>
                    <flux:label>Retail Price</flux:label>
                    <flux:input wire:model="retail_price" type="number" step="0.0001" min="0" icon="currency-pound" />
                    <flux:description>Standard selling price</flux:description>
                    <flux:error name="retail_price" />
                </flux:field>

                <flux:field>
                    <flux:label>Shipping Cost</flux:label>
                    <flux:input wire:model="shipping_cost" type="number" step="0.0001" min="0" icon="currency-pound" />
                    <flux:description>Average cost to ship this product</flux:description>
                    <flux:error name="shipping_cost" />
                </flux:field>

                <flux:field>
                    <flux:label>Default Tax Rate (%)</flux:label>
                    <flux:input wire:model="default_tax_rate" type="number" step="0.01" min="0" max="100" icon="receipt-percent" />
                    <flux:description>Standard tax rate for this product</flux:description>
                    <flux:error name="default_tax_rate" />
                </flux:field>
            </div>

            @if($purchase_price && $retail_price && (float)$retail_price > 0)
                <div class="mt-4 p-4 bg-zinc-50 dark:bg-zinc-800/50 rounded-lg">
                    <div class="text-sm text-zinc-600 dark:text-zinc-400">
                        @php
                            $cost = (float)$purchase_price + (float)($shipping_cost ?? 0);
                            $price = (float)$retail_price;
                            $margin = $price > 0 ? (($price - $cost) / $price) * 100 : 0;
                        @endphp
                        <span class="font-medium">Calculated Margin:</span>
                        <span class="ml-2 font-mono {{ $margin >= 30 ? 'text-emerald-600 dark:text-emerald-400' : ($margin >= 15 ? 'text-amber-600 dark:text-amber-400' : 'text-red-600 dark:text-red-400') }}">
                            {{ number_format($margin, 1) }}%
                        </span>
                        <span class="text-xs ml-2">(includes shipping cost)</span>
                    </div>
                </div>
            @endif
        </x-animations.fade-in-up>

        {{-- Physical Specifications --}}
        <x-animations.fade-in-up :delay="300" class="bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 p-6 space-y-6">
            <div class="flex items-start gap-3">
                <div class="flex-shrink-0 w-10 h-10 bg-purple-100 dark:bg-purple-900/20 rounded-lg flex items-center justify-center">
                    <flux:icon.scale class="size-6 text-purple-600 dark:text-purple-400" />
                </div>
                <div class="flex-1">
                    <flux:heading size="lg">Physical Specifications</flux:heading>
                    <flux:subheading>Weight and dimensions for shipping calculations</flux:subheading>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                <flux:field>
                    <flux:label>Weight (kg)</flux:label>
                    <flux:input wire:model="weight" type="number" step="0.001" min="0" />
                    <flux:error name="weight" />
                </flux:field>

                <flux:field>
                    <flux:label>Height (cm)</flux:label>
                    <flux:input wire:model="dimension_height" type="number" step="0.1" min="0" />
                    <flux:error name="dimension_height" />
                </flux:field>

                <flux:field>
                    <flux:label>Width (cm)</flux:label>
                    <flux:input wire:model="dimension_width" type="number" step="0.1" min="0" />
                    <flux:error name="dimension_width" />
                </flux:field>

                <flux:field>
                    <flux:label>Depth (cm)</flux:label>
                    <flux:input wire:model="dimension_depth" type="number" step="0.1" min="0" />
                    <flux:error name="dimension_depth" />
                </flux:field>
            </div>
        </x-animations.fade-in-up>

        {{-- Inventory Settings --}}
        <x-animations.fade-in-up :delay="400" class="bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 p-6 space-y-6">
            <div class="flex items-start gap-3">
                <div class="flex-shrink-0 w-10 h-10 bg-amber-100 dark:bg-amber-900/20 rounded-lg flex items-center justify-center">
                    <flux:icon.archive-box class="size-6 text-amber-600 dark:text-amber-400" />
                </div>
                <div class="flex-1">
                    <flux:heading size="lg">Inventory Settings</flux:heading>
                    <flux:subheading>Stock levels are synced from Linnworks - only minimum level is editable</flux:subheading>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <flux:field>
                    <flux:label>Minimum Stock Level</flux:label>
                    <flux:input wire:model="stock_minimum" type="number" min="0" />
                    <flux:description>Alert threshold for low stock warnings</flux:description>
                    <flux:error name="stock_minimum" />
                </flux:field>

                <div class="p-4 bg-zinc-50 dark:bg-zinc-800/50 rounded-lg">
                    <div class="text-sm text-zinc-500 dark:text-zinc-400">Current Stock</div>
                    <div class="text-2xl font-semibold text-zinc-900 dark:text-zinc-100">{{ number_format($product->stock_level ?? 0) }}</div>
                </div>

                <div class="p-4 bg-zinc-50 dark:bg-zinc-800/50 rounded-lg">
                    <div class="text-sm text-zinc-500 dark:text-zinc-400">Available</div>
                    <div class="text-2xl font-semibold {{ ($product->stock_available ?? 0) <= ($product->stock_minimum ?? 0) ? 'text-amber-600 dark:text-amber-400' : 'text-emerald-600 dark:text-emerald-400' }}">
                        {{ number_format($product->stock_available ?? 0) }}
                    </div>
                </div>
            </div>
        </x-animations.fade-in-up>

        {{-- Actions --}}
        <div class="flex items-center justify-between pt-4">
            <x-action-message on="product-updated">
                Saved successfully!
            </x-action-message>

            <div class="flex items-center gap-3">
                <flux:button variant="ghost" href="{{ route('products.detail', $product->sku) }}">
                    Cancel
                </flux:button>
                <flux:button variant="primary" type="submit" icon="check">
                    Save Changes
                </flux:button>
            </div>
        </div>
    </form>
</div>
