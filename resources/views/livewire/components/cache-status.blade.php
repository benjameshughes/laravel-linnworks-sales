<div class="bg-white dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700 p-4">
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
            @if($this->cacheStatus['is_healthy'])
                <div class="w-3 h-3 bg-green-500 rounded-full animate-pulse"></div>
                <span class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Cache Status: Healthy</span>
            @else
                <div class="w-3 h-3 bg-red-500 rounded-full"></div>
                <span class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Cache Status: Issues</span>
            @endif
            
            @if($this->cacheStatus['last_refresh'])
                <span class="text-xs text-zinc-600 dark:text-zinc-400">
                    Last refresh: {{ \Carbon\Carbon::parse($this->cacheStatus['last_refresh']['timestamp'])->diffForHumans() }}
                </span>
            @endif
        </div>
        
        <div class="flex items-center gap-2">
            <flux:button 
                variant="ghost" 
                size="sm" 
                wire:click="refreshCache"
                icon="arrow-path"
            >
                Refresh
            </flux:button>
            
            <flux:button 
                variant="ghost" 
                size="sm" 
                wire:click="toggleDetails"
                icon="{{ $showDetails ? 'chevron-up' : 'chevron-down' }}"
            >
                {{ $showDetails ? 'Hide' : 'Details' }}
            </flux:button>
        </div>
    </div>
    
    @if($showDetails)
        <div class="mt-4 pt-4 border-t border-zinc-200 dark:border-zinc-700">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                {{-- Sales Cache Stats --}}
                <div class="bg-zinc-50 dark:bg-zinc-700 rounded-lg p-3">
                    <h4 class="text-sm font-medium text-zinc-900 dark:text-zinc-100 mb-2">Sales Metrics Cache</h4>
                    <div class="space-y-1 text-xs text-zinc-600 dark:text-zinc-400">
                        <div>Status: {{ $this->salesCacheStats['is_cache_warm'] ? '🔥 Warm' : '❄️ Cold' }}</div>
                        <div>Data Count: {{ number_format($this->salesCacheStats['data_count']) }}</div>
                        <div>Duration: {{ $this->salesCacheStats['cache_duration_minutes'] }}m</div>
                        @if($this->salesCacheStats['last_warmed'])
                            <div>Last Warmed: {{ \Carbon\Carbon::parse($this->salesCacheStats['last_warmed'])->diffForHumans() }}</div>
                        @endif
                    </div>
                </div>
                
                {{-- Products Cache Stats --}}
                <div class="bg-zinc-50 dark:bg-zinc-700 rounded-lg p-3">
                    <h4 class="text-sm font-medium text-zinc-900 dark:text-zinc-100 mb-2">Products Metrics Cache</h4>
                    <div class="space-y-1 text-xs text-zinc-600 dark:text-zinc-400">
                        <div>Status: {{ $this->productsCacheStats['is_cache_warm'] ? '🔥 Warm' : '❄️ Cold' }}</div>
                        <div>Data Count: {{ number_format($this->productsCacheStats['data_count']) }}</div>
                        <div>Duration: {{ $this->productsCacheStats['cache_duration_minutes'] }}m</div>
                        @if($this->productsCacheStats['last_warmed'])
                            <div>Last Warmed: {{ \Carbon\Carbon::parse($this->productsCacheStats['last_warmed'])->diffForHumans() }}</div>
                        @endif
                    </div>
                </div>
            </div>
            
            @if($this->cacheStatus['last_refresh'])
                <div class="mt-3 bg-zinc-50 dark:bg-zinc-700 rounded-lg p-3">
                    <h4 class="text-sm font-medium text-zinc-900 dark:text-zinc-100 mb-2">Last Refresh Details</h4>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 text-xs text-zinc-600 dark:text-zinc-400">
                        <div>
                            <div class="font-medium">Period</div>
                            <div>{{ $this->cacheStatus['last_refresh']['period'] }}</div>
                        </div>
                        <div>
                            <div class="font-medium">Orders</div>
                            <div>{{ number_format($this->cacheStatus['last_refresh']['orders_count']) }}</div>
                        </div>
                        <div>
                            <div class="font-medium">Duration</div>
                            <div>{{ $this->cacheStatus['last_refresh']['duration_ms'] }}ms</div>
                        </div>
                        <div>
                            <div class="font-medium">Channel</div>
                            <div>{{ $this->cacheStatus['last_refresh']['channel'] ?? 'All' }}</div>
                        </div>
                    </div>
                </div>
            @endif
            
            @if($this->cacheStatus['last_failure'])
                <div class="mt-3 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-3">
                    <h4 class="text-sm font-medium text-red-900 dark:text-red-100 mb-2">Last Failure</h4>
                    <div class="text-xs text-red-700 dark:text-red-300">
                        <div class="mb-1">{{ \Carbon\Carbon::parse($this->cacheStatus['last_failure']['timestamp'])->diffForHumans() }}</div>
                        <div class="font-mono">{{ $this->cacheStatus['last_failure']['error'] }}</div>
                    </div>
                </div>
            @endif
        </div>
    @endif
</div>