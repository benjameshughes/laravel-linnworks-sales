# Metrics Cache Layer Implementation Plan

## Goal
Add a clean cache layer on top of the new metrics architecture WITHOUT touching the pure SalesMetrics service.

## Architecture

```
Livewire Component (UI Layer)
        ↓
MetricsCacheService (Cache Layer) ← NEW!
        ↓ (on miss or warm)
SalesMetrics (Business Logic) ← NO CHANGES!
        ↓
Repository/Factory/Actions ← NO CHANGES!
```

---

## Phase 1: Create MetricsCacheService

### File: `app/Services/Cache/MetricsCacheService.php`

**Purpose:** Single source of truth for ALL metrics caching logic.

**Key Methods:**

```php
// Read methods (lazy - uses Cache::remember)
public function getMetricsSummary(...): ?Collection
public function getTopChannels(...): ?Collection
public function getTopProducts(...): ?Collection
public function getDailyRevenueData(...): ?Collection
public function getChannelDistributionData(...): ?Collection

// Warm methods (explicit - uses Cache::put)
public function warmMetricsSummary(...): void
public function warmTopChannels(...): void
public function warmTopProducts(...): void
public function warmDailyRevenueData(...): void
public function warmChannelDistributionData(...): void

// Utility
private function buildKey(string $metric, ...): string
public function flush(): void
```

**Behavior:**

**Read methods:**
- Check cache with `Cache::get($key)`
- If HIT → return cached data
- If MISS → dispatch background job to warm cache + return `null`
- Livewire will show loading skeleton on `null`

**Warm methods:**
- Call `SalesMetrics` service directly
- Put result in cache with `Cache::put($key, $data, ttl)`
- Broadcast event when done: `MetricsCacheWarmed`

**Key Format:**
```
metrics:summary:{period}:{channel}:{customFrom}:{customTo}
metrics:top_channels:{period}:{channel}:{limit}
metrics:top_products:{period}:{channel}:{limit}
metrics:daily_revenue:{period}:{customFrom}:{customTo}
metrics:channel_distribution:{period}:{channel}:{limit}
```

---

## Phase 2: Create Background Warming Job

### File: `app/Jobs/WarmMetricCacheJob.php`

**Purpose:** Warm a single metric in the background.

```php
class WarmMetricCacheJob implements ShouldQueue
{
    public function __construct(
        public readonly string $metric,
        public readonly array $params,
    ) {
        $this->onQueue('cache');
    }

    public function handle(): void
    {
        $cacheService = app(MetricsCacheService::class);

        // Call the appropriate warm method
        match($this->metric) {
            'summary' => $cacheService->warmMetricsSummary(...$this->params),
            'top_channels' => $cacheService->warmTopChannels(...$this->params),
            'top_products' => $cacheService->warmTopProducts(...$this->params),
            'daily_revenue' => $cacheService->warmDailyRevenueData(...$this->params),
            'channel_distribution' => $cacheService->warmChannelDistributionData(...$this->params),
        };

        // Broadcast completion
        broadcast(new MetricsCacheWarmed($this->metric, $this->params));
    }
}
```

---

## Phase 3: Create Event

### File: `app/Events/MetricsCacheWarmed.php`

**Purpose:** Notify frontend when cache is ready.

```php
class MetricsCacheWarmed implements ShouldBroadcast
{
    public function __construct(
        public readonly string $metric,
        public readonly array $params,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel('cache-management'),
        ];
    }
}
```

---

## Phase 4: Update Livewire Components

### Pattern for ALL dashboard components:

**Before (direct service call):**
```php
#[Computed]
public function metrics(): Collection
{
    return app(SalesMetrics::class)->getMetricsSummary(...);
}
```

**After (cache layer):**
```php
#[Computed]
public function metrics(): ?Collection
{
    return app(MetricsCacheService::class)->getMetricsSummary(
        period: $this->period,
        channel: $this->channel,
        customFrom: $this->customFrom,
        customTo: $this->customTo
    );
}

#[On('echo:cache-management,MetricsCacheWarmed')]
public function refreshMetrics(array $event): void
{
    // Only refresh if this metric was warmed
    if ($event['metric'] === 'summary') {
        unset($this->metrics); // Clear computed cache
    }
}
```

**Blade (handle null state):**
```blade
@if($this->metrics === null)
    {{-- Loading skeleton --}}
    <div class="animate-pulse">
        <div class="h-4 bg-gray-200 rounded w-3/4"></div>
    </div>
    <span class="text-sm text-gray-500">
        Calculating metrics... ⏳
    </span>
@else
    {{-- Real data --}}
    <div>Revenue: {{ $this->metrics['total_revenue'] }}</div>
@endif
```

**Components to update:**
- ✅ MetricsSummary
- ✅ TopChannels
- ✅ TopProducts
- ✅ DailyRevenueChart
- ✅ SalesTrendChart
- ✅ ChannelDistributionChart
- ✅ RecentOrders (maybe - less critical)

---

## Phase 5: Update WarmPeriodCacheJob

### File: `app/Jobs/WarmPeriodCacheJob.php`

**Current:** Directly calculates and caches
**New:** Just calls MetricsCacheService warm methods

```php
public function handle(): void
{
    $cache = app(MetricsCacheService::class);

    // Define what to warm
    $periods = ['0', '1', '7', '30', '90', '365', '730'];
    $channels = ['all', 'Amazon', 'eBay', /* ... */];

    foreach ($periods as $period) {
        // Summary
        foreach ($channels as $channel) {
            $cache->warmMetricsSummary($period, $channel);
        }

        // Top channels (all channels combined)
        $cache->warmTopChannels($period, 'all', 6);

        // Top products
        foreach ($channels as $channel) {
            $cache->warmTopProducts($period, $channel, 10);
        }

        // Daily revenue
        $cache->warmDailyRevenueData($period);

        // Channel distribution
        $cache->warmChannelDistributionData($period, 'all', 6);
    }

    broadcast(new CacheWarmingCompleted());
}
```

---

## Phase 6: "Warm Cache" Button

### Current Implementation
Already exists in `DashboardFilters` component - just verify it dispatches the job.

```php
public function warmCache(): void
{
    WarmPeriodCacheJob::dispatch();
    $this->dispatch('cache-warming-started');
}
```

---

## Implementation Checklist

### Core Services
- [ ] Create `app/Services/Cache/MetricsCacheService.php`
- [ ] Create `app/Jobs/WarmMetricCacheJob.php`
- [ ] Create `app/Events/MetricsCacheWarmed.php`

### Livewire Components
- [ ] Update `MetricsSummary.php` to use cache service
- [ ] Update `TopChannels.php` to use cache service
- [ ] Update `TopProducts.php` to use cache service
- [ ] Update `DailyRevenueChart.php` to use cache service
- [ ] Update `SalesTrendChart.php` to use cache service
- [ ] Update `ChannelDistributionChart.php` to use cache service
- [ ] Update `RecentOrders.php` (optional)

### Jobs
- [ ] Update `WarmPeriodCacheJob.php` to use cache service

### Blade Templates
- [ ] Update all component views to handle `null` state (loading skeletons)

### Tests
- [ ] Unit tests for `MetricsCacheService`
- [ ] Feature tests for cache warming flow
- [ ] Livewire tests for null handling

---

## User Experience Flow

1. **User loads dashboard (cache cold)**
   - Livewire calls `MetricsCacheService->getMetricsSummary()`
   - Cache miss → returns `null`
   - Job dispatched to warm cache
   - User sees loading skeleton

2. **Background job runs**
   - Calls `SalesMetrics` service (pure logic)
   - Caches result
   - Broadcasts `MetricsCacheWarmed` event

3. **Frontend receives event**
   - Livewire hears event via Laravel Echo
   - Clears computed property cache
   - Re-fetches from `MetricsCacheService`
   - Cache hit → returns data
   - Loading skeleton → Real data ✨

4. **Subsequent loads (cache warm)**
   - Livewire calls `MetricsCacheService->getMetricsSummary()`
   - Cache hit → instant return
   - No loading state, immediate render

---

## Benefits

✅ **Clean Separation:** SalesMetrics stays pure (no cache logic)
✅ **Single Responsibility:** All cache logic in one service
✅ **Async UX:** Users never wait for calculations
✅ **Real-time Updates:** Laravel Echo broadcasts completion
✅ **Background Processing:** Heavy lifting in queued jobs
✅ **Testable:** Can test service without cache, cache without service
✅ **Flexible:** Swap cache backends (Redis, Memcached) easily

---

## Notes

- Laravel Echo is already configured and working
- Cache TTL should be configurable (default: 1 hour)
- Consider cache tags for easier flushing by group
- Monitor cache hit rates in production
- Document cache key structure for debugging

---

**Next Action:** Pass this plan to an agent to implement Phase 1-3, then we'll manually test before doing Phases 4-6.
