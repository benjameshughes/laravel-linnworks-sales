<div class="relative" x-data="{ 
    isOpen: false,
    init() {
        this.isOpen = @js($isOpen);
    },
    toggle() {
        this.isOpen = !this.isOpen;
        if (this.isOpen) {
            $wire.open();
        } else {
            $wire.close();
        }
    },
    close() {
        this.isOpen = false;
        $wire.close();
    }
}" @close-dropdowns.window="close()">
    @if($label)
        <flux:label class="mb-1">{{ $label }}</flux:label>
    @endif

    <!-- Trigger Button -->
    <button
        type="button"
        @click="toggle()"
        @click.away="close()"
        class="relative w-full cursor-pointer rounded-lg bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 py-2 pl-3 pr-10 text-left text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 disabled:cursor-not-allowed disabled:bg-zinc-50 dark:disabled:bg-zinc-900 disabled:text-zinc-500"
    >
        <span class="block truncate {{ count($selected) === 0 ? 'text-zinc-500 dark:text-zinc-400' : 'text-zinc-900 dark:text-zinc-100' }}">
            {{ $this->getDisplayText() }}
        </span>
        <span class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-2">
            <flux:icon 
                name="chevron-down" 
                class="h-4 w-4 text-zinc-400 transition-transform duration-200"
                ::class="{ 'rotate-180': isOpen }"
            />
        </span>
    </button>

    <!-- Dropdown Panel -->
    <div
        x-show="isOpen"
        x-transition:enter="transition ease-out duration-100"
        x-transition:enter-start="transform opacity-0 scale-95"
        x-transition:enter-end="transform opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-75"
        x-transition:leave-start="transform opacity-100 scale-100"
        x-transition:leave-end="transform opacity-0 scale-95"
        class="absolute z-50 mt-1 w-full rounded-lg bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 shadow-lg"
        style="display: none;"
        @click.stop
    >
        <!-- Search Input (if searchable) -->
        @if($searchable)
            <div class="p-2 border-b border-zinc-200 dark:border-zinc-700">
                <input
                    type="text"
                    wire:model.live="search"
                    placeholder="Search..."
                    class="w-full rounded-md border border-zinc-200 dark:border-zinc-600 bg-white dark:bg-zinc-800 px-3 py-1.5 text-sm text-zinc-900 dark:text-zinc-100 placeholder-zinc-500 dark:placeholder-zinc-400 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                />
            </div>
        @endif

        <!-- Select All / Deselect All -->
        @if(count($this->getFilteredOptions()) > 1)
            <div class="flex items-center justify-between px-3 py-2 border-b border-zinc-200 dark:border-zinc-700">
                <button
                    type="button"
                    wire:click="selectAll"
                    class="text-xs font-medium text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300"
                >
                    Select All
                </button>
                <button
                    type="button"
                    wire:click="deselectAll"
                    class="text-xs font-medium text-zinc-600 dark:text-zinc-400 hover:text-zinc-800 dark:hover:text-zinc-300"
                >
                    Clear
                </button>
            </div>
        @endif

        <!-- Options List -->
        <div class="max-h-60 overflow-auto py-1">
            @forelse($this->getFilteredOptions() as $option)
                @php
                    $value = is_array($option) ? $option['value'] : $option;
                    $label = is_array($option) ? ($option['label'] ?? $option['value']) : $option;
                    $isSelected = in_array($value, $selected);
                @endphp
                
                <div
                    wire:click="toggleOption('{{ $value }}')"
                    @click.stop
                    class="flex items-center px-3 py-2 text-sm cursor-pointer hover:bg-zinc-50 dark:hover:bg-zinc-700/50 {{ $isSelected ? 'bg-blue-50 dark:bg-blue-900/20' : '' }}"
                >
                    <div class="flex h-4 w-4 flex-shrink-0 items-center mr-3">
                        <input
                            type="checkbox"
                            checked="{{ $isSelected ? 'true' : 'false' }}"
                            class="h-4 w-4 rounded border-zinc-300 dark:border-zinc-600 text-blue-600 focus:ring-blue-500 dark:bg-zinc-800"
                            readonly
                        />
                    </div>
                    <span class="block truncate {{ $isSelected ? 'font-medium text-blue-600 dark:text-blue-400' : 'text-zinc-900 dark:text-zinc-100' }}">
                        {{ $label }}
                    </span>
                </div>
            @empty
                <div class="px-3 py-2 text-sm text-zinc-500 dark:text-zinc-400 text-center">
                    {{ $emptyMessage }}
                </div>
            @endforelse
        </div>
    </div>
</div>