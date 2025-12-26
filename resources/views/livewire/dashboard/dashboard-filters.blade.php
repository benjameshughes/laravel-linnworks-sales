<div>
    {{-- Header with Controls --}}
    <div class="bg-white dark:bg-zinc-800 rounded-lg shadow-sm border border-zinc-200 dark:border-zinc-700 p-3 transition-all duration-300 hover:shadow-md">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3">
            {{-- Left: Info --}}
            <div class="flex flex-wrap items-center gap-2 text-sm text-zinc-600 dark:text-zinc-400">
                {{-- Inline date-picker with custom trigger --}}
                <flux:date-picker
                    wire:model.live="dateRange"
                    wire:change="applyCustomRange"
                    mode="range"
                    with-presets
                    max="{{ now()->format('Y-m-d') }}"
                >
                    <x-slot name="trigger">
                        <flux:button variant="ghost" size="sm" class="gap-1.5 -mx-1 font-normal text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100">
                            <flux:icon name="calendar-days" variant="outline" class="size-4" />
                            <span>{{ $this->formattedDateRange }}</span>
                            @if($period === 'custom')
                                <flux:badge color="blue" size="sm">Custom</flux:badge>
                            @endif
                        </flux:button>
                    </x-slot>
                </flux:date-picker>
                <span class="text-zinc-400">•</span>
                <span class="font-medium text-zinc-900 dark:text-zinc-100 transition-all duration-300"
                      x-data="{ orders: {{ $this->totalOrders }} }"
                      x-init="$watch('orders', () => { $el.classList.add('scale-110', 'text-blue-600', 'dark:text-blue-400'); setTimeout(() => $el.classList.remove('scale-110', 'text-blue-600', 'dark:text-blue-400'), 500) })">
                    {{ number_format($this->totalOrders) }} orders
                </span>
                <span class="text-zinc-400">•</span>
                <span class="flex items-center gap-1"
                      wire:ignore
                      x-data="{
                          elapsed: {{ $this->lastSyncInfo->get('elapsed_seconds', 0) }},
                          interval: null,
                          displayText: '{{ $this->lastSyncInfo->get('time_human') }}',
                          isSyncing: @entangle('isSyncing'),
                          init() {
                              this.startTimer();
                              // Watch for sync state changes
                              this.$watch('isSyncing', (value) => {
                                  if (!value) {
                                      // Sync just completed, reset timer
                                      this.elapsed = 0;
                                      this.startTimer();
                                  }
                              });
                          },
                          startTimer() {
                              if (this.interval) clearInterval(this.interval);

                              // If already past 60 seconds, use minute-based updates
                              if (this.elapsed >= 60) {
                                  this.updateMinuteDisplay();

                                  // Calculate seconds until next minute boundary
                                  const secondsIntoMinute = this.elapsed % 60;
                                  const secondsUntilNextMinute = 60 - secondsIntoMinute;

                                  // First tick at the minute boundary, then every 60s after
                                  setTimeout(() => {
                                      this.tickMinute();
                                      this.interval = setInterval(() => this.tickMinute(), 60000);
                                  }, secondsUntilNextMinute * 1000);
                              } else {
                                  // Use second-based updates
                                  this.interval = setInterval(() => this.tickSecond(), 1000);
                              }
                          },
                          tickSecond() {
                              if (this.isSyncing) return;

                              this.elapsed++;

                              if (this.elapsed < 60) {
                                  this.displayText = this.elapsed + ' second' + (this.elapsed === 1 ? '' : 's') + ' ago';
                              } else {
                                  // Hit 60 seconds, switch to minute-based updates
                                  clearInterval(this.interval);
                                  this.updateMinuteDisplay();
                                  this.interval = setInterval(() => this.tickMinute(), 60000);
                              }
                          },
                          tickMinute() {
                              if (this.isSyncing) return;

                              this.elapsed += 60;
                              this.updateMinuteDisplay();
                          },
                          updateMinuteDisplay() {
                              const minutes = Math.floor(this.elapsed / 60);
                              this.displayText = minutes + ' minute' + (minutes === 1 ? '' : 's') + ' ago';
                          }
                      }">
                    <span x-show="isSyncing" class="flex items-center gap-1"
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
                        <span class="text-blue-600 dark:text-blue-400" x-text="$wire.syncMessage">{{ $syncMessage }}</span>
                    </span>
                    <span x-show="!isSyncing" class="flex items-center gap-1"
                          x-transition:enter="transition ease-out duration-300"
                          x-transition:enter-start="opacity-0"
                          x-transition:enter-end="opacity-100"
                          x-transition:leave="transition ease-in duration-200"
                          x-transition:leave-start="opacity-100"
                          x-transition:leave-end="opacity-0">
                        <flux:icon name="arrow-path" class="size-3 text-zinc-500 transition-transform duration-500 hover:rotate-180" />
                        <span x-text="displayText">{{ $this->lastSyncInfo->get('time_human') }}</span>
                    </span>
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
                <div class="relative min-w-[140px] flex-1 lg:flex-initial lg:w-40">
                    <flux:select wire:model.live.debounce.300ms="status" size="sm">
                        <flux:select.option value="all">All Orders</flux:select.option>
                        <flux:select.option value="open_paid">Open & Paid</flux:select.option>
                        <flux:select.option value="open">Open (All)</flux:select.option>
                        <flux:select.option value="processed">Processed</flux:select.option>
                    </flux:select>
                </div>

                <div class="relative min-w-[140px] flex-1 lg:flex-initial lg:w-36">
                    <flux:select wire:model.live.debounce.300ms="period" size="sm">
                        @foreach(\App\Enums\Period::all() as $periodOption)
                            <flux:select.option value="{{ $periodOption->value }}">
                                {{ $periodOption->label() }}
                            </flux:select.option>
                        @endforeach
                    </flux:select>
                </div>

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

        {{-- Loading indicator for custom date range calculations --}}
        <div
            x-data="{ show: @entangle('isLoadingData') }"
            x-show="show"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 -translate-y-2"
            x-transition:enter-end="opacity-100 translate-y-0"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100 translate-y-0"
            x-transition:leave-end="opacity-0 -translate-y-2"
            class="mt-3 flex items-center gap-2 px-4 py-2 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg text-sm text-blue-700 dark:text-blue-300"
            style="display: none;"
        >
            <svg class="animate-spin size-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <span>Crunching your custom date range...</span>
        </div>
    </div>

</div>