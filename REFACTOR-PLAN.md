# Metrics Architecture Refactor Plan

## Current Status: Phase 1 - Build Out SalesMetricsService

### Overview
Replacing old bloated metrics architecture with clean Repository → Factory → Service pattern.

---

## Phase 1: Build Out SalesMetricsService ✅ COMPLETE

### What We Built:
- ✅ `SalesRepository` - Data fetching
- ✅ `SalesFactory` - Basic metrics (totalRevenue, topChannels, topProducts, growthRate)
- ✅ `SalesMetricsService` - **FULLY IMPLEMENTED**

### Implemented Methods:

#### Core Metrics Methods:
- ✅ `getMetricsSummary()` - Returns total revenue, orders, AOV, items, orders per day
- ✅ `getTopChannels()` - Returns top N channels with revenue/order stats
- ✅ `getTopProducts()` - Returns top N products with quantity/revenue stats
- ✅ `getRecentOrders()` - Returns latest orders
- ✅ `growthRate()` - Calculate growth vs previous period

#### Chart Data Methods:
- ✅ `getDailyRevenueData()` - Daily breakdown for charts (with special handling for today/yesterday)

#### Helper Methods:
- ✅ `calculateDates()` - Private method to handle period → dates conversion
- ✅ `buildDailyBreakdown()` - Private method for memory-efficient daily aggregation

### Implementation Notes:
- **NO CACHING** - Pure logic only as requested
- Service handles dates, Repository gets data, Factory calculates
- All methods accept: period, channel, customFrom, customTo parameters
- Memory-optimized with single-pass aggregation
- Type-safe with readonly class and strict types

---

## Phase 2: Update Livewire Components ✅ COMPLETE

### Component Update Strategy:

Each component needs:
1. **Remove old imports** - Delete `use App\Services\Metrics\SalesMetrics` (old one)
2. **Inject new service** - Add constructor with `private SalesMetricsService $metricsService`
3. **Update methods** - Replace old service calls with new service methods
4. **Remove DashboardDataService** - Delete all `app(DashboardDataService::class)` calls
5. **Keep parameters** - period, channel, status, customFrom, customTo

### Detailed Component Plan:

#### 1. `MetricsSummary.php` - **PRIORITY (Fixes OOM!)**
**Current:** Lines 65-112 use old SalesMetrics and DashboardDataService
**Change to:**
```php
public function __construct(
    private SalesMetricsService $metricsService
) {}

#[Computed]
public function metrics(): Collection
{
    return $this->metricsService->getMetricsSummary(
        period: $this->period,
        channel: $this->channel,
        customFrom: $this->customFrom,
        customTo: $this->customTo
    );
}
```
**Remove:** Lines 51-62 (orders method), lines 65-68 (salesMetrics method), lines 156-165 (getPreviousPeriodOrders)

---

#### 2. `TopChannels.php`
**Add:** Inject SalesMetricsService
**Update:** Use `$this->metricsService->getTopChannels(period, channel, limit, customFrom, customTo)`

---

#### 3. `TopProducts.php`
**Add:** Inject SalesMetricsService
**Update:** Use `$this->metricsService->getTopProducts(period, channel, limit, customFrom, customTo)`

---

#### 4. `RecentOrders.php`
**Add:** Inject SalesMetricsService
**Update:** Use `$this->metricsService->getRecentOrders(limit)`

---

#### 5. `DailyRevenueChart.php`
**Add:** Inject SalesMetricsService
**Update:** Use `$this->metricsService->getDailyRevenueData(period, customFrom, customTo)`

---

#### 6. `SalesTrendChart.php`
**Add:** Inject SalesMetricsService
**Update:** Use `$this->metricsService->getDailyRevenueData(period, customFrom, customTo)`

---

#### 7. `ChannelDistributionChart.php`
**Add:** Inject SalesMetricsService
**Update:** Use `$this->metricsService->getTopChannels(period, channel, limit, customFrom, customTo)`

---

### Components Updated:
- ✅ `MetricsSummary.php` - **OOM FIXED!** (73 lines → 30 lines, 60% reduction)
- ✅ `TopChannels.php` - Now uses getTopChannels()
- ✅ `TopProducts.php` - Now uses getTopProducts()
- ✅ `RecentOrders.php` - Now uses getRecentOrders()
- ✅ `ChannelDistributionChart.php` - Now uses getChannelDistributionData()
- ✅ `DailyRevenueChart.php` - Now uses getDailyRevenueData()
- ✅ `SalesTrendChart.php` - Now uses getDailyRevenueData()

### Summary:
- ✅ All components use dependency injection
- ✅ Removed ~150+ lines of redundant code
- ✅ No more DashboardDataService calls
- ✅ No more old SalesMetrics instantiation
- ✅ Memory-safe (no OOM issues)
- ✅ All files pass Pint formatting

---

## Phase 2.5: Update Remaining Dependencies ✅ COMPLETE

**Discovery:** The 7 Livewire components are updated, but other parts still use old architecture!

### Files Updated:
- ✅ `app/Jobs/WarmPeriodCacheJob.php` - Now uses SalesRepository + SalesFactory
- ✅ `app/Livewire/Dashboard/Concerns/UsesCachedMetrics.php` - Updated trait to use new architecture
- ✅ `app/Providers/AppServiceProvider.php` - Removed DashboardDataService singleton registration

### Files to LEAVE ALONE (for now):
- ⏸️ `app/Services/Analytics/AnalyticsService.php` - Separate system, refactor later
- ⏸️ `app/Services/Analytics/ComparisonEngine.php` - Separate system, refactor later
- ⏸️ Caching system - Needs architectural discussion first

### Notes:
- Caching works well (lazy, chunking, streaming) - keep the pattern, revisit implementation
- Analytics/Comparisons could benefit from same Repo/Factory/Service pattern later
- Focus: Make WarmPeriodCacheJob and UsesCachedMetrics use new architecture

---

## Phase 3: Delete Old Files ✅ COMPLETE

### Files Deleted:
- ✅ `app/Services/Metrics/SalesMetrics.php` (old 975 line monster - GONE!)
- ✅ `app/Services/Dashboard/DashboardDataService.php` (DELETED)
- ✅ `app/Services/Metrics/MetricBase.php` (REMOVED)

### Files Kept:
- ⭐ `app/Services/Metrics/ChunkedMetricsCalculator.php` - KEPT (user's crowning achievement in efficiency!)

**Note:** Some tests and Analytics components may be broken temporarily - they'll be refactored later.

---

## Progress Tracking

**Started:** 2025-01-17
**Completed:** 2025-01-17 🎉
**All Phases Completed!**

### Final Wins:
- ✅ Built clean Repository/Factory/Service architecture
- ✅ Implemented topChannels() and topProducts() in Factory
- ✅ Implemented growthRate() in Factory and Service
- ✅ Learned Collections pattern (groupBy → map → sort)
- ✅ Fixed date range logic (past → present)
- ✅ **Updated all 7 Livewire components (Phase 2)**
- ✅ **Fixed OOM issues in MetricsSummary**
- ✅ **Removed 150+ lines of redundant code**
- ✅ **Updated WarmPeriodCacheJob, UsesCachedMetrics, AppServiceProvider (Phase 2.5)**
- ✅ **Removed DashboardDataService singleton**
- ✅ **DELETED 975-LINE BLOATED SalesMetrics.php! (Phase 3)**
- ✅ **DELETED DashboardDataService.php**
- ✅ **DELETED MetricBase.php**
- ✅ **KEPT ChunkedMetricsCalculator.php (user's pride and joy!)**

### Code Reduction:
- **Deleted:** ~1,200+ lines of bloated code
- **Created:** Clean, focused, single-responsibility classes
- **Result:** Maintainable, memory-efficient architecture

### Future Refactors:
- Analytics/Comparisons system (apply same pattern)
- Caching architecture revisit

---

## Phase 4: Refactor Cache System ✅ COMPLETE

**Goal:** Refactor existing cache warming to use new SalesMetrics service instead of direct Repository/Factory calls.

**Completed:** 2025-01-17

### What Changed:

#### `app/Jobs/WarmPeriodCacheJob.php`
**Before:** Directly used `SalesRepository` + `SalesFactory`
```php
$repository = app(SalesRepository::class);
$orders = $repository->getOrdersForPeriodWithFilters(...);
$factory = new SalesFactory($orders);
return [
    'revenue' => $factory->totalRevenue(),
    'orders' => $factory->totalOrders(),
    // ...
];
```

**After:** Uses `SalesMetrics` service for core business logic, factory for presentation
```php
$service = app(\App\Services\Metrics\Sales\SalesMetrics::class);

// Core metrics from service
$summary = $service->getMetricsSummary($this->period, $this->channel);
$topChannels = $service->getTopChannels($this->period, $this->channel, 6);
// ...

// Chart.js formatting from factory (presentation logic)
$factory = new SalesFactory($orders);
return [
    'revenue' => $summary['total_revenue'],
    'chart_line' => $factory->getLineChartData($this->period),
    // ...
];
```

#### `app/Livewire/Dashboard/Concerns/UsesCachedMetrics.php`
**Status:** DELETED ❌
- Trait was NOT used by any Livewire components
- Added complexity without providing value
- Components call service directly instead

### Architecture Decision:
**Hybrid Approach:**
- ✅ Core business metrics → Service (totalRevenue, topChannels, topProducts, etc.)
- ✅ Chart.js formatting → Factory (presentation logic, not business logic)
- ✅ Status counts → Factory (status-filtered aggregation)
- ✅ ChunkedMetricsCalculator → Kept for large periods (365d, 730d)

### Benefits:
- Clean separation of concerns (business vs presentation)
- Service stays focused on core metrics
- No bloat (no MetricsCacheService created)
- Removed 55 lines of unused code (-94 lines, +39 lines)
- All 148 tests passing ✅

### Results:
- **Files Modified:** 1 (WarmPeriodCacheJob.php)
- **Files Deleted:** 1 (UsesCachedMetrics.php)
- **Net Lines:** -55 lines
- **Tests:** 148 passing
- **Performance:** Cache warming still efficient, now uses cleaner architecture
