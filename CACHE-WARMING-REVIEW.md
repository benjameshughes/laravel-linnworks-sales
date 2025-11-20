# Cache Warming Infrastructure Review

## Executive Summary

**Status**: ✅ Working, but needs refactoring
**Branch**: `refactor/database-cleanup` → Should move to new branch `refactor/cache-warming`

The cache warming system is functional but has technical debt from the PoC phase. Now that database refactoring is complete, this needs a proper architectural review.

---

## Current Architecture

### Job: `WarmPeriodCacheJob`

**Purpose**: Warm cache for a specific period/channel combination

**Issues**:
1. ❌ **Property naming inconsistency** (line 57, 75-76, 118):
   - Constructor uses `$source`
   - Code references `$this->channel`
   - Creates confusion and likely causes errors

2. ❌ **Dual calculation paths** (lines 147-213):
   - Small periods: Service → Factory → Cache
   - Large periods: ChunkedMetricsCalculator → Cache
   - Two completely different code paths = maintenance nightmare

3. ❌ **Mixed responsibilities**:
   - Job handles: metric calculation, caching, broadcasting, memory management
   - Violates Single Responsibility Principle

4. ⚠️ **Memory optimization overkill** (lines 113-132):
   - Manual `unset()`, `gc_collect_cycles()`
   - Likely unnecessary with modern PHP garbage collection
   - Adds complexity without proven benefit

5. ⚠️ **Configuration coupling** (line 224):
   - `config('dashboard.chunked_calculation_threshold')`
   - Job shouldn't know about UI config

6. ✅ **Good parts**:
   - Proper error handling and logging
   - Retry logic (3 attempts)
   - Batch support
   - Broadcasting events

---

## Events

### `CachePeriodWarmed`
**Status**: ✅ Clean
**Purpose**: Notify UI that cache is ready
**Issues**: None

### `CachePeriodWarmingStarted`
**Status**: ✅ Clean
**Purpose**: Show progress in UI
**Issues**: None

---

## Services & Calculators

### `SalesMetrics` Service
**Status**: ⚠️ Mixed with presentation logic
**Used for**: Small periods (<180 days)
**Issues**:
- Returns Chart.js formatted data
- Mixes business logic with UI formatting

### `ChunkedMetricsCalculator`
**Status**: ✅ Recently fixed (database columns)
**Used for**: Large periods (≥180 days)
**Issues**:
- Different API than SalesMetrics
- Hard to maintain two parallel systems

---

## Recommendations

### Option 1: Quick Wins (Same Branch)
**Time**: 30 minutes
**Impact**: Medium

1. **Fix property naming bug**:
   ```php
   // Change constructor parameter
   public function __construct(
       public readonly string $period,
       public readonly string $channel = 'all',  // ← Fix
       public readonly string $status = 'all'    // ← Also fix (was int)
   ) {}
   ```

2. **Remove manual memory management**:
   - Delete `unset($cacheData)`
   - Delete `gc_collect_cycles()`
   - Let PHP handle it

3. **Simplify logging**:
   - Remove debug logs
   - Keep only info/error logs

### Option 2: Proper Refactor (New Branch)
**Time**: 2-3 hours
**Impact**: High
**New Branch**: `refactor/cache-warming`

#### Step 1: Extract Cache Warming Service
```php
final class MetricsCacheService
{
    public function warm(string $period, string $channel, string $status): array
    {
        // Single unified calculation path
        return $this->calculator->calculate($period, $channel, $status);
    }

    public function get(string $period, string $channel, string $status): ?array
    {
        return Cache::get($this->getCacheKey($period, $channel, $status));
    }

    public function invalidate(string $period, string $channel, string $status): void
    {
        Cache::forget($this->getCacheKey($period, $channel, $status));
    }
}
```

#### Step 2: Simplify Job
```php
final class WarmPeriodCacheJob implements ShouldQueue
{
    public function __construct(
        public readonly string $period,
        public readonly string $channel = 'all',
        public readonly string $status = 'all'
    ) {}

    public function handle(MetricsCacheService $cache): void
    {
        CachePeriodWarmingStarted::dispatch($this->period);

        $metrics = $cache->warm($this->period, $this->channel, $this->status);

        CachePeriodWarmed::dispatch(
            $this->period,
            $metrics['orders'],
            $metrics['revenue'],
            $metrics['items']
        );
    }
}
```

#### Step 3: Unify Calculators
- Merge `SalesMetrics` and `ChunkedMetricsCalculator`
- Single API, single code path
- Automatic chunking when needed (transparent to caller)

---

## Decision Points

### Should we fix now or later?

**Fix Now (Option 1)** if:
- ✅ You want to merge `refactor/database-cleanup` soon
- ✅ Cache warming is working "good enough"
- ✅ You have other priorities

**Refactor Properly (Option 2)** if:
- ✅ You're planning more cache features
- ✅ You want clean, maintainable code
- ✅ You have 2-3 hours to invest now

### My Recommendation

**Wrap up database refactoring** → Merge `refactor/database-cleanup`

**Then** create `refactor/cache-warming` for proper cleanup:
1. Extract MetricsCacheService
2. Unify calculation paths
3. Remove presentation logic from services
4. Simplify job to 20 lines

This keeps PRs focused and reviewable.

---

## Testing Checklist

Before merging `refactor/database-cleanup`:
- [ ] Recent orders sync works ✅ (confirmed)
- [ ] Historical import works ✅ (confirmed)
- [ ] Cache warming completes without errors (needs retest after fixes)
- [ ] UI shows warmed cache data
- [ ] Reverb broadcasting works

---

## Summary

**Database Refactoring**: ✅ COMPLETE
**Cache Warming**: ⚠️ Works but needs cleanup

**Next Steps**:
1. Quick fix property naming bug
2. Merge database refactor branch
3. New branch for cache warming refactor
4. Then tackle "the potatoes" (shipping, notes, properties)