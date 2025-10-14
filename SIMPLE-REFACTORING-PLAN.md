# Simple Database-Code Alignment Plan

**Branch:** `refactor/database-code-alignment`
**Architecture:** API → DTO → Service Layer → Database

---

## The Simple Problem

Your database schema is **correct**. Your code is using **wrong field names**.

```
Linnworks API → LinnworksOrder DTO → OrderImportDTO → DB
                                          ↓
                                    (wrong field names!)
                                          ↓
                                    MySQL Database
```

The `OrderImportDTO` creates arrays with field names that don't match your actual database columns.

---

## The Simple Solution

**Align code to match database (not the other way around).**

We're going to:
1. Make DTO output match database columns exactly
2. Make Model fillable match database columns exactly
3. Update Service Layer if needed
4. Clean up duplicate/deprecated fields

**No complex migrations. No database changes. Just fix the PHP code.**

---

## Current Architecture

```
┌──────────────────┐
│  Linnworks API   │
└────────┬─────────┘
         │
         ▼
┌──────────────────────────────┐
│  LinnworksOrder DTO          │  ← Parses API response
│  (readonly class)            │
└────────┬─────────────────────┘
         │
         ▼
┌──────────────────────────────┐
│  OrderImportDTO              │  ← Converts to DB-ready arrays
│  ::fromLinnworks()           │     (THIS IS WHERE THE BUG IS)
└────────┬─────────────────────┘
         │
         ├─────────────────┐
         ▼                 ▼
┌─────────────────┐  ┌────────────────┐
│  Bulk Import    │  │  Service Layer │
│  (DB::insert)   │  │  (SalesMetric) │
└────────┬────────┘  └────────┬───────┘
         │                    │
         ▼                    ▼
┌──────────────────────────────┐
│      MySQL Database          │
│  - orders                    │
│  - order_items               │
│  - order_shipping            │
│  - order_notes               │
│  - order_properties          │
│  - order_identifiers         │
└──────────────────────────────┘
         │
         ▼
┌──────────────────────────────┐
│  Eloquent Models             │  ← Used for queries/display
│  (Order, OrderItem, etc)     │
└──────────────────────────────┘
```

---

## Simple 3-Step Plan

### Step 1: Fix OrderItem Fields (Critical)

**File:** `app/DataTransferObjects/OrderImportDTO.php`

**Current code (line ~106-120):**
```php
$itemsData = $linnworks->items->map(fn ($item) => [
    'order_id' => null,
    'item_id' => $item->itemId,
    'sku' => $item->sku,
    'quantity' => $item->quantity,
    'unit_cost' => $item->unitCost,           // ❌ DB has 'cost_price'
    'price_per_unit' => $item->pricePerUnit,  // ❌ DB has 'unit_price'
    'line_total' => $item->lineTotal,         // ❌ DB has 'total_price'
    'metadata' => json_encode([...]),         // ❌ DB has 'item_attributes'
    'created_at' => now()->toDateTimeString(),
    'updated_at' => now()->toDateTimeString(),
])->toArray();
```

**Fixed code:**
```php
$itemsData = $linnworks->items->map(fn ($item) => [
    'order_id' => null,
    'item_id' => $item->itemId,
    'linnworks_item_id' => $item->stockItemId ?? null,  // ADD
    'sku' => $item->sku,
    'title' => $item->itemTitle,                        // ADD
    'description' => null,                              // ADD (if available in API)
    'category' => $item->categoryName,                  // ADD
    'quantity' => $item->quantity,
    'unit_price' => $item->pricePerUnit,                // FIXED ✅
    'total_price' => $item->lineTotal,                  // FIXED ✅
    'cost_price' => $item->unitCost,                    // FIXED ✅
    'profit_margin' => $item->profit ?? null,           // ADD
    'tax_rate' => 0,                                    // ADD (calculate if needed)
    'discount_amount' => 0,                             // ADD (calculate if needed)
    'bin_rack' => null,                                 // ADD (if available in API)
    'is_service' => false,                              // ADD (determine logic)
    'item_attributes' => json_encode([                  // FIXED ✅
        'original_title' => $item->itemTitle,
        'original_category' => $item->categoryName,
    ]),
    'created_at' => now()->toDateTimeString(),
    'updated_at' => now()->toDateTimeString(),
])->toArray();
```

**File:** `app/Models/OrderItem.php`

**Update $fillable:**
```php
protected $fillable = [
    'order_id',
    'item_id',
    'linnworks_item_id',  // ADD
    'sku',
    'title',              // ADD
    'description',        // ADD
    'category',           // ADD
    'quantity',
    'unit_price',         // CHANGE from 'price_per_unit'
    'total_price',        // CHANGE from 'line_total'
    'cost_price',         // CHANGE from 'unit_cost'
    'profit_margin',      // ADD
    'tax_rate',
    'discount_amount',
    'bin_rack',           // ADD
    'is_service',         // ADD
    'item_attributes',    // CHANGE from 'metadata'
];
```

**Update casts:**
```php
protected function casts(): array {
    return [
        'quantity' => 'integer',
        'unit_price' => 'decimal:4',      // CHANGE
        'total_price' => 'decimal:4',     // CHANGE
        'cost_price' => 'decimal:4',      // CHANGE
        'profit_margin' => 'decimal:4',
        'tax_rate' => 'decimal:4',
        'discount_amount' => 'decimal:4',
        'is_service' => 'boolean',
        'item_attributes' => 'array',     // CHANGE
    ];
}
```

**Update accessor methods:**
```php
// OLD: line 62-65
protected function profit(): Attribute {
    return Attribute::make(
        get: fn () => $this->line_total - ($this->unit_cost * $this->quantity)  // ❌
    );
}

// NEW:
protected function profit(): Attribute {
    return Attribute::make(
        get: fn () => $this->total_price - ($this->cost_price * $this->quantity)  // ✅
    );
}

// OLD: line 71-74
protected function profitMargin(): Attribute {
    return Attribute::make(
        get: fn () => $this->line_total == 0 ? 0 : ($this->profit / $this->line_total) * 100  // ❌
    );
}

// NEW:
protected function profitMargin(): Attribute {
    return Attribute::make(
        get: fn () => $this->total_price == 0 ? 0 : ($this->profit / $this->total_price) * 100  // ✅
    );
}

// OLD: line 80-84
protected function formattedLineTotal(): Attribute {
    return Attribute::make(
        get: fn () => '£'.number_format($this->line_total, 2)  // ❌
    );
}

// NEW:
protected function formattedTotalPrice(): Attribute {  // Rename method too
    return Attribute::make(
        get: fn () => '£'.number_format($this->total_price, 2)  // ✅
    );
}

// OLD: line 88-92
protected function formattedUnitCost(): Attribute {
    return Attribute::make(
        get: fn () => '£'.number_format($this->unit_cost, 2)  // ❌
    );
}

// NEW:
protected function formattedCostPrice(): Attribute {  // Rename method too
    return Attribute::make(
        get: fn () => '£'.number_format($this->cost_price, 2)  // ✅
    );
}
```

**Update scope:**
```php
// OLD: line 158-160
public function scopeProfitable(Builder $query): Builder {
    return $query->whereRaw('line_total > (unit_cost * quantity)');  // ❌
}

// NEW:
public function scopeProfitable(Builder $query): Builder {
    return $query->whereRaw('total_price > (cost_price * quantity)');  // ✅
}
```

---

### Step 2: Fix Order::syncOrderItems() Method

**File:** `app/Models/Order.php`

This method manually creates order items. Update field names (line ~489-500):

```php
// OLD:
$this->orderItems()->create([
    'item_id' => $itemId,
    'sku' => $sku,
    'quantity' => $quantity,
    'unit_cost' => $isObject ? $item->unitCost : ($item['unit_cost'] ?? 0),        // ❌
    'price_per_unit' => $pricePerUnit,                                             // ❌
    'line_total' => $isObject ? $item->lineTotal : ($item['line_total'] ?? 0),    // ❌
    'metadata' => array_filter([...]),                                             // ❌
]);

// NEW:
$this->orderItems()->create([
    'item_id' => $itemId,
    'linnworks_item_id' => $isObject ? ($item->stockItemId ?? null) : ($item['stock_item_id'] ?? null),
    'sku' => $sku,
    'title' => $itemTitle,
    'category' => $isObject ? $item->categoryName : ($item['category_name'] ?? null),
    'quantity' => $quantity,
    'cost_price' => $isObject ? $item->unitCost : ($item['unit_cost'] ?? 0),      // ✅
    'unit_price' => $pricePerUnit,                                                 // ✅
    'total_price' => $isObject ? $item->lineTotal : ($item['line_total'] ?? 0),   // ✅
    'profit_margin' => 0,  // Calculate if needed
    'item_attributes' => array_filter([                                            // ✅
        'item_title' => $itemTitle,
        'category_name' => $isObject ? $item->categoryName : ($item['category_name'] ?? null),
    ]),
]);
```

**Also fix the duplicate code block starting at line ~503** (it's repeated logic for arrays).

---

### Step 3: Clean Up Order Table Duplicates

**File:** `app/DataTransferObjects/OrderImportDTO.php`

Remove duplicate fields from the order array (line ~51-103):

```php
// OLD:
$orderData = [
    // ... other fields ...
    'source' => $linnworks->orderSource,              // ❌ Duplicate
    'sub_source' => $subSource,                       // ❌ Duplicate
    'order_source' => $linnworks->orderSource,        // ✅ Keep
    'subsource' => $linnworks->subsource,             // ✅ Keep
    // ... other fields ...
];

// NEW:
$orderData = [
    // ... other fields ...
    // Remove 'source' and 'sub_source' entirely
    'order_source' => $linnworks->orderSource,        // ✅ Keep
    'subsource' => $linnworks->subsource,             // ✅ Keep
    // ... other fields ...
];
```

**File:** `app/Models/Order.php`

Remove deprecated fields from $fillable (line ~32-80):

```php
protected $fillable = [
    'linnworks_order_id',
    'order_id',
    'order_number',
    'channel_name',
    'channel_reference_number',
    // 'source',           // ❌ REMOVE
    // 'sub_source',       // ❌ REMOVE
    'external_reference',
    'total_charge',
    'total_discount',
    'postage_cost',
    'total_paid',
    'profit_margin',
    'currency',
    'status',
    'addresses',
    'received_date',
    'processed_date',
    // 'dispatched_date',  // ❌ REMOVE (use dispatched_at)
    'is_resend',
    'is_exchange',
    'notes',
    'raw_data',
    'items',  // Keep for now (Phase 4 will remove)
    'order_source',      // ✅ KEEP
    'subsource',         // ✅ KEEP
    'tax',
    'order_status',
    'location_id',
    'last_synced_at',
    'is_paid',
    'paid_date',
    'is_open',
    'is_processed',
    'has_refund',
    'is_cancelled',
    'status_reason',
    'cancelled_at',
    'dispatched_at',     // ✅ KEEP
    'sync_status',
    'sync_metadata',
    'marker',
    'is_parked',
    'despatch_by_date',
    'num_items',
    'payment_method',
];
```

**Search for any code using deprecated fields:**

```bash
# Find all references
rg "->source[^_]" app/       # Match ->source but not ->order_source
rg "'source'" app/
rg "->sub_source" app/
rg "'sub_source'" app/
rg "->dispatched_date" app/
rg "'dispatched_date'" app/

# Update each reference to use:
# - order_source instead of source
# - subsource instead of sub_source
# - dispatched_at instead of dispatched_date
```

---

## Step 4: Update SalesMetric Service (If Needed)

**Check:** Does your `SalesMetric` service reference any of the old field names?

```bash
rg "unit_cost|line_total|price_per_unit|metadata" app/Services/
```

If so, update those references to match the new database column names:
- `unit_cost` → `cost_price`
- `line_total` → `total_price`
- `price_per_unit` → `unit_price`
- `metadata` → `item_attributes`

---

## Testing Checklist

After making changes:

```bash
# 1. Clear config cache
php artisan config:clear
php artisan cache:clear

# 2. Test DTO creates correct arrays
php artisan tinker
> $linnworks = LinnworksOrder::fromArray([/* test data */]);
> $dto = OrderImportDTO::fromLinnworks($linnworks);
> $dto->items[0]  // Verify field names match database

# 3. Test model can be filled
> $item = new OrderItem();
> $item->getFillable()  // Should match database columns
> Schema::getColumnListing('order_items')  // Compare

# 4. Test bulk insert works
> DB::table('order_items')->insert($dto->items);  // Should succeed

# 5. Run your tests
php artisan test

# 6. Test historical import
php artisan sales:import-historical  // Or whatever your command is
```

---

## Risk Assessment

**Risk Level:** LOW

**Why?**
- No database changes
- Only PHP code updates
- Easy to rollback (just git revert)
- Can test thoroughly in local/staging first

**What Could Go Wrong?**
1. Existing code referencing old field names breaks
   - **Fix:** Search and replace before deploying
2. Frontend/Livewire components using old field names
   - **Fix:** Search all Blade files and Livewire components
3. API responses still using old names
   - **Fix:** Add API transformers if needed

---

## Deployment Plan

### Before Deployment

```bash
# 1. Find all references to old field names
rg "unit_cost|line_total|price_per_unit|metadata" app/ resources/
rg "source[^_]|sub_source|dispatched_date" app/ resources/

# 2. Update all found references

# 3. Run tests
php artisan test

# 4. Test import process
php artisan sales:import-historical --dry-run  # If you have this flag
```

### Deployment

1. **Commit changes:**
   ```bash
   git add -A
   git commit -m "Align DTO and Model field names with database schema"
   ```

2. **Deploy to staging:**
   ```bash
   git push origin refactor/database-code-alignment
   # Deploy to staging via your CI/CD
   ```

3. **Test on staging:**
   - Run import process
   - Check SalesMetric calculations
   - Verify dashboard displays correctly

4. **Deploy to production:**
   - Merge to main
   - Deploy during low-traffic window
   - Monitor logs for errors

### After Deployment

```bash
# Monitor for errors
tail -f storage/logs/laravel.log

# Check for failed jobs
php artisan queue:failed

# Verify data integrity
php artisan tinker
> OrderItem::where('created_at', '>', now()->subHour())->count()
> Order::where('created_at', '>', now()->subHour())->count()
```

---

## Summary

**What we're doing:**
1. Fix OrderImportDTO to output correct field names
2. Fix OrderItem model to match database
3. Fix Order::syncOrderItems() method
4. Remove duplicate fields from Order model
5. Update any service layer code (SalesMetric)

**What we're NOT doing:**
- No database migrations
- No complex multi-phase rollout
- No data migrations
- No table restructuring

**Timeline:**
- Code changes: 2-3 hours
- Testing: 1-2 hours
- Deployment: 30 minutes
- **Total: 1 day**

This is a simple search-and-replace refactoring to align your code with your database reality.

---

**Ready to implement?** Say the word and I'll start making these changes!
