<div class="max-w-4xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
    {{-- Header --}}
    <div class="mb-8">
        <div class="flex items-center gap-3 mb-2">
            <a href="{{ route('products.analytics') }}" class="text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300 transition-colors">
                <flux:icon.arrow-left class="size-5" />
            </a>
            <flux:heading size="xl">Product Import/Export</flux:heading>
        </div>
        <flux:subheading class="ml-8">
            Manage product costs and details via CSV
        </flux:subheading>
    </div>

    <div class="space-y-8">
        {{-- Export Section --}}
        <x-animations.fade-in-up :delay="100" class="bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 p-6 space-y-6">
            <div class="flex items-start gap-3">
                <div class="flex-shrink-0 w-10 h-10 bg-blue-100 dark:bg-blue-900/20 rounded-lg flex items-center justify-center">
                    <flux:icon.arrow-down-tray class="size-6 text-blue-600 dark:text-blue-400" />
                </div>
                <div class="flex-1">
                    <flux:heading size="lg">Export Products</flux:heading>
                    <flux:subheading>Download {{ number_format($productCount) }} products as CSV</flux:subheading>
                </div>
            </div>

            <div class="space-y-4">
                <div>
                    <flux:label class="mb-3 block">Select fields to export:</flux:label>
                    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">
                        @foreach($exportFields as $field => $selected)
                            <label class="flex items-center gap-2 cursor-pointer">
                                <flux:checkbox wire:model="exportFields.{{ $field }}" :disabled="$field === 'sku'" />
                                <span class="text-sm text-zinc-700 dark:text-zinc-300 {{ $field === 'sku' ? 'font-medium' : '' }}">
                                    {{ str_replace('_', ' ', ucfirst($field)) }}
                                    @if($field === 'sku')
                                        <span class="text-xs text-zinc-400">(required)</span>
                                    @endif
                                </span>
                            </label>
                        @endforeach
                    </div>
                </div>

                <flux:separator />

                <div class="flex justify-end">
                    <flux:button variant="primary" wire:click="export" icon="arrow-down-tray">
                        Export CSV
                    </flux:button>
                </div>
            </div>
        </x-animations.fade-in-up>

        {{-- Import Section --}}
        <x-animations.fade-in-up :delay="200" class="bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 p-6 space-y-6">
            <div class="flex items-start gap-3">
                <div class="flex-shrink-0 w-10 h-10 bg-emerald-100 dark:bg-emerald-900/20 rounded-lg flex items-center justify-center">
                    <flux:icon.arrow-up-tray class="size-6 text-emerald-600 dark:text-emerald-400" />
                </div>
                <div class="flex-1">
                    <flux:heading size="lg">Import Products</flux:heading>
                    <flux:subheading>Update existing products from CSV - only products with matching SKUs will be updated</flux:subheading>
                </div>
            </div>

            <div class="space-y-4">
                {{-- Instructions --}}
                <div class="p-4 bg-zinc-50 dark:bg-zinc-800/50 rounded-lg space-y-2 text-sm text-zinc-600 dark:text-zinc-400">
                    <div class="font-medium text-zinc-900 dark:text-zinc-100">Import Instructions:</div>
                    <ul class="list-disc list-inside space-y-1">
                        <li>CSV must have a <code class="bg-zinc-200 dark:bg-zinc-700 px-1 rounded">sku</code> column to match products</li>
                        <li>Only existing products will be updated (new SKUs are skipped)</li>
                        <li>Empty cells are ignored (existing values preserved)</li>
                        <li>Invalid values are skipped for that field</li>
                        <li>Supports: purchase_price, retail_price, shipping_cost, default_tax_rate, weight, stock_minimum, and more</li>
                    </ul>
                </div>

                {{-- File Upload --}}
                <div
                    x-data="{ dragging: false }"
                    x-on:dragenter.prevent="dragging = true"
                    x-on:dragleave.prevent="dragging = false"
                    x-on:dragover.prevent
                    x-on:drop.prevent="dragging = false; $refs.fileInput.files = $event.dataTransfer.files; $refs.fileInput.dispatchEvent(new Event('change'))"
                    class="relative"
                >
                    <label
                        for="file-upload"
                        :class="{ 'border-blue-400 bg-blue-50 dark:bg-blue-900/20': dragging }"
                        class="flex flex-col items-center justify-center w-full h-32 border-2 border-dashed border-zinc-300 dark:border-zinc-600 rounded-lg cursor-pointer hover:border-zinc-400 dark:hover:border-zinc-500 transition-colors"
                    >
                        <div class="flex flex-col items-center justify-center pt-5 pb-6">
                            <flux:icon.cloud-arrow-up class="size-10 mb-3 text-zinc-400" />
                            <p class="mb-2 text-sm text-zinc-500 dark:text-zinc-400">
                                <span class="font-semibold">Click to upload</span> or drag and drop
                            </p>
                            <p class="text-xs text-zinc-500 dark:text-zinc-400">CSV files only (max 10MB)</p>
                        </div>
                        <input
                            x-ref="fileInput"
                            wire:model="file"
                            id="file-upload"
                            type="file"
                            class="hidden"
                            accept=".csv,.txt"
                        />
                    </label>
                </div>

                @error('file')
                    <div class="text-red-500 text-sm">{{ $message }}</div>
                @enderror

                @if($file)
                    <div class="flex items-center gap-3 p-3 bg-zinc-50 dark:bg-zinc-800/50 rounded-lg">
                        <flux:icon.document-text class="size-5 text-zinc-500" />
                        <span class="text-sm text-zinc-700 dark:text-zinc-300 flex-1">{{ $file->getClientOriginalName() }}</span>
                        <flux:button variant="ghost" size="sm" wire:click="$set('file', null)" icon="x-mark">
                            Remove
                        </flux:button>
                    </div>
                @endif

                <flux:separator />

                <div class="flex items-center justify-between">
                    <flux:button variant="ghost" wire:click="downloadTemplate" icon="document-arrow-down">
                        Download Template
                    </flux:button>

                    <flux:button
                        variant="primary"
                        wire:click="import"
                        :disabled="!$file"
                        icon="arrow-up-tray"
                        wire:loading.attr="disabled"
                    >
                        <span wire:loading.remove wire:target="import">Import CSV</span>
                        <span wire:loading wire:target="import">Importing...</span>
                    </flux:button>
                </div>
            </div>
        </x-animations.fade-in-up>

        {{-- Import Results --}}
        @if($showResults)
            <x-animations.fade-in-up :delay="300" class="bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 p-6 space-y-6">
                <div class="flex items-start gap-3">
                    <div class="flex-shrink-0 w-10 h-10 bg-purple-100 dark:bg-purple-900/20 rounded-lg flex items-center justify-center">
                        <flux:icon.clipboard-document-check class="size-6 text-purple-600 dark:text-purple-400" />
                    </div>
                    <div class="flex-1">
                        <flux:heading size="lg">Import Results</flux:heading>
                        <flux:subheading>Summary of the import operation</flux:subheading>
                    </div>
                </div>

                {{-- Summary Stats --}}
                <div class="grid grid-cols-3 gap-4">
                    <div class="p-4 bg-emerald-50 dark:bg-emerald-900/20 rounded-lg text-center">
                        <div class="text-2xl font-bold text-emerald-600 dark:text-emerald-400">{{ $updated }}</div>
                        <div class="text-sm text-emerald-700 dark:text-emerald-300">Updated</div>
                    </div>
                    <div class="p-4 bg-amber-50 dark:bg-amber-900/20 rounded-lg text-center">
                        <div class="text-2xl font-bold text-amber-600 dark:text-amber-400">{{ $skipped }}</div>
                        <div class="text-sm text-amber-700 dark:text-amber-300">Skipped</div>
                    </div>
                    <div class="p-4 bg-red-50 dark:bg-red-900/20 rounded-lg text-center">
                        <div class="text-2xl font-bold text-red-600 dark:text-red-400">{{ count($importErrors) }}</div>
                        <div class="text-sm text-red-700 dark:text-red-300">Errors</div>
                    </div>
                </div>

                {{-- Error Details --}}
                @if(count($importErrors) > 0)
                    <div class="space-y-2">
                        <flux:label>Error Details:</flux:label>
                        <div class="max-h-64 overflow-y-auto space-y-2">
                            @foreach($importErrors as $error)
                                <div class="flex items-start gap-3 p-3 bg-red-50 dark:bg-red-900/20 rounded-lg text-sm">
                                    <flux:icon.exclamation-circle class="size-5 text-red-500 flex-shrink-0 mt-0.5" />
                                    <div>
                                        <span class="font-medium text-red-800 dark:text-red-200">Row {{ $error['row'] }}</span>
                                        @if($error['sku'] !== 'N/A')
                                            <span class="text-red-600 dark:text-red-400">({{ $error['sku'] }})</span>
                                        @endif
                                        <span class="text-red-700 dark:text-red-300">: {{ $error['message'] }}</span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                <flux:separator />

                <div class="flex justify-end">
                    <flux:button variant="ghost" wire:click="resetImportState" icon="arrow-path">
                        Import Another File
                    </flux:button>
                </div>
            </x-animations.fade-in-up>
        @endif
    </div>
</div>
