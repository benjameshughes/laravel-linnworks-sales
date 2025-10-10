<div>
    {{-- Header with Controls --}}
    <div class="bg-white dark:bg-zinc-800 rounded-lg shadow-sm border border-zinc-200 dark:border-zinc-700 p-3 transition-all duration-300 hover:shadow-md">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3">
            {{-- Left: Info --}}
            <div class="flex flex-wrap items-center gap-2 text-sm text-zinc-600 dark:text-zinc-400">
                <span>{{ $this->formattedDateRange }}</span>
                <span class="text-zinc-400">•</span>
                <span class="font-medium text-zinc-900 dark:text-zinc-100 transition-all duration-300"
                      x-data="{ orders: {{ $this->totalOrders }} }"
                      x-init="$watch('orders', () => { $el.classList.add('scale-110', 'text-blue-600', 'dark:text-blue-400'); setTimeout(() => $el.classList.remove('scale-110', 'text-blue-600', 'dark:text-blue-400'), 500) })"
                      x-effect="orders = {{ $this->totalOrders }}">
                    {{ number_format($this->totalOrders) }} orders
                </span>
                <span class="text-zinc-400">•</span>
                <span class="flex items-center gap-1"
                      x-data="{
                          lastSync: '{{ $this->lastSyncInfo->get('time_human') }}',
                          elapsed: 0,
                          interval: null,
                          updateTime() {
                              this.elapsed++;
                              // After 60 seconds, switch to minute intervals
                              if (this.elapsed === 60) {
                                  clearInterval(this.interval);
                                  this.interval = setInterval(() => $wire.$refresh(), 60000);
                              }
                              $wire.$refresh();
                          }
                      }"
                      x-init="interval = setInterval(() => updateTime(), 1000)">
                    @if($isSyncing)
                        <span class="flex items-center gap-1"
                              x-transition:enter="transition ease-out duration-300"
                              x-transition:enter-start="opacity-0"
                              x-transition:enter-end="opacity-100"
                              x-transition:leave="transition ease-in duration-200"
                              x-transition:leave-start="opacity-100"
                              x-transition:leave-end="opacity-0">
                            <svg class="animate-spin size-3 text-blue-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span class="text-blue-600 dark:text-blue-400">{{ $syncMessage }}</span>
                            @if($syncCount > 0)
                                <span class="text-blue-600 dark:text-blue-400 font-medium">• {{ number_format($syncCount) }}</span>
                            @endif
                        </span>
                    @else
                        <span class="flex items-center gap-1"
                              x-transition:enter="transition ease-out duration-300"
                              x-transition:enter-start="opacity-0"
                              x-transition:enter-end="opacity-100"
                              x-transition:leave="transition ease-in duration-200"
                              x-transition:leave-start="opacity-100"
                              x-transition:leave-end="opacity-0">
                            <flux:icon name="arrow-path" class="size-3 text-zinc-500 transition-transform duration-500 hover:rotate-180" />
                            {{ $this->lastSyncInfo->get('time_human') }}
                        </span>
                    @endif
                </span>
                @if($this->lastSyncInfo->get('status') === 'success')
                    <span x-data x-init="$el.classList.add('opacity-0', 'scale-95'); setTimeout(() => $el.classList.remove('opacity-0', 'scale-95'), 50)" class="transition-all duration-300">
                        <flux:badge color="green" size="sm">
                            {{ number_format($this->lastSyncInfo->get('success_rate'), 1) }}%
                        </flux:badge>
                    </span>
                @endif
            </div>

            {{-- Right: Filters & Controls --}}
            <div class="flex items-center gap-2 flex-wrap lg:flex-nowrap">
                <div class="relative">
                    <flux:input
                        wire:model.live.debounce.300ms="searchTerm"
                        placeholder="Search..."
                        class="w-32 lg:w-40 flex-shrink-0"
                        size="sm"
                    />
                    <div wire:loading wire:target="searchTerm" class="absolute right-2 top-1/2 -translate-y-1/2">
                        <svg class="animate-spin h-4 w-4 text-zinc-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                    </div>
                </div>

                <div class="relative min-w-[140px] flex-1 lg:flex-initial lg:w-36">
                    <flux:select wire:model.live.debounce.300ms="period" size="sm">
                        <flux:select.option value="1">Last 24 hours</flux:select.option>
                        <flux:select.option value="yesterday">Yesterday</flux:select.option>
                        <flux:select.option value="7">Last 7 days</flux:select.option>
                        <flux:select.option value="30">Last 30 days</flux:select.option>
                        <flux:select.option value="90">Last 90 days</flux:select.option>
                        <flux:select.option value="custom">Custom Range...</flux:select.option>
                    </flux:select>
                </div>

                @if($period === 'custom')
                    <div class="flex items-center gap-2">
                        <flux:input
                            type="date"
                            wire:model.live="customFrom"
                            size="sm"
                            class="w-36 flex-shrink-0"
                        />
                        <span class="text-zinc-400">→</span>
                        <flux:input
                            type="date"
                            wire:model.live="customTo"
                            size="sm"
                            class="w-36 flex-shrink-0"
                        />
                    </div>
                @endif

                <div class="relative min-w-[140px] flex-1 lg:flex-initial lg:w-36">
                    <flux:select wire:model.live.debounce.300ms="channel" size="sm">
                        <flux:select.option value="all">All Channels</flux:select.option>
                        @foreach($this->availableChannels as $channelOption)
                            <flux:select.option value="{{ $channelOption->get('name') }}">
                                {{ $channelOption->get('label') }}
                            </flux:select.option>
                        @endforeach
                    </flux:select>
                </div>

                <div x-data="{
                    rateLimitSeconds: @entangle('rateLimitSeconds'),
                    countdown() {
                        if (this.rateLimitSeconds > 0) {
                            this.rateLimitSeconds--;
                            if (this.rateLimitSeconds === 0) {
                                $wire.checkRateLimit();
                                // Subtle pulse when button becomes available
                                $el.querySelector('button').classList.add('animate-pulse');
                                setTimeout(() => $el.querySelector('button').classList.remove('animate-pulse'), 1000);
                            }
                        }
                    }
                }"
                x-init="setInterval(() => countdown(), 1000)">
                    <flux:button
                        variant="primary"
                        size="sm"
                        wire:click="syncOrders"
                        wire:target="syncOrders"
                        ::disabled="$wire.isSyncing || rateLimitSeconds > 0"
                        icon="cloud-arrow-down"
                        class="flex-shrink-0 transition-all duration-300 hover:scale-105 active:scale-95"
                    >
                        <span x-show="!$wire.isSyncing && rateLimitSeconds === 0" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-90" x-transition:enter-end="opacity-100 scale-100">Sync</span>
                        <span x-show="$wire.isSyncing" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-90" x-transition:enter-end="opacity-100 scale-100">Syncing</span>
                        <span x-show="!$wire.isSyncing && rateLimitSeconds > 0" x-text="`Wait ${rateLimitSeconds}s`" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-90" x-transition:enter-end="opacity-100 scale-100"></span>
                    </flux:button>
                </div>
            </div>
        </div>
    </div>

</div>