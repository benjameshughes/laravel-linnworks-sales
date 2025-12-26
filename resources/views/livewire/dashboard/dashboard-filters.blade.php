<div>
    {{-- Simple border-bottom separator, no card wrapper --}}
    <div class="flex flex-col gap-3 pb-4 border-b border-zinc-200 dark:border-zinc-700">
        {{-- Top Row: Title + Date Picker (left) | Sync Info (right) --}}
        <div class="flex items-center justify-between gap-4">
            <div class="flex items-center gap-2">
                <flux:heading size="xl" class="text-zinc-900 dark:text-zinc-100">Dashboard</flux:heading>

                {{-- Date picker inline with title --}}
                <flux:date-picker
                    wire:model.live="dateRange"
                    wire:change="applyCustomRange"
                    mode="range"
                    with-presets
                    with-inputs
                    selectable-header
                    max="{{ now()->format('Y-m-d') }}"
                >
                    <x-slot name="trigger">
                        <flux:button variant="ghost" size="sm" class="gap-1.5 font-normal text-zinc-500 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100">
                            <flux:icon name="calendar-days" variant="outline" class="size-4" />
                            <span>{{ $this->formattedDateRange }}</span>
                            @if($period === 'custom')
                                <flux:badge color="blue" size="sm">Custom</flux:badge>
                            @endif
                        </flux:button>
                    </x-slot>
                </flux:date-picker>
            </div>

            {{-- Sync Status + Last Update (compact, right-aligned) --}}
            <div class="flex items-center gap-3 text-sm text-zinc-500 dark:text-zinc-400">
                {{-- Order count --}}
                <span class="flex items-center gap-1.5">
                    <flux:icon name="shopping-bag" class="size-4" />
                    <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ number_format($this->totalOrders) }}</span>
                    <span>orders</span>
                </span>

                <span class="text-zinc-300 dark:text-zinc-600">·</span>

                {{-- Sync timer --}}
                <span class="flex items-center gap-1"
                      wire:ignore
                      x-data="{
                          elapsed: {{ $this->lastSyncInfo->get('elapsed_seconds', 0) }},
                          interval: null,
                          displayText: '{{ $this->lastSyncInfo->get('time_human') }}',
                          isSyncing: @entangle('isSyncing'),
                          init() {
                              this.startTimer();
                              this.$watch('isSyncing', (value) => {
                                  if (!value) {
                                      this.elapsed = 0;
                                      this.startTimer();
                                  }
                              });
                          },
                          startTimer() {
                              if (this.interval) clearInterval(this.interval);
                              if (this.elapsed >= 60) {
                                  this.updateMinuteDisplay();
                                  const secondsIntoMinute = this.elapsed % 60;
                                  const secondsUntilNextMinute = 60 - secondsIntoMinute;
                                  setTimeout(() => {
                                      this.tickMinute();
                                      this.interval = setInterval(() => this.tickMinute(), 60000);
                                  }, secondsUntilNextMinute * 1000);
                              } else {
                                  this.interval = setInterval(() => this.tickSecond(), 1000);
                              }
                          },
                          tickSecond() {
                              if (this.isSyncing) return;
                              this.elapsed++;
                              if (this.elapsed < 60) {
                                  this.displayText = this.elapsed + ' second' + (this.elapsed === 1 ? '' : 's') + ' ago';
                              } else {
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
                    <span x-show="isSyncing" class="flex items-center gap-1">
                        <svg class="animate-spin size-3 text-blue-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span class="text-blue-600 dark:text-blue-400" x-text="$wire.syncMessage">{{ $syncMessage }}</span>
                    </span>
                    <span x-show="!isSyncing" class="flex items-center gap-1">
                        <flux:icon name="arrow-path" class="size-3" />
                        <span x-text="displayText">{{ $this->lastSyncInfo->get('time_human') }}</span>
                    </span>
                </span>

                {{-- Success rate badge --}}
                @if($this->lastSyncInfo->get('status') === 'success')
                    <flux:badge color="green" size="sm">
                        {{ number_format($this->lastSyncInfo->get('success_rate'), 1) }}%
                    </flux:badge>
                @endif
            </div>
        </div>

        {{-- Bottom Row: Filters + Sync Button --}}
        <div class="flex items-center gap-2">
            <flux:select wire:model.live.debounce.300ms="status" size="sm" class="!w-auto">
                <flux:select.option value="all">All Orders</flux:select.option>
                <flux:select.option value="open_paid">Open & Paid</flux:select.option>
                <flux:select.option value="open">Open (All)</flux:select.option>
                <flux:select.option value="processed">Processed</flux:select.option>
            </flux:select>

            <flux:select wire:model.live.debounce.300ms="period" size="sm" class="!w-auto">
                @foreach(\App\Enums\Period::all() as $periodOption)
                    <flux:select.option value="{{ $periodOption->value }}">
                        {{ $periodOption->label() }}
                    </flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live.debounce.300ms="channel" size="sm" class="!w-auto">
                <flux:select.option value="all">All Channels</flux:select.option>
                @foreach($this->availableChannels as $channelOption)
                    <flux:select.option value="{{ $channelOption->get('name') }}">
                        {{ $channelOption->get('label') }}
                    </flux:select.option>
                @endforeach
            </flux:select>

            <div class="flex-1"></div>

            {{-- Sync button --}}
            <div x-data="{
                rateLimitSeconds: @entangle('rateLimitSeconds'),
                countdown() {
                    if (this.rateLimitSeconds > 0) {
                        this.rateLimitSeconds--;
                        if (this.rateLimitSeconds === 0) {
                            $wire.checkRateLimit();
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
                >
                    <span x-show="!$wire.isSyncing && rateLimitSeconds === 0">Sync</span>
                    <span x-show="$wire.isSyncing">Syncing</span>
                    <span x-show="!$wire.isSyncing && rateLimitSeconds > 0" x-text="`Wait ${rateLimitSeconds}s`"></span>
                </flux:button>
            </div>
        </div>
    </div>

    {{-- Loading indicator for custom date range calculations --}}
    <div
        x-data="{
            isLoading: @entangle('isLoadingData'),
            showIndicator: false,
            timeout: null,
            init() {
                this.$watch('isLoading', (value) => {
                    if (value) {
                        this.timeout = setTimeout(() => { this.showIndicator = true }, 400);
                    } else {
                        clearTimeout(this.timeout);
                        this.showIndicator = false;
                    }
                });
            }
        }"
        x-show="showIndicator"
        x-transition
        class="mt-3 flex items-center gap-2 px-3 py-2 bg-zinc-50 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-lg text-sm text-zinc-600 dark:text-zinc-400"
        style="display: none;"
    >
        <svg class="animate-spin size-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
        <span>Crunching your custom date range...</span>
    </div>
</div>
