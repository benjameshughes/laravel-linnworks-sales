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

## Phase 3: Delete Old Files ⬅️ READY TO START

### Files to Delete:
- [ ] `app/Services/Metrics/SalesMetrics.php` (old 975 line monster)
- [ ] `app/Services/Dashboard/DashboardDataService.php`
- [ ] `app/Services/Metrics/MetricBase.php` (if not used elsewhere)
- [ ] `app/Services/Metrics/ChunkedMetricsCalculator.php` (if not used elsewhere)

---

## Progress Tracking

**Started:** 2025-01-17
**Current Phase:** 3
**Last Updated:** 2025-01-17
**Phase 1 Completed:** 2025-01-17
**Phase 2 Completed:** 2025-01-17

### Wins So Far:
- ✅ Built Repository/Factory/Service architecture
- ✅ Implemented topChannels() and topProducts() in Factory
- ✅ Implemented growthRate() in Factory and Service
- ✅ Learned Collections pattern (groupBy → map → sort)
- ✅ Fixed date range logic (past → present)
- ✅ **Updated all 7 Livewire components (Phase 2)**
- ✅ **Fixed OOM issues in MetricsSummary**
- ✅ **Removed 150+ lines of redundant code**

### Next Action:
Delete old bloated files (Phase 3) - The cleansing begins! 🔥
