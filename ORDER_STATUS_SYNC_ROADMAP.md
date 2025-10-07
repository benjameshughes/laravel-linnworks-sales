# Order Status Sync & Historical Data Roadmap

**Project**: Sync historical processed orders + auto-update order statuses
**Started**: October 2025
**Status**: 🚧 In Progress

---

## 📊 CURRENT STATE (Baseline)

### Database Stats
- **672 open orders** in database (includes 2-month-old stale data)
- **0 processed orders** synced yet
- **346 orders with revenue data** (from open orders sync)

### Data Quality Issues
```
❌ Tax: £0.00          → Open orders don't include tax
❌ Postage: £0.00      → Open orders don't include postage
❌ Tracking: Missing   → Not captured from open orders
❌ Status: Stale       → 2-month-old orders still marked "open"
❌ Profit: £0.00       → No cost data (separate issue, being worked)
```

### What's Working
- ✅ Open orders sync (getting new orders)
- ✅ Order items with quantities/prices
- ✅ Product catalog (4,562 products synced)
- ✅ Analytics extraction working

---

## 🎯 PROJECT GOALS

### Phase 1: Historical Catch-Up (One-time)
**Goal**: Import processed orders from Linnworks to update stale database

**Target**: Last 2 years (configurable, user can specify)

**Expected Results**:
- ~672 stale orders updated with proper status
- Tax and postage data populated
- Order status breakdown: dispatched/cancelled/refunded
- Tracking numbers captured
- Processing timestamps recorded

### Phase 2: Ongoing Status Monitor (Scheduled)
**Goal**: Auto-detect when open orders become processed in Linnworks

**Mechanism**:
```
Every 15 minutes (after open orders sync):
1. Get current open order IDs from Linnworks
2. Compare with open orders in DB
3. Orders that "disappeared" → fetch from processed API
4. Update with full details (status, tax, postage, tracking)
```

**Expected Results**:
- Real-time order status updates
- No more stale "open" orders
- Complete fulfillment data
- Accurate analytics

---

## 📋 DETAILED TASK BREAKDOWN

### ✅ COMPLETED
- [x] Product sync fixed (Stock API endpoints)
- [x] Product pricing capture implemented
- [x] Analytics with cost tracking
- [x] Open orders sync working

---

### 🚧 PHASE 1: HISTORICAL IMPORT

#### Task 1.1: Enhance Historical Import Command ⏳
**File**: `app/Console/Commands/ImportHistoricalOrders.php`

**Requirements**:
- [x] Support --from and --to dates (already exists)
- [ ] Add 2-year maximum validation
- [ ] Better status handling (dispatched/cancelled/refunded)
- [ ] Update matching logic (update existing vs create new)

**Implementation**:
```php
// Add validation
if ($dateRange['from']->diffInDays(now()) > 730) {
    throw new \Exception('Maximum historical import is 2 years (730 days)');
}

// Status mapping
$statusMap = [
    1 => 'dispatched',
    2 => 'cancelled',
    3 => 'on_hold',
    4 => 'refunded',
];
```

**Commands**:
```bash
# Flexible date range examples
php artisan import:historical-orders --days=90
php artisan import:historical-orders --from=2024-01-01 --to=2024-12-31
php artisan import:historical-orders --from=2023-10-01  # Last 2 years max
```

**Status**: 🟡 In Progress

---

#### Task 1.2: Add Status Fields to Database ⏳
**Migration**: `add_order_status_fields`

**Fields to add**:
```php
$table->boolean('is_cancelled')->default(false);
$table->string('status_reason')->nullable();  // cancellation/refund reason
$table->timestamp('cancelled_at')->nullable();
$table->timestamp('dispatched_at')->nullable();
```

**Update fillable in Order model**:
```php
protected $fillable = [
    // ... existing
    'is_cancelled',
    'status_reason',
    'cancelled_at',
    'dispatched_at',
];
```

**Status**: 🔴 Not Started

---

#### Task 1.3: Test Dry Run ⏳
**Command**: `php artisan import:historical-orders --days=90 --dry-run`

**Verification checklist**:
- [ ] Shows correct date range
- [ ] Displays total orders available
- [ ] Shows sample order data
- [ ] No errors in API calls
- [ ] Respects rate limits

**Status**: 🔴 Not Started

---

#### Task 1.4: Run Live Import ⏳
**Command**: `php artisan import:historical-orders --days=90`

**Success Criteria**:
- [ ] All processed orders imported
- [ ] No duplicates created
- [ ] Tax/postage data populated
- [ ] Order statuses correctly set
- [ ] Analytics show updated data

**Status**: 🔴 Not Started

---

### 🚧 PHASE 2: ONGOING STATUS MONITOR

#### Task 2.1: Create UpdateProcessedOrderDetails Command ⏳
**File**: `app/Console/Commands/UpdateProcessedOrderDetails.php`

**Logic Flow**:
```php
1. Get all open order IDs from Linnworks API
2. Get all open orders from database
3. Find orders in DB but NOT in Linnworks = just processed
4. For each disappeared order:
   a. Fetch from processed orders API
   b. Update order with full details
   c. Set is_open = false, is_processed = true
   d. Update status, tax, postage, tracking
5. Log all status changes
```

**Command signature**:
```php
protected $signature = 'orders:update-processed-status
                        {--check-last-hours=24 : Only check orders received in last N hours}
                        {--dry-run : Show what would be updated}';
```

**Status**: 🔴 Not Started

---

#### Task 2.2: Add Processed Order Fetcher ⏳
**Enhancement**: `LinnworksApiService`

**New method**:
```php
public function getProcessedOrdersByIds(array $orderIds): Collection
{
    // Fetch specific processed orders by ID
    // Returns full order details with tax, postage, tracking
}
```

**Status**: 🔴 Not Started

---

#### Task 2.3: Add to Scheduler ⏳
**File**: `routes/console.php`

**Schedule**:
```php
// Run every 15 minutes (after open orders sync)
Schedule::command('sync:linnworks-orders')->everyFifteenMinutes();

// Run 2 minutes after to check for status changes
Schedule::command('orders:update-processed-status')
    ->everyFifteenMinutes()
    ->at(':02');  // Runs at :02, :17, :32, :47
```

**Status**: 🔴 Not Started

---

### 🚧 PHASE 3: ANALYTICS ENHANCEMENTS

#### Task 3.1: Update Analytics for Order Status ⏳
**File**: `app/Console/Commands/ExtractOrderAnalytics.php`

**Enhancements**:
```php
// Exclude cancelled orders from revenue
$totalRevenue = $ordersQuery
    ->where('is_cancelled', false)
    ->sum('total_charge');

// Add status breakdown
+------------------+-------+----------+
| Status           | Count | Revenue  |
+------------------+-------+----------+
| Dispatched       | 580   | £15,234  |
| Open (Pending)   | 92    | £2,341   |
| Cancelled        | 15    | £0       |
| Refunded         | 5     | £234     |
+------------------+-------+----------+
```

**Status**: 🔴 Not Started

---

#### Task 3.2: Add Fulfillment Time Metrics ⏳
**New Analytics Section**:

```php
📦 FULFILLMENT PERFORMANCE
=================================================
Average Time to Process: 2.3 days
Fastest: 0.5 days
Slowest: 7.2 days

By Channel:
- Amazon: 1.8 days (fast)
- eBay: 2.5 days (medium)
- Direct: 3.2 days (slow)
```

**Status**: 🔴 Not Started

---

## 🎯 SUCCESS METRICS

### Phase 1 Success = Historical Data Loaded
- [ ] Zero orders with "stale" open status older than 1 day
- [ ] Tax and postage totals > £0
- [ ] Status distribution makes sense (most dispatched)
- [ ] Analytics show complete financial picture

### Phase 2 Success = Real-Time Updates
- [ ] Orders auto-update within 15 minutes of Linnworks change
- [ ] No manual intervention needed
- [ ] Accurate status at all times
- [ ] Complete audit trail with timestamps

---

## 📅 TIMELINE ESTIMATE

### Week 1: Foundation
- **Day 1**: Add status fields, enhance import command ✅
- **Day 2**: Test dry run, validate data ✅
- **Day 3**: Run live historical import ✅
- **Day 4**: Verify analytics, fix issues ✅
- **Day 5**: Buffer for unexpected issues ⏳

### Week 2: Automation
- **Day 1**: Build UpdateProcessedOrderDetails command ⏳
- **Day 2**: Test on sample orders ⏳
- **Day 3**: Add to scheduler, monitor ⏳
- **Day 4**: Enhance analytics with status ⏳
- **Day 5**: Documentation & refinement ⏳

---

## 🔧 TECHNICAL NOTES

### API Constraints
- Processed orders API requires **minimum 20 results per page**
- Rate limit: 150 requests/minute (auto-managed)
- Maximum date range per request: None specified
- Historical data availability: Unknown, likely 2+ years

### Order Status in Linnworks
```php
nStatus field values:
0 = Unknown/Pending
1 = Processed/Dispatched ✅
2 = Cancelled ❌
3 = On Hold ⏸️
4 = Refunded 💰
```

### Database Deduplication
- Primary key: `linnworks_order_id` (UUID from Linnworks)
- Unique constraint prevents duplicates
- Update strategy: Find by `linnworks_order_id`, update if exists

---

## 🐛 KNOWN ISSUES & RISKS

### Current Issues
1. **Stale data** - 672 orders need status updates
2. **Missing tax/postage** - £0.00 in analytics
3. **No cancellation tracking** - Can't distinguish cancelled orders
4. **Cost data** - Separate issue, awaiting Linnworks data entry

### Potential Risks
1. **Large batch import** - Might hit rate limits (mitigated with batching)
2. **Order matching** - Ensuring we update correct orders (using linnworks_order_id)
3. **Status transitions** - Orders might change while we're syncing (idempotent operations)
4. **API changes** - Linnworks might change response format (defensive coding)

---

## 💡 FUTURE ENHANCEMENTS (Post-MVP)

### Nice to Have
- [ ] Email notifications when orders cancelled/refunded
- [ ] Webhook support (if Linnworks provides)
- [ ] Status change history log
- [ ] Fulfillment SLA alerting
- [ ] Courier performance tracking
- [ ] Returns management

---

## 📚 REFERENCES

- [Open Orders API Spec](https://github.com/LinnSystems/PublicApiSpecs/blob/main/1.0/openorders.json)
- [Processed Orders API Spec](https://github.com/LinnSystems/PublicApiSpecs/blob/main/1.0/processedorders.json)
- [Linnworks Data Guide](./LINNWORKS_DATA_GUIDE.md)

---

## 📊 PROGRESS TRACKER

**Overall Progress**: ██░░░░░░░░ 20%

- ✅ Planning & Documentation: 100%
- 🚧 Phase 1 (Historical Import): 10%
- 🔴 Phase 2 (Status Monitor): 0%
- 🔴 Phase 3 (Analytics): 0%

**Last Updated**: October 7, 2025
**Next Milestone**: Complete Phase 1 by end of week
